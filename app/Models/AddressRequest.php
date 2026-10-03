<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAddition(): bool
    {
        return $this->type === self::TYPE_CREATE;
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

    /** What the address is called, for a queue row or a notification. */
    public function subjectLabel(): string
    {
        return $this->address?->label ?? $this->before['label'] ?? $this->payload['label'] ?? 'an address';
    }

    /** What the request asks for, as a phrase: "edit an address". */
    public function actionLabel(): string
    {
        return match ($this->type) {
            self::TYPE_CREATE => 'add an address',
            self::TYPE_UPDATE => 'edit an address',
            self::TYPE_DELETE => 'delete an address',
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
