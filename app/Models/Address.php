<?php

namespace App\Models;

use App\Geo\PhLocations;
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
     * The attributes a city code decides, for every writer that has one.
     *
     * The bundled dataset is authoritative for everything it knows, so a caller
     * cannot pair Cebu's city code with a Manila postal code: the code decides
     * all of it. Postal is the one exception, because GeoNames has no entry for
     * some cities - Manila and Makati among them - so where it has nothing the
     * caller's value is left alone rather than blanked.
     *
     * Shared by the create form and the import so the two cannot drift into
     * accepting different pairings of the same columns.
     *
     * @return array<string, mixed>
     */
    public static function attributesFromCityCode(string $code): array
    {
        $city = PhLocations::find($code);

        if ($city === null) {
            return [];
        }

        $geo = PhLocations::coordinatesFor($city['code']) ?? [];

        $attributes = [
            'city' => $city['name'],
            'state' => PhLocations::stateFor($city),
            'country' => 'Philippines',
            'region_code' => $city['region'],
            'province_code' => $city['province'],
            'latitude' => $geo['lat'] ?? null,
            'longitude' => $geo['lng'] ?? null,
        ];

        if (filled($geo['postal'] ?? null)) {
            $attributes['postal_code'] = $geo['postal'];
        }

        return $attributes;
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
