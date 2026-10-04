<?php

namespace App;

use App\Geo\PhLocations;

/**
 * The landing page's hero map: every city in the bundled dataset as a point.
 *
 * Returns data, not markup - the Blade view prints the circles. Keeping the
 * projection here and the SVG there is what lets this be tested without a
 * browser, while the SVG stays with the view.
 *
 * The dataset places 1490 of the 1642 cities. The other 152 are drawn at the
 * centre of the province or region they sit in - the same stand-in the app's
 * own map already draws - and flagged so the view can render them as a hollow
 * ring rather than a filled dot. This is the one place the coordinate gap is
 * visible, so the flag is load-bearing: a stand-in drawn like a measurement
 * would make the map lie about what the project knows.
 *
 * No database, no network, no cache. One pass over the cities; PhLocations'
 * centre index memoises itself on the first city that needs one.
 */
final class ArchipelagoMap
{
    /**
     * @return array{
     *     points: list<array{x: float, y: float, approximate: bool}>,
     *     bounds: array{min_lat: float, max_lat: float, min_lng: float, max_lng: float, width: float, height: float},
     *     viewBox: string,
     *     counts: array{total: int, placed: int, approximate: int}
     * }
     */
    public static function points(int $width = 800): array
    {
        // Raw positions, before any projection: the box has to be known before
        // a point can be placed inside it.
        $raw = [];
        $counts = ['total' => 0, 'placed' => 0, 'approximate' => 0];

        // The placed set, built in one call, so deciding placed-or-not is a set
        // lookup rather than a coordinate probe per city. It is the same source
        // of truth for "has coordinates" the seeder and the map use.
        $placed = array_flip(PhLocations::seedableCityCodes());

        foreach (array_keys(PhLocations::cities()) as $code) {
            $counts['total']++;

            if (isset($placed[$code])) {
                $geo = PhLocations::coordinatesFor($code);
                $lat = $geo['lat'] ?? null;
                $lng = $geo['lng'] ?? null;

                // Counted already; a placed row with no usable position is not
                // drawn, but the total stays honest.
                if (blank($lat) || blank($lng)) {
                    continue;
                }

                $raw[] = [(float) $lat, (float) $lng, false];
                $counts['placed']++;

                continue;
            }

            $centre = PhLocations::approximateFor($code);

            // Nothing to draw it at and nothing to borrow. Not on current data,
            // but the city is still counted and simply absent, so the figures
            // on the page never claim a point the map does not carry.
            if ($centre === null) {
                continue;
            }

            $raw[] = [$centre['lat'], $centre['lng'], true];
            $counts['approximate']++;
        }

        if ($raw === []) {
            return [
                'points' => [],
                'bounds' => ['min_lat' => 0.0, 'max_lat' => 0.0, 'min_lng' => 0.0, 'max_lng' => 0.0, 'width' => 0.0, 'height' => 0.0],
                'viewBox' => '0 0 0 0',
                'counts' => $counts,
            ];
        }

        $lats = array_column($raw, 0);
        $lngs = array_column($raw, 1);

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        // Plain equirectangular, corrected for latitude. A degree of longitude
        // is cos(latitude) times a degree of latitude on the ground, so the
        // longitude span is squeezed by cos(centre latitude) before it is
        // scaled. At about 12N that is a 2.4% correction - small, but an
        // uncorrected archipelago reads visibly too wide.
        $lngScale = cos(deg2rad(($minLat + $maxLat) / 2));

        $latSpan = $maxLat - $minLat;
        $lngSpan = ($maxLng - $minLng) * $lngScale;

        // Height follows from the corrected spans, so the country keeps its
        // proportions instead of being stretched into a chosen box.
        $height = $lngSpan > 0 ? round($width * $latSpan / $lngSpan, 1) : (float) $width;

        $points = [];

        foreach ($raw as [$lat, $lng, $approximate]) {
            $points[] = [
                'x' => round($lngSpan > 0 ? ($lng - $minLng) * $lngScale / $lngSpan * $width : 0.0, 1),
                // y is flipped: north is up on the plate.
                'y' => round($latSpan > 0 ? ($maxLat - $lat) / $latSpan * $height : 0.0, 1),
                'approximate' => $approximate,
            ];
        }

        return [
            'points' => $points,
            'bounds' => [
                'min_lat' => $minLat,
                'max_lat' => $maxLat,
                'min_lng' => $minLng,
                'max_lng' => $maxLng,
                'width' => (float) $width,
                'height' => $height,
            ],
            'viewBox' => '0 0 '.$width.' '.$height,
            'counts' => $counts,
        ];
    }
}
