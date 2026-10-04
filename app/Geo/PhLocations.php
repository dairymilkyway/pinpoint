<?php

namespace App\Geo;

use RuntimeException;

/**
 * Read-only access to the bundled Philippine Standard Geographic Code (PSGC)
 * dataset and the GeoNames coordinates matched to it.
 *
 * Both are inert JSON under resources/data. Nothing here touches the network at
 * request time, so the cascading dropdowns and the map have no API key, nothing
 * to rate-limit and nothing to bill. Each file is parsed once per process and
 * memoised.
 *
 * The two datasets do not share a key, so coordinates are matched to a city by
 * name *and* verified against its province (or region), and the result is keyed
 * by PSGC code. See resources/data/README.md for the join rules and the
 * limitations that fall out of them.
 */
final class PhLocations
{
    /**
     * Region names as they are written in an address, where the PSGC label is
     * not the one people use.
     *
     * Metro Manila is the only case. PSGC calls the capital region "National
     * Capital Region (NCR)", which is the right name for a classification and
     * the wrong one for a state field - nobody writes it on an envelope. The
     * other seventeen regions keep their own names, so the override is a map
     * with one entry rather than a rule.
     *
     * Held here rather than edited into psgc-regions.json so the snapshot stays
     * a faithful copy of the PSA release.
     */
    private const REGION_LABELS = [
        '1300000000' => 'Metro Manila',
    ];

    /** @var array<string, string>|null */
    private static ?array $regions = null;

    /** @var array<string, string>|null */
    private static ?array $provinces = null;

    /** @var array<string, string>|null */
    private static ?array $provinceRegion = null;

    /** @var array<string, array{code: string, name: string, region: ?string, province: ?string, class: string}>|null */
    private static ?array $cities = null;

    /**
     * lat and lng are optional in this shape even though every row carries them
     * today: the file is hand-maintained, and a row written without a measured
     * position is legal. Ask positionAt() for a point rather than testing
     * whether the row exists.
     *
     * @var array<string, array{postal: string, place: string, lat?: string, lng?: string}>|null
     */
    private static ?array $coordinates = null;

    /** @var array<string, list<string>>|null normalized official name => city codes */
    private static ?array $citiesByExactName = null;

    /** @var array<string, list<string>>|null normalized name with its City wrapper removed => city codes */
    private static ?array $citiesByShortName = null;

    /** @var array<string, array{lat: float, lng: float}>|null province code => centre of the cities in it that GeoNames placed */
    private static ?array $provinceCentres = null;

    /** @var array<string, array{lat: float, lng: float}>|null region code => centre of the cities in it that GeoNames placed */
    private static ?array $regionCentres = null;

    /** @return array<string, string> region code => name */
    public static function regions(): array
    {
        if (self::$regions === null) {
            self::$regions = [];
            foreach (self::read('psgc-regions.json') as $row) {
                self::$regions[$row['code']] = $row['name'];
            }
        }

        return self::$regions;
    }

    /** @return array<string, string> province code => name */
    public static function provinces(): array
    {
        if (self::$provinces === null) {
            self::$provinces = [];
            self::$provinceRegion = [];
            foreach (self::read('psgc-provinces.json') as $row) {
                self::$provinces[$row['code']] = $row['name'];
                self::$provinceRegion[$row['code']] = $row['region'];
            }
        }

        return self::$provinces;
    }

    /**
     * @return array<string, array{code: string, name: string, region: ?string, province: ?string, class: string}>
     */
    public static function cities(): array
    {
        if (self::$cities === null) {
            self::$cities = [];
            foreach (self::read('psgc-cities.json') as $row) {
                // The code is repeated inside the entry as well as being the key,
                // so a caller holding one city does not have to carry the code
                // around separately.
                self::$cities[$row['code']] = [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'region' => $row['region'],
                    'province' => $row['province'],
                    'class' => $row['class'],
                ];
            }
        }

        return self::$cities;
    }

    public static function find(string $cityCode): ?array
    {
        return self::cities()[$cityCode] ?? null;
    }

