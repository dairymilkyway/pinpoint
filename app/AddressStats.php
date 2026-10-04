<?php

namespace App;

use App\Geo\PhLocations;
use App\Models\Address;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
     * The cumulative addresses on file, one point per week, oldest first.
     *
     * Each value is the running total at the end of that week, so the last point
     * equals counts($query)['addresses'] - the sparkline and the figure beside it
     * cannot disagree. Reads created_at off the SAME scoped query the caller
     * handed in.
     *
     * Deliberately not grouped in SQL: there is no date-bucketing expression both
     * MariaDB and SQLite accept, and the app has to run on both. So the week
     * boundaries are built first in PHP, and each row is looked up in that array
     * by its own start-of-week date rather than doing week arithmetic on a
     * timestamp - diffInWeeks and a raw / 604800 both carry Carbon-version and
     * DST traps that a keyed lookup does not. Bucketing startOfWeek() on both the
     * boundary and the row is what keeps the two from disagreeing about which day
     * a week starts.
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function trend(Builder $query, int $weeks = 12): array
    {
        $weeks = max(1, $weeks);

        $firstWeek = now()->startOfWeek()->subWeeks($weeks - 1);

        // One bucket per week, oldest first, keyed by its start-of-week date so a
        // row is matched by key rather than by timestamp comparison.
        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $buckets[$firstWeek->copy()->addWeeks($i)->toDateString()] = 0;
        }

        // Everything already on file before the window opens, as the running
        // total's starting value.
        $running = (clone $query)->where('created_at', '<', $firstWeek)->count();

        // One column, one query over the window; the bucketing happens in PHP.
        (clone $query)
            ->where('created_at', '>=', $firstWeek)
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$buckets): void {
                $key = Carbon::parse($createdAt)->startOfWeek()->toDateString();
                if (array_key_exists($key, $buckets)) {
                    $buckets[$key]++;
                }
            });

        $points = [];
        foreach ($buckets as $week => $count) {
            $running += $count;
            $points[] = [
                'label' => Carbon::parse($week)->format('j M'),
                'value' => $running,
            ];
        }

        return $points;
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
     * A city the coordinate dataset does not carry is drawn at the centre of the
     * province or region it sits in, and flagged, so the map has no silent holes
     * without any invented number reaching the database. The flag is what lets
     * the map draw the difference; a stand-in that looked like a measurement
     * would be worse than no pin. The stored coordinates stay honest, which is
     * also why the coverage figures above the map are unchanged by this.
     *
     * The region field is the LABEL, produced through the same helper and the
     * same `?? (string) $code` fallback regions() uses, so a pin and its bar join
     * on an identical string even for a region the dataset does not know. It is
     * deliberately not `state`, which is the province.
     *
     * @param  bool  $withOwner  Named on each pin only where there is more than
     *                           one owner on the map to tell apart.
     * @return array<int, array{label: string, line: string, city: string|null, state: string|null, region: string|null, postal: string|null, owner: string|null, lat: float|string, lng: float|string, approximate: bool}>
     */
    public static function mapPoints(Builder $query, bool $withOwner): array
    {
        $columns = ['label', 'line1', 'line2', 'city', 'state', 'region_code', 'postal_code', 'latitude', 'longitude', 'city_code'];

        if ($withOwner) {
            $columns[] = 'user_id';
        }

        return (clone $query)
            ->when($withOwner, fn (Builder $q) => $q->with('user:id,name'))
            ->get($columns)
            ->map(function (Address $address) use ($withOwner): ?array {
                $pinned = $address->hasCoordinates();

                $position = $pinned
                    ? ['lat' => $address->latitude, 'lng' => $address->longitude]
                    : PhLocations::approximateFor($address->city_code);

                // Nothing to draw it at and nothing to approximate from: a
                // free-text address, or a city with no placed neighbours. It is
                // absent from the map, which the table now says outright.
                if ($position === null) {
                    return null;
                }

                return [
                    'label' => $address->label,
                    'line' => trim($address->line1.($address->line2 ? ', '.$address->line2 : '')),
                    'city' => $address->city,
                    'state' => $address->state,
                    'region' => PhLocations::regionLabel((string) $address->region_code) ?? (string) $address->region_code,
                    'postal' => $address->postal_code,
                    'owner' => $withOwner ? $address->user?->name : null,
                    'lat' => $position['lat'],
                    'lng' => $position['lng'],
                    'approximate' => ! $pinned,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
