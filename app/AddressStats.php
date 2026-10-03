<?php

namespace App;

use App\Geo\PhLocations;
use App\Models\Address;
use Illuminate\Database\Eloquent\Builder;

/**
 * The figures the dashboard and a single user's profile page both report.
 *
 * Each method takes a query the caller has already scoped, so the two pages
 * cannot drift into showing different numbers for the same rows: the profile
 * page hands in one owner's rows, the dashboard hands in everything the reader
 * can reach.
 */
final class AddressStats
{
    /**
     * @return array{addresses: int, cities: int, regions: int}
     */
    public static function counts(Builder $query): array
    {
        return [
            'addresses' => (clone $query)->count(),
            'cities' => (clone $query)->whereNotNull('city_code')->distinct()->count('city_code'),
            'regions' => (clone $query)->whereNotNull('region_code')->distinct()->count('region_code'),
        ];
    }

    /**
     * How much of what was handed in can be pinned. Counted in SQL rather than
     * by loading every row, since this is the only thing the pages need from
     * them.
     *
     * @return array{pinned: int, total: int, percent: int}
     */
    public static function coverage(Builder $query): array
    {
        $total = (clone $query)->count();
        $pinned = (clone $query)->whereNotNull('latitude')->whereNotNull('longitude')->count();

        return [
            'pinned' => $pinned,
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) round($pinned / $total * 100),
        ];
    }

    /**
     * The region distribution, ranked longest first.
     *
     * Grouped on region_code rather than the `state` column: state holds the
     * province ("Kalinga"), not the region it belongs to.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function regions(Builder $query): array
    {
        $counts = (clone $query)
            ->whereNotNull('region_code')
            ->selectRaw('region_code, count(*) as total')
            ->groupBy('region_code')
            ->pluck('total', 'region_code');

        // Label ties broken alphabetically, so the bar order is stable between
        // two requests rather than whatever the database returns.
        return $counts
            ->map(fn ($total, $code) => [
                'label' => PhLocations::regionLabel((string) $code) ?? (string) $code,
                'value' => (int) $total,
            ])
            ->sortBy([['value', 'desc'], ['label', 'asc']])
            ->values()
            ->all();
    }

    /**
     * The pin payload the map module reads. One definition of the shape, since
     * the page and the endpoint have to agree on it field for field.
     *
     * @param  bool  $withOwner  Named on each pin only where there is more than
     *                           one owner on the map to tell apart.
     * @return array<int, array{label: string, line: string, city: string|null, state: string|null, postal: string|null, owner: string|null, lat: float|string|null, lng: float|string|null}>
     */
    public static function mapPoints(Builder $query, bool $withOwner): array
    {
        $columns = ['label', 'line1', 'line2', 'city', 'state', 'postal_code', 'latitude', 'longitude'];

        if ($withOwner) {
            $columns[] = 'user_id';
        }

        return (clone $query)
            ->when($withOwner, fn (Builder $q) => $q->with('user:id,name'))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get($columns)
            ->map(fn (Address $address) => [
                'label' => $address->label,
                'line' => trim($address->line1.($address->line2 ? ', '.$address->line2 : '')),
                'city' => $address->city,
                'state' => $address->state,
                'postal' => $address->postal_code,
                'owner' => $withOwner ? $address->user?->name : null,
                'lat' => $address->latitude,
                'lng' => $address->longitude,
            ])
            ->all();
    }
}
