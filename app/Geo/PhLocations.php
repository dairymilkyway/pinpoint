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
    /** @var array<string, string>|null */
    private static ?array $regions = null;

    /** @var array<string, string>|null */
    private static ?array $provinces = null;

    /** @var array<string, string>|null */
    private static ?array $provinceRegion = null;

    /** @var array<string, array{code: string, name: string, region: ?string, province: ?string, class: string}>|null */
    private static ?array $cities = null;

    /** @var array<string, array{postal: string, lat: string, lng: string, place: string}>|null */
    private static ?array $coordinates = null;

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
        return array_keys(array_intersect_key(self::cities(), self::coordinateMap()));
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
     * A region name as it would be written in an address.
     *
     * The full PSGC form is a classification label - "Region VII (Central
     * Visayas)" - which reads badly in a state field. Drop the numbering and the
     * trailing abbreviation, keeping the part people actually use.
     */
    public static function regionLabel(?string $code): ?string
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
        $path = dirname(__DIR__, 2) . '/resources/data/' . $file;

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
