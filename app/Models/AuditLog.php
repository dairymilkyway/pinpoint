<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * An append-only record of what happened to the directory, and who did it.
 *
 * The request queue only covers what Customers asked for. An Admin edits
 * addresses directly, and without this that is the one write nobody could
 * account for - which is the half most worth recording.
 */
class AuditLog extends Model
{
    public const ADDRESS_CREATED = 'address.created';

    public const ADDRESS_UPDATED = 'address.updated';

    public const ADDRESS_DELETED = 'address.deleted';

    public const REQUEST_RAISED = 'request.raised';

    public const REQUEST_APPROVED = 'request.approved';

    public const REQUEST_REJECTED = 'request.rejected';

    /** Human wording for each event, for the log screen. */
    public const EVENT_LABELS = [
        self::ADDRESS_CREATED => 'Address created',
        self::ADDRESS_UPDATED => 'Address edited',
        self::ADDRESS_DELETED => 'Address deleted',
        self::REQUEST_RAISED => 'Request raised',
        self::REQUEST_APPROVED => 'Request approved',
        self::REQUEST_REJECTED => 'Request rejected',
    ];

    protected $fillable = [
        'actor_id',
        'event',
        'subject_type',
        'subject_id',
        'before',
        'after',
    ];

    /** Append-only. Nothing in the app writes to a row once it exists. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Write an entry, or nothing at all when there is nobody to attribute it to.
     *
     * The seeder creates fifty-odd addresses with no signed-in actor; recording
     * those would bury every real entry under seed noise in the one screen that
     * has to stay readable. The rule lives here rather than at each call site so
     * it cannot be applied in some places and forgotten in others.
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
    ): ?self {
        $actor = Auth::user();

        if ($actor === null) {
            return null;
        }

        return self::create([
            'actor_id' => $actor->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
        ]);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::EVENT_LABELS[$this->event] ?? $this->event;
    }
}
