<?php

namespace Database\Factories;

use App\Geo\PhLocations;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Street names that actually exist in the Philippines, so seeded rows read
     * like real addresses rather than faker's invented ones. The house number is
     * generated; the street is not a claim about a specific building.
     */
    private const STREETS = [
        'Rizal Avenue', 'Mabini Street', 'Bonifacio Avenue', 'Quezon Boulevard',
        'Ayala Avenue', 'Del Pilar Street', 'Katipunan Avenue', 'Aguinaldo Highway',
        'Osmena Boulevard', 'Session Road', 'Shaw Boulevard', 'J.P. Laurel Avenue',
        'Roxas Boulevard', 'Magsaysay Avenue', 'Burgos Street', 'Gomez Street',
        'Zamora Street', 'Lacson Avenue', 'Espana Boulevard', 'Ortigas Avenue',
    ];

    private const UNITS = [
        'Unit', 'Suite', 'Floor',
    ];

    public function definition(): array
    {
        // Only cities GeoNames could place on the map, so a seeded address always
        // has coordinates and the map has something to show. See
        // resources/data/README.md for what falls outside that set.
        $cityCode = fake()->randomElement(PhLocations::seedableCityCodes());
        $city = PhLocations::find($cityCode);
        $geo = PhLocations::coordinatesFor($cityCode);

        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Office', 'Warehouse', 'Billing', 'Shipping']),
            'line1' => fake()->numberBetween(1, 999).' '.fake()->randomElement(self::STREETS),
            'line2' => fake()->boolean(40)
                ? fake()->randomElement(self::UNITS).' '.fake()->numberBetween(1, 40).fake()->randomElement(['', 'A', 'B'])
                : null,
            'city' => $city['name'],
            'state' => PhLocations::stateFor($city),
            'postal_code' => $geo['postal'],
            'country' => 'Philippines',
            'region_code' => $city['region'],
            'province_code' => $city['province'],
            'city_code' => $cityCode,
            'latitude' => $geo['lat'],
            'longitude' => $geo['lng'],
            'is_default' => false,
        ];
    }
}
