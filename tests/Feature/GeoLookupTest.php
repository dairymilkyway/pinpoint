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

        $this->actingAs($superadmin)
            ->post(route('addresses.store'), [
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
            ->assertRedirect(route('addresses.user', $superadmin))
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
            ->post(route('addresses.store'), [
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
            ->post(route('addresses.store'), [
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
}
