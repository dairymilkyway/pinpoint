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
     * The fallback for the documented gap: 154 of the 1642 cities have no
     * coordinates, so a row in one of them is drawn at the centre of the
     * province or region it is known to sit in.
     */
    public function test_a_city_the_dataset_cannot_place_borrows_its_regions_centre(): void
    {
        // Taguig is a Metro Manila city with no province, so the region is the
        // only coarser place there is to borrow from.
        $centre = PhLocations::approximateFor('1381500000');

        $this->assertNotNull($centre);

        // Inside Metro Manila, and nowhere near anything that could be mistaken
        // for the city itself. This is the assertion that keeps the fallback an
        // approximation: if a real position is ever added for Taguig, this test
        // should be the thing that notices and gets updated.
        $this->assertGreaterThan(14.3, $centre['lat']);
        $this->assertLessThan(14.8, $centre['lat']);
        $this->assertGreaterThan(120.9, $centre['lng']);
        $this->assertLessThan(121.2, $centre['lng']);
    }

    public function test_a_city_that_was_placed_is_never_approximated(): void
    {
        // The fallback exists for the cities that have none, not as a substitute
        // for the 1488 that do.
        $this->assertNotNull(PhLocations::coordinatesFor('1380300000'));
        $this->assertNull(PhLocations::approximateFor('1380300000'));
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
            if (PhLocations::coordinatesFor($code) !== null) {
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

        $address = Address::factory()->for($superadmin)->create([
            'city' => 'City of Taguig',
            'city_code' => '1381500000',
            'postal_code' => '1630',
            'latitude' => null,
            'longitude' => null,
        ]);

        $points = $this->actingAs($superadmin)->getJson(route('home.map'))->assertOk()->json();

        $this->assertCount(1, $points);
        $this->assertSame('City of Taguig', $points[0]['city']);
        $this->assertTrue($points[0]['approximate'], 'a borrowed position must say so');
        $this->assertEqualsWithDelta(14.5868, $points[0]['lat'], 0.01);

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
