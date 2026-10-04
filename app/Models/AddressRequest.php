<?php

namespace App\Models;

use App\Notifications\AddressRequestRaised;
use App\Rbac;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A Customer's proposal to change an address, waiting on a reader's decision.
 *
 * A Customer cannot write to the directory, so this is the only way they shape
 * it: they describe what they want and someone holding addresses.approve either
 * applies it or refuses it. The row is also the record of the decision, which is
 * why a decided request is never deleted.
 */
class AddressRequest extends Model
{
    public const TYPE_CREATE = 'create';

    public const TYPE_UPDATE = 'update';

    public const TYPE_DELETE = 'delete';

    /**
     * A whole spreadsheet of new addresses, proposed as one request.
     *
     * One request rather than one per row, so the decision machinery built for a
     * single proposal - one status, one decider, one notification, one audit
     * entry - applies unchanged. The payload is a list of address attribute
     * arrays, one per validated row.
     */
    public const TYPE_IMPORT = 'import';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'address_id',
        'type',
        'payload',
        'before',
        'note',
        'status',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'before' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Files a request: creates it, tells the approvers and records the raise in
     * the audit log.
     *
     * The three happen together so every path that raises a request does all of
     * them. The import proposal is filed from a different controller, and this
     * is what keeps it from silently skipping the queue notification or the
     * audit entry a single-address request writes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function raise(array $attributes): self
    {
        $change = self::create($attributes);

        // Everyone who may decide, minus whoever raised it - a requester never
        // holds approve, so the exclusion is belt-and-braces.
        foreach (User::query()
            ->permission(Rbac::APPROVE_PERMISSION)
            ->whereKeyNot($change->user_id)
            ->get() as $approver) {
            $approver->notify(new AddressRequestRaised($change));
        }

        AuditLog::record(AuditLog::REQUEST_RAISED, $change, null, [
            'type' => $change->type,
            'address' => $change->subjectLabel(),
            'requester' => $change->user->name,
        ]);

        return $change;
    }

    /**
     * The requester, deactivated or not. The queue renders this name with no
     * null guard, so the SoftDeletes scope would make the whole page throw the
     * moment a requester is deactivated - and the queue must survive that.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * The reader who decided, deactivated or not. Same reason as user(): the
     * decided list renders this name without a guard.
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * A request that adds rather than touches an existing address. An import
     * qualifies: it has no address to name, and every row it proposes is new.
     *
     * This must include the import, or isOrphaned() below reads every import
     * proposal as an orphaned edit and the review modal refuses to approve it.
     */
    public function isAddition(): bool
    {
        return in_array($this->type, [self::TYPE_CREATE, self::TYPE_IMPORT], true);
    }

    /**
     * The address went away after the request was raised. The foreign key is
     * nulled rather than cascaded so the request survives its subject; only an
     * addition is supposed to have no address, so a null anywhere else is this.
     */
    public function isOrphaned(): bool
    {
        return ! $this->isAddition() && $this->address_id === null;
    }

    /**
     * Someone changed the address after this request was raised, so approving it
     * would overwrite work the requester never saw.
     *
     * Only the fields the request actually proposes are compared, and only
     * against the snapshot taken when it was raised. Both narrowings matter: a
     * default toggle moves the address without touching anything an edit request
     * is about, and marking a request out of date for that would train a reader
     * to ignore the flag.
     */
    public function isStale(): bool
    {
        if ($this->type !== self::TYPE_UPDATE || $this->address === null || $this->payload === null) {
            return false;
        }

        foreach (array_keys($this->payload) as $attribute) {
            if (! array_key_exists($attribute, $this->before ?? [])) {
                continue;
            }

            // Loose on purpose: the snapshot holds a boolean as 1 and a float as
            // a string on the way back out of JSON, and both still mean equality.
            if ($this->address->getAttribute($attribute) != $this->before[$attribute]) {
                return true;
            }
        }

        return false;
    }

    /** How many addresses an import proposal carries. Zero for every other type. */
    public function rowCount(): int
    {
        return $this->type === self::TYPE_IMPORT ? count($this->payload ?? []) : 0;
    }

    /** What the address is called, for a queue row or a notification. */
    public function subjectLabel(): string
    {
        if ($this->type === self::TYPE_IMPORT) {
            $count = $this->rowCount();

            return $count.' '.Str::plural('address', $count).' from a spreadsheet';
        }

        return $this->address?->label ?? $this->before['label'] ?? $this->payload['label'] ?? 'an address';
    }

    /** What the request asks for, as a phrase: "edit an address". */
    public function actionLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CREATE => 'add an address',
            self::TYPE_UPDATE => 'edit an address',
            self::TYPE_DELETE => 'delete an address',
            self::TYPE_IMPORT => 'import '.$this->rowCount().' '.Str::plural('address', $this->rowCount()),
        };
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * A reader sees the whole queue; everybody else sees only what they asked
     * for. Same shape as Address::scopeVisibleTo(), so there is one visibility
     * rule in the app rather than two that can disagree.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->seesEveryAddress()
            ? $query
            : $query->where('address_requests.user_id', $user->id);
    }
}
