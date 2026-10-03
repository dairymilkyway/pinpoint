<?php

namespace App\Models;

use App\Observers\AddressObserver;
use Database\Factories\AddressFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(AddressObserver::class)]
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    /**
     * The attributes that count as the address's content, in the order they read
     * on a form.
     *
     * user_id is deliberately absent: ownership is not editable, so a change of
     * owner is not an edit and must not show up in the audit log as one. Both
     * the audit diff and the request snapshot are cut from this list, so the two
     * cannot disagree about what "the address changed" means.
     */
    public const TRACKED = [
        'label',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'region_code',
        'province_code',
        'city_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $fillable = [
        'user_id',
        'label',
        'line1',
        'line2',
        'city',
        'state',
        'postal_code',
        'country',
        'region_code',
        'province_code',
        'city_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * The tracked attributes as they stand. Used for the snapshot a request
     * carries and for the before/after an audit entry carries, so both describe
     * the address in the same terms.
     *
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $snapshot = [];

        foreach (self::TRACKED as $attribute) {
            $snapshot[$attribute] = $this->getAttribute($attribute);
        }

        return $snapshot;
    }

    /**
     * Narrows a query to the addresses the user may see. Every read path - the
     * table, its export, the map and the dashboard figures - goes through here,
     * so the rule is stated once instead of per call site.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->seesEveryAddress()
            ? $query
            : $query->where('addresses.user_id', $user->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
