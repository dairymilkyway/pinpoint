<?php

namespace Tests\Feature;

use App\Geo\PhLocations;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Stand in for the .env values the seeder normally reads, so the test
        // does not depend on a developer's local environment.
        config([
            'demo.owners_password' => 'demo-secret-1234',
            'demo.accounts' => [
                ['role' => 'Superadmin', 'name' => 'Ana Reyes', 'email' => 'admin@example.test', 'password' => 'admin-secret-1234'],
                ['role' => 'Admin', 'name' => 'Miguel Santos', 'email' => 'manager@example.test', 'password' => 'demo-secret-1234'],
                ['role' => 'Customer', 'name' => 'Liza Mendoza', 'email' => 'viewer@example.test', 'password' => 'demo-secret-1234'],
            ],
            'demo.owners' => [
                'Juan Dela Cruz' => 'juan.delacruz@example.test',
                'Maria Santos' => 'maria.santos@example.test',
            ],
        ]);
    }

    public function test_the_full_seeder_runs_clean(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Three picker accounts plus two extra owners.
        $this->assertSame(5, User::count());

        $this->assertSame(1, User::role('Superadmin')->count());
        $this->assertSame(1, User::role('Admin')->count());

        // The extra owners are Customers too, so the count is one plus their number.
        $this->assertSame(1 + count(config('demo.owners')), User::role('Customer')->count());

        // Nobody was left behind without a role, which is what the old
        // User::factory() call in this seeder used to produce.
        $this->assertSame(0, User::doesntHave('roles')->count());
    }

    public function test_running_the_seeder_twice_changes_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $users = User::count();
        $addresses = Address::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($users, User::count(), 'Re-seeding duplicated users.');
        $this->assertSame($addresses, Address::count(), 'Re-seeding duplicated addresses.');
    }

    public function test_the_queue_is_seeded_with_one_waiting_request_of_each_kind(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, AddressRequest::count());

        foreach ([AddressRequest::TYPE_CREATE, AddressRequest::TYPE_UPDATE, AddressRequest::TYPE_DELETE] as $type) {
            $change = AddressRequest::query()->where('type', $type)->sole();

            $this->assertSame(AddressRequest::STATUS_PENDING, $change->status);
            $this->assertFalse(
                $change->user->seesEveryAddress(),
                "The {$type} request was raised by a managing role.",
            );
        }

        // An addition has no address behind it; the other two do.
        $this->assertNull(AddressRequest::query()->where('type', AddressRequest::TYPE_CREATE)->sole()->address_id);
        $this->assertNotNull(AddressRequest::query()->where('type', AddressRequest::TYPE_UPDATE)->sole()->address_id);

        // Seeding runs with nobody signed in, and an entry that cannot be
        // attributed would only bury the real ones.
        $this->assertSame(0, AuditLog::count());
    }

    public function test_running_the_seeder_twice_does_not_stack_requests(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        // By kind rather than by count: a second run must not leave two of the
        // same demo waiting, or the queue doubles every time the seeder is run.
        $this->assertSame(3, AddressRequest::count());
    }

    public function test_every_seeded_address_uses_a_real_city_with_coordinates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cities = PhLocations::cities();

        $this->assertGreaterThan(0, Address::count());

        foreach (Address::all() as $address) {
            $this->assertArrayHasKey($address->city_code, $cities);
            $this->assertSame($cities[$address->city_code]['name'], $address->city);
            $this->assertSame('Philippines', $address->country);

            // Coordinates come from the verified GeoNames set, which is exactly
            // what makes the seeded rows pinnable on the map.
            $this->assertNotNull($address->latitude, "Address {$address->id} has no latitude.");
            $this->assertNotNull($address->longitude, "Address {$address->id} has no longitude.");
        }
    }

    public function test_the_province_recorded_matches_the_city(): void
    {
        $this->seed(DatabaseSeeder::class);

        $cities = PhLocations::cities();

        foreach (Address::all() as $address) {
            $city = $cities[$address->city_code];

            $this->assertSame($city['region'], $address->region_code);
            $this->assertSame($city['province'], $address->province_code);
            $this->assertSame(PhLocations::stateFor($city), $address->state);
        }
    }

    public function test_the_directory_readers_own_no_addresses(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['Superadmin', 'Admin'] as $role) {
            $reader = User::role($role)->sole();

            // They read the whole book through their role. Holding rows of their
            // own would make the Owners card count them as owners and put them
            // in the users list as the accounts that manage it.
            $this->assertSame(0, $reader->addresses()->count(), "{$role} owns addresses.");

            // And holding none must not narrow what they can reach.
            $this->assertTrue($reader->seesEveryAddress());
            $this->assertSame(Address::count(), Address::query()->visibleTo($reader)->count());
        }
    }

    public function test_each_owner_has_exactly_one_default_address(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (User::all() as $user) {
            $this->assertSame(
                // A reader owns nothing, so it has no default either.
                $user->seesEveryAddress() ? 0 : 1,
                $user->addresses()->where('is_default', true)->count(),
                "{$user->name} does not have the expected default address count.",
            );
        }
    }
}
