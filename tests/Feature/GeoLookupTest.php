<?php

namespace Tests\Feature;

use App\Geo\PhLocations;
use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('Superadmin');
    }

    /** Readers own nothing, so a creation has to name the account it is for. */
    private function customer(): User
    {
        return User::factory()->create()->assignRole('Customer');
    }

    private function cityCodeNamed(string $name): string
    {
        foreach (PhLocations::cities() as $code => $city) {
            if ($city['name'] === $name) {
                return $code;
            }
        }

        $this->fail("No city named {$name} in the bundled dataset.");
    }

    public function test_the_city_list_requires_authentication(): void
    {
        $this->get(route('geo.cities', ['region' => '1300000000']))
            ->assertRedirect(route('login'));
    }

    public function test_the_city_list_returns_cities_for_a_region(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->getJson(route('geo.cities', ['region' => '1300000000']))
            ->assertOk()
            ->json();

        // Metro Manila has no provinces, so its cities hang off the region.
        $this->assertContains('City of Manila', array_column($response, 'name'));
    }

    public function test_the_city_list_is_narrowed_by_province(): void
    {
        $response = $this->actingAs($this->superadmin())
            ->getJson(route('geo.cities', ['region' => '0700000000', 'province' => '0702200000']))
            ->assertOk()
            ->json();

        $names = array_column($response, 'name');

        // Alcantara is a municipality of Cebu province...
        $this->assertContains('Alcantara', $names);
        // ...while City of Cebu is an independent city that hangs off the region,
        // not off the province, so it must not appear in the province's list.
        $this->assertNotContains('City of Cebu', $names);
    }

    public function test_an_unknown_region_code_is_rejected(): void
    {
        $this->actingAs($this->superadmin())
            ->getJson(route('geo.cities', ['region' => '9999999999']))
            ->assertStatus(422);
    }

    public function test_an_unknown_province_code_is_rejected(): void
    {
        $this->actingAs($this->superadmin())
            ->getJson(route('geo.cities', ['province' => '9999999999']))
            ->assertStatus(422);
    }

    public function test_storing_an_address_with_a_city_code_derives_the_rest(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->customer();

        $this->actingAs($superadmin)
            ->post(route('addresses.store', ['user' => $customer->id]), [
                'label' => 'Head Office',
                'line1' => '1 Test Street',
                'city_code' => $this->cityCodeNamed('City of Cebu'),
                // Supplied values that contradict the city code, to prove the
                // dataset wins rather than the client.
                'city' => 'Nowhere',
                'state' => 'Nowhere',
                'postal_code' => '0000',
                'country' => 'Atlantis',
            ])
            // A reader of the whole book lands on the owner's page, so the
            // address they just made is on screen.
            ->assertRedirect(route('addresses.user', $customer))
            ->assertSessionHasNoErrors();

        $address = Address::sole();

        $this->assertSame('City of Cebu', $address->city);
        $this->assertSame('Central Visayas', $address->state);
        $this->assertSame('Philippines', $address->country);
        $this->assertSame('6000', $address->postal_code);
        $this->assertSame('0700000000', $address->region_code);
        $this->assertNull($address->province_code);
        $this->assertNotNull($address->latitude);
        $this->assertNotNull($address->longitude);
    }

    /**
     * GeoNames keys Metro Manila by postal district and never by city, so the
     * name-plus-parent join finds nothing and an address in the capital ends up
     * with no coordinates - and therefore no pin. Each city's own central post
     * office is the anchor that closes it.
     */
    public function test_a_metropolitan_city_is_pinned_from_its_own_post_office(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)
            ->post(route('addresses.store', ['user' => $this->customer()->id]), [
                'label' => 'Head Office',
                'line1' => '1 Roxas Boulevard',
                'city_code' => $this->cityCodeNamed('City of Manila'),
            ])
            ->assertSessionHasNoErrors();

        $address = Address::sole();

        $this->assertSame('City of Manila', $address->city);
        // The postal code travels with the coordinates, and 1000 is Manila's.
        $this->assertSame('1000', $address->postal_code);
        $this->assertNotNull($address->latitude);
        $this->assertNotNull($address->longitude);

        $points = $this->actingAs($superadmin)->getJson(route('home.map'))->assertOk()->json();

        $this->assertCount(1, $points);
        $this->assertSame('Head Office', $points[0]['label']);
    }

    public function test_a_city_in_a_province_records_that_province(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('addresses.store', ['user' => $this->customer()->id]), [
                'label' => 'Branch',
                'line1' => '2 Test Street',
                'city_code' => $this->cityCodeNamed('City of Vigan'),
            ])
            ->assertSessionHasNoErrors();

        $address = Address::sole();

        $this->assertSame('Ilocos Sur', $address->state);
        $this->assertSame(
            array_search('Ilocos Sur', PhLocations::provinces(), true),
            $address->province_code,
        );
        $this->assertNotNull($address->latitude);
    }

    public function test_an_unknown_city_code_is_rejected(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('addresses.store'), [
                'label' => 'Nowhere',
                'line1' => '3 Test Street',
                'city_code' => '9999999999',
                'postal_code' => '1000',
                'country' => 'Philippines',
            ])
            ->assertSessionHasErrors('city_code');

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_the_map_only_returns_addresses_that_can_be_pinned(): void
    {
        $superadmin = $this->superadmin();

        Address::factory()->create([
            'user_id' => $superadmin->id,
            'latitude' => 10.3167,
            'longitude' => 123.8907,
        ]);

        Address::factory()->create([
            'user_id' => $superadmin->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        // The dashboard map is the only one left; the addresses page no longer
        // carries a map panel, so these follow the pinning rule where it lives.
        $points = $this->actingAs($superadmin)
            ->getJson(route('home.map'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $points);
        $this->assertSame(10.3167, $points[0]['lat']);
    }

    public function test_the_map_requires_authentication(): void
    {
        $this->get(route('home.map'))->assertRedirect(route('login'));
    }

    /**
     * The fallback for the documented gap: 152 of the 1642 cities have no
     * coordinates, so a row in one of them is drawn at the centre of the
     * province or region it is known to sit in.
     */
    public function test_a_city_the_dataset_cannot_place_borrows_its_regions_centre(): void
    {
        // General Santos is a highly urbanised city with no province, so the
        // region is the only coarser place there is to borrow from - the shape
        // the capital's cities used to have before every one of them was pinned.
        $centre = PhLocations::approximateFor('1230800000');

        $this->assertNotNull($centre);

        // In SOCCSKSARGEN, and nowhere near the city itself. This is what keeps
        // the fallback an approximation rather than a position: if a real one is
        // ever added, this test should be the thing that notices.
        $this->assertGreaterThan(6.0, $centre['lat']);
        $this->assertLessThan(7.5, $centre['lat']);
        $this->assertGreaterThan(124.0, $centre['lng']);
        $this->assertLessThan(125.5, $centre['lng']);
    }

    public function test_a_city_that_was_placed_is_never_approximated(): void
    {
        // The fallback exists for the cities that have none, not as a substitute
        // for the 1490 that do. Makati was anchored to its own post office;
        // Taguig and Pateros were not anchored at all until the gazetteer placed
        // them, so both halves of how the capital got here are checked.
        foreach (['1380300000', '1381500000', '1381701000'] as $code) {
            $this->assertNotNull(PhLocations::coordinatesFor($code));
            $this->assertNull(PhLocations::approximateFor($code));
        }
    }

    /**
     * The capital region is the one place PSGC's own label is not what an
     * address says. Its cities carry no province, so the region name is what
     * lands in the state field - and that field is read by people, not by the
     * classification.
     */
    public function test_the_capital_region_is_labelled_the_way_an_address_writes_it(): void
    {
        $this->assertSame('Metro Manila', PhLocations::regionLabel('1300000000'));

        // The label reaches the state field through the same call the form and
        // the importer both make.
        $manila = PhLocations::find($this->cityCodeNamed('City of Manila'));

        $this->assertSame('Metro Manila', PhLocations::stateFor($manila));
    }

    /**
     * The override is a map with one entry, and this is what keeps it one: a
     * second region renamed by accident would otherwise pass unnoticed.
     */
    public function test_no_region_but_the_capital_is_renamed(): void
    {
        $renamed = [];

        foreach (PhLocations::regions() as $code => $name) {
            $label = PhLocations::regionLabel($code);

            $this->assertNotNull($label, "{$name} has no label an address could carry");

            if ($label === 'Metro Manila') {
                // The dataset key arrives as an int, so it is cast back to the
                // ten-digit form the codes are written in everywhere else.
                $renamed[] = (string) $code;
            }
        }

        $this->assertSame(['1300000000'], $renamed);
    }

    /**
     * Renaming a region must not make the old name unsearchable. A reader who
     * types what PSGC calls it has to land on the same city as one who types
     * what the post office calls it.
     */
    public function test_the_capital_region_answers_to_both_of_its_names(): void
    {
        foreach (['Metro Manila', 'National Capital Region'] as $qualifier) {
            $matches = PhLocations::citiesNamed('San Juan', $qualifier);

            $this->assertCount(1, $matches, "\"{$qualifier}\" should name one City of San Juan");
            $this->assertSame('City of San Juan', reset($matches)['name']);
        }
    }

    /**
     * No city in the capital borrows a centre any more. All seventeen have a row
     * of their own carrying a position: fifteen anchored to their own central
     * post office, and Taguig and Pateros from the gazetteer, because the postal
     * file the join consumes names neither.
     *
     * Written over the whole region rather than over one city, since the gap
     * this closes was regional - every city in the capital was once unpinned -
     * and a per-city test would have missed the next one to fall out.
     */
    public function test_every_city_in_the_capital_has_a_position_of_its_own(): void
    {
        $cities = PhLocations::citiesIn('1300000000', null);

        $this->assertCount(17, $cities, 'the capital should have seventeen cities');

        foreach ($cities as $code => $name) {
            $geo = PhLocations::coordinatesFor((string) $code);

            $this->assertNotNull($geo, "{$name} should have a row of its own");
            $this->assertArrayHasKey('lat', $geo, "{$name} should carry a latitude");
            $this->assertArrayHasKey('lng', $geo, "{$name} should carry a longitude");
            $this->assertNotEmpty($geo['postal'] ?? null, "{$name} should carry a postal code");
            $this->assertNull(
                PhLocations::approximateFor((string) $code),
                "{$name} has a position of its own and should not borrow one",
            );
        }
    }

    /**
     * Pateros is the same gap reached from the other side. The postal file has
     * no Pateros row either, for the same district-keyed reason - but GeoNames'
     * gazetteer does place the town, so this row carries a position and the
     * postal code is the half that has to come from PHLPost instead.
     *
     * Placed means drawn as itself: no borrowed centre, and a pin the coverage
     * figure is right to count. That is the opposite of Taguig's row above, and
     * the reason the two are shaped differently.
     */
    public function test_a_city_the_postal_file_cannot_reach_can_still_be_placed(): void
    {
        $geo = PhLocations::coordinatesFor('1381701000');

        $this->assertNotNull($geo, 'Pateros should have a row of its own');
        $this->assertSame('1620', $geo['postal']);
        $this->assertArrayHasKey('lat', $geo);
        $this->assertArrayHasKey('lng', $geo);

        $this->assertNull(PhLocations::approximateFor('1381701000'));

        // And it reaches the address fields through the one path that writes
        // them, so a Pateros address ends up pinned and carrying its postal code.
        $attributes = Address::attributesFromCityCode('1381701000');

        $this->assertSame('1620', $attributes['postal_code']);
        // The row holds coordinates as strings, the way the JSON does; the
        // column is decimal, so compare the number rather than the spelling.
        $this->assertEqualsWithDelta(14.5391, (float) $attributes['latitude'], 0.0001);
        $this->assertSame('Metro Manila', $attributes['state']);
    }

    /**
     * Every code offered to the seeder has to be one the map can pin. The
     * factory writes a latitude straight off the row it is handed, so a row
     * without one would give it nothing to write - and because the factory picks
     * at random, that failure lands on whichever unrelated test happens to run
     * next rather than on this one.
     *
     * No bundled row is in that shape today, which is why this is written as the
     * contract rather than as a check on one city: it is what makes hand-adding
     * a coordinate-less row to geo-by-city.json safe.
     */
    public function test_only_cities_with_a_position_are_offered_for_seeding(): void
    {
        // The codes come back as ints, being array keys; cast so the comparisons
        // below are against the ten-digit form the dataset documents.
        $codes = array_map('strval', PhLocations::seedableCityCodes());

        foreach ($codes as $code) {
            $geo = PhLocations::coordinatesFor($code);

            $this->assertNotNull($geo, "{$code} was offered with no row at all");
            $this->assertArrayHasKey('lat', $geo, "{$code} was offered with no latitude");
            $this->assertArrayHasKey('lng', $geo, "{$code} was offered with no longitude");
        }

        // Guards the loop against being vacuous - an empty list would otherwise
        // pass this test by never running it.
        $this->assertNotEmpty($codes);
    }

    public function test_there_is_nothing_to_approximate_without_a_known_city(): void
    {
        // A code the dataset has never heard of, and the free-text path, which
        // carries no code at all. Neither can borrow a centre, and the fallback
        // must not become a licence to invent a position for anything.
        $this->assertNull(PhLocations::approximateFor(null));
        $this->assertNull(PhLocations::approximateFor('9999999999'));
    }

    public function test_every_city_without_coordinates_can_still_be_drawn(): void
    {
        $unplaced = 0;

        foreach (PhLocations::cities() as $code => $city) {
            // Asks whether the city is placed, not whether the dataset mentions
            // it. No bundled row is written without a position today, but the
            // file is hand-edited and skipping on the row alone would quietly
            // drop the first one that was.
            $geo = PhLocations::coordinatesFor($code);

            if (filled($geo['lat'] ?? null) && filled($geo['lng'] ?? null)) {
                continue;
            }

            $unplaced++;

            $this->assertNotNull(
                PhLocations::approximateFor($code),
                "{$city['name']} ({$code}) has no coordinates and nothing to borrow from",
            );
        }

        // Guards the loop against being vacuous: a fetch that returned nothing
        // would otherwise pass this test by never running it.
        $this->assertGreaterThan(0, $unplaced, 'the loop should have found cities with no coordinates');
    }

    public function test_the_map_draws_an_unplaceable_city_and_flags_it(): void
    {
        $superadmin = $this->superadmin();

        // Bayawan sits in Negros Oriental, so it borrows its province's centre -
        // the other half of the fallback from the region case above.
        // The postal code is the reader's own, since the dataset has nothing to
        // say about a city it cannot place - the column is not nullable, so a
        // real address always carries one.
        $address = Address::factory()->for($superadmin)->create([
            'city' => 'City of Bayawan',
            'state' => 'Negros Oriental',
            'city_code' => '1804606000',
            'postal_code' => '6221',
            'latitude' => null,
            'longitude' => null,
        ]);

        $points = $this->actingAs($superadmin)->getJson(route('home.map'))->assertOk()->json();

        $this->assertCount(1, $points);
        $this->assertSame('City of Bayawan', $points[0]['city']);
        $this->assertTrue($points[0]['approximate'], 'a borrowed position must say so');

        // In Negros, and nowhere near the city itself - which is what makes this
        // an approximation rather than a position.
        $this->assertGreaterThan(9.0, $points[0]['lat']);
        $this->assertLessThan(10.0, $points[0]['lat']);
        $this->assertGreaterThan(122.5, $points[0]['lng']);
        $this->assertLessThan(124.0, $points[0]['lng']);

        // The stand-in is drawn, never stored. The row still holds no
        // coordinates, which is what keeps the coverage figure above the map
        // and the spreadsheet export telling the truth.
        $this->assertNull($address->fresh()->latitude);
    }

    public function test_a_placed_city_is_not_flagged_as_approximate(): void
    {
        $superadmin = $this->superadmin();
        Address::factory()->for($superadmin)->create();

        $points = $this->actingAs($superadmin)->getJson(route('home.map'))->assertOk()->json();

        $this->assertCount(1, $points);
        $this->assertFalse($points[0]['approximate']);
    }

    public function test_a_free_text_address_is_still_absent_from_the_map(): void
    {
        $superadmin = $this->superadmin();

        Address::factory()->for($superadmin)->create([
            'city' => 'Springfield',
            'city_code' => null,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertSame(
            [],
            $this->actingAs($superadmin)->getJson(route('home.map'))->assertOk()->json(),
        );
    }
}