    /** Postal code and coordinates for a city, when GeoNames confirmed a row. */
    public static function coordinatesFor(?string $cityCode): ?array
    {
        return $cityCode === null ? null : (self::coordinateMap()[$cityCode] ?? null);
    }

    /**
     * The row's position, or null when it holds none.
     *
     * A geo row is allowed to carry a postal code and no coordinates. No bundled
     * row is shaped that way today - the last two, Taguig's and Pateros', were
     * placed from the gazetteer - but the file is hand-edited and this is what
     * keeps such a row from being read as "placed". So callers that need a point
     * must ask here rather than testing whether the row exists at all.
     *
     * @param  array<string, mixed>|null  $geo
     * @return array{lat: float, lng: float}|null
     */
    private static function positionAt(?array $geo): ?array
    {
        if ($geo === null || blank($geo['lat'] ?? null) || blank($geo['lng'] ?? null)) {
            return null;
        }

        return ['lat' => (float) $geo['lat'], 'lng' => (float) $geo['lng']];
    }

    /** Provinces belonging to a region, ready for a <select>. */
    public static function provincesIn(?string $regionCode): array
    {
        self::provinces();

        return array_filter(
            self::$provinces,
            fn (string $code) => self::$provinceRegion[$code] === $regionCode,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Cities in a region, narrowed to a province when one is given.
     *
     * A null province is meaningful rather than "unfiltered": cities that sit
     * directly under their region - the HUCs and Metro Manila - carry no
     * province, and those are exactly the ones this returns.
     */
    public static function citiesIn(?string $regionCode, ?string $provinceCode): array
    {
        $out = [];

        foreach (self::cities() as $code => $city) {
            if ($city['region'] !== $regionCode || $city['province'] !== $provinceCode) {
                continue;
            }
            $out[$code] = $city['name'];
        }

        asort($out);

        return $out;
    }

    /** Cities that have coordinates, for seeding and for map pins. */
    public static function seedableCityCodes(): array
    {
        // Rows that hold a position, not merely rows that exist. A postal-only
        // row is a real row, and a caller that asked for a city it can pin would
        // otherwise be handed one it cannot.
        $placed = array_filter(
            self::coordinateMap(),
            fn (array $geo) => self::positionAt($geo) !== null,
        );

        return array_keys(array_intersect_key(self::cities(), $placed));
    }

    /**
     * Where to draw a city that has no coordinates of its own: the centre of the
     * province it sits in, or failing that its region.
     *
     * This is not a position for the city and must never be stored as one. It is
     * the middle of a larger place the city is known to be inside, averaged from
     * the cities in that place that GeoNames *did* place - so it is a stand-in
     * that can be drawn, not a measurement. Callers are expected to mark it as
     * approximate wherever a person can see it.
     *
     * Deliberately not "the nearest known place": nothing here knows where a
     * city without coordinates is, so there is no distance to measure from. The
     * coarser parent is the only thing that is actually known.
     *
     * Null when the city is not in the dataset, when it already has coordinates,
     * or when nothing in its province or region was placed either.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function approximateFor(?string $cityCode): ?array
    {
        // Keyed off whether the city has coordinates, not whether it has a row.
        // A row may carry only a postal code, and reading the row's presence as
        // "placed" would drop that city off the map instead of drawing it at the
        // centre it borrows.
        if ($cityCode === null || self::positionAt(self::coordinatesFor($cityCode)) !== null) {
            return null;
        }

        $city = self::find($cityCode);

        if ($city === null) {
            return null;
        }

        self::indexCentres();

        $centre = $city['province'] !== null
            ? (self::$provinceCentres[$city['province']] ?? null)
            : null;

        return $centre ?? self::$regionCentres[$city['region']] ?? null;
    }

    /**
     * Averages each province and each region from the cities inside it that have
     * coordinates. Built once, on the first city that needs one.
     */
    private static function indexCentres(): void
    {
        if (self::$provinceCentres !== null) {
            return;
        }

        self::$provinceCentres = [];
        self::$regionCentres = [];

        // province or region code => [lat sum, lng sum, how many]
        $provinces = [];
        $regions = [];

        foreach (self::cities() as $code => $city) {
            // A row without coordinates contributes nothing to average. Reading
            // a missing lat as 0.0 would drag whichever centre the row belongs
            // to far off the archipelago.
            $position = self::positionAt(self::coordinatesFor($code));

            if ($position === null) {
                continue;
            }

            $lat = $position['lat'];
            $lng = $position['lng'];

            if (filled($city['province'])) {
                $provinces[$city['province']] ??= [0.0, 0.0, 0];
                $provinces[$city['province']][0] += $lat;
                $provinces[$city['province']][1] += $lng;
                $provinces[$city['province']][2]++;
            }

            if (filled($city['region'])) {
                $regions[$city['region']] ??= [0.0, 0.0, 0];
                $regions[$city['region']][0] += $lat;
                $regions[$city['region']][1] += $lng;
                $regions[$city['region']][2]++;
            }
        }

        foreach ($provinces as $key => [$lat, $lng, $count]) {
            self::$provinceCentres[$key] = ['lat' => $lat / $count, 'lng' => $lng / $count];
        }

        foreach ($regions as $key => [$lat, $lng, $count]) {
            self::$regionCentres[$key] = ['lat' => $lat / $count, 'lng' => $lng / $count];
        }
    }

    public static function regionName(?string $code): ?string
    {
        return $code === null ? null : (self::regions()[$code] ?? null);
    }

    public static function provinceName(?string $code): ?string
    {
        return $code === null ? null : (self::provinces()[$code] ?? null);
    }

    public static function cityName(?string $code): ?string
    {
        return $code === null ? null : (self::cities()[$code]['name'] ?? null);
    }

    /**
     * The cities a name could mean, keyed by code - for a caller holding what
     * somebody typed rather than the ten-digit key.
     *
     * The dataset carries the official form, "City of Manila" and "Quezon City",
     * which is not how anyone writes a city in a spreadsheet, so the comparison
     * ignores case and the tilde. Failing that it ignores the wrapper too. The
     * official form is tried first and wins outright: "Quezon City" is one city,
     * while the stripped form alone would also collect the six municipalities
     * called Quezon.
     *
     * An empty array means the dataset has no such city. More than one entry
     * means the name is real but belongs to several places - 114 names here do -
     * and resolving that is the caller's business, not a silent pick.
     *
     * A qualifier narrows the result to the province, or failing that the region,
     * a reader named alongside it. It cannot widen a result: a name the dataset
     * does not have stays absent however it is qualified.
     *
     * @return array<string, array{code: string, name: string, region: ?string, province: ?string, class: string}>
     */
    public static function citiesNamed(string $name, ?string $qualifier = null): array
    {
        $wanted = self::normalizePlaceName($name);

        if ($wanted === '') {
            return [];
        }

        self::indexNames();

        // Both sides of the comparison lose the wrapper: the dataset name was
        // indexed under its short form, and the name that came in may carry the
        // wrapper in the other order - "Cebu City" against "City of Cebu".
        $exact = self::$citiesByExactName[$wanted] ?? [];
        $short = self::$citiesByShortName[self::normalizePlaceName(self::stripCityAffix($name))] ?? [];

        // Unqualified, the official form wins outright, so "Quezon City" stays
        // one city instead of joining the six municipalities called Quezon.
        // Qualified, both pools are in play, because the qualifier is what does
        // the choosing: "San Juan, National Capital Region" means the City of
        // San Juan, which only the short form carries.
        $codes = $qualifier === null
            ? ($exact !== [] ? $exact : $short)
            : array_values(array_unique([...$exact, ...$short]));

        $out = [];

        foreach ($codes as $code) {
            $city = self::cities()[$code];

            if ($qualifier === null || self::isInPlace($city, $qualifier)) {
                $out[$code] = $city;
            }
        }

        return $out;
    }

    /**
     * A region name as it would be written in an address.
     *
     * The full PSGC form is a classification label - "Region VII (Central
     * Visayas)" - which reads badly in a state field. Drop the numbering and the
     * trailing abbreviation, keeping the part people actually use.
     */
    public static function regionLabel(?string $code): ?string
    {
        return self::REGION_LABELS[$code] ?? self::psgcRegionLabel($code);
    }

    /** The same label straight off the dataset, override or no override. */
    private static function psgcRegionLabel(?string $code): ?string
    {
        $name = self::regionName($code);

        if ($name === null) {
            return null;
        }

        // PSGC brackets the friendly name far more often than not: "Region I
        // (Ilocos Region)". The bracket is only an abbreviation in a few cases -
        // "National Capital Region (NCR)" - so prefer the bracket unless it is
        // short and all-caps.
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/', $name, $m)) {
            $inner = trim($m[2]);
            $isAbbreviation = strlen($inner) <= 5 && preg_match('/^[A-Z]+$/', $inner) === 1;

            return $isAbbreviation ? (trim($m[1]) ?: null) : $inner;
        }

        return trim($name) ?: null;
    }

    /**
     * What belongs in the address `state` field: the province where the city has
     * one, otherwise the region - which is how addresses in Metro Manila and the
     * independent cities are actually written.
     */
    public static function stateFor(array $city): ?string
    {
        return self::provinceName($city['province']) ?? self::regionLabel($city['region']);
    }

    /**
     * Builds both name indexes once. Two of them, not one, because the official
     * form has to outrank the shortened one - see citiesNamed().
     */
    private static function indexNames(): void
    {
        if (self::$citiesByExactName !== null) {
            return;
        }

        self::$citiesByExactName = [];
        self::$citiesByShortName = [];

        foreach (self::cities() as $code => $city) {
            self::$citiesByExactName[self::normalizePlaceName($city['name'])][] = $code;

            $short = self::normalizePlaceName(self::stripCityAffix($city['name']));

            if ($short !== '') {
                self::$citiesByShortName[$short][] = $code;
            }
        }
    }

    /** Whether a city sits in the province, or failing that the region, named. */
    private static function isInPlace(array $city, string $qualifier): bool
    {
        $wanted = self::normalizePlaceName($qualifier);

        $labels = [
            self::stateFor($city),
            self::provinceName($city['province']),
            self::regionLabel($city['region']),
            // Both halves of a rename: the override is what the app writes, but
            // a reader who types what PSGC calls the region must still land on
            // it. Metro Manila is the case that makes this matter.
            self::psgcRegionLabel($city['region']),
        ];

        foreach ($labels as $label) {
            if ($label !== null && self::normalizePlaceName($label) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /** The official name with the "City of " / " City" wrapper removed. */
    private static function stripCityAffix(string $name): string
    {
        $name = preg_replace('/^city of\s+/i', '', trim($name));

        return (string) preg_replace('/\s+city$/i', '', (string) $name);
    }

    /**
     * A place name reduced to what a reader would type: lower case, no accents,
     * single spaces.
     *
     * The tilde is the only accent in 1642 city names, 18 of them, so the map is
     * written out rather than transliterating the whole Unicode range for one
     * character.
     */
    private static function normalizePlaceName(string $name): string
    {
        $name = str_replace(['ñ', 'Ñ'], 'n', $name);
        $name = mb_strtolower(trim($name), 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    /** @return array<string, array{postal: string, lat: string, lng: string, place: string}> */
    private static function coordinateMap(): array
    {
        if (self::$coordinates === null) {
            self::$coordinates = [];
            foreach (self::read('geo-by-city.json') as $code => $row) {
                self::$coordinates[(string) $code] = $row;
            }
        }

        return self::$coordinates;
    }

    /** @return list<array<string, mixed>> */
    private static function read(string $file): array
    {
        $path = dirname(__DIR__, 2).'/resources/data/'.$file;

        if (! is_file($path)) {
            throw new RuntimeException("Bundled geo dataset missing: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Bundled geo dataset is not valid JSON: {$path}");
        }

        return $decoded;
    }
}
