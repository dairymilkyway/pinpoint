<?php

namespace Tests\Feature;

use App\Geo\PhLocations;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, string>> */
    private array $shippedPeople = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // The real file's people, read before the stand-in replaces them below.
        // The numbers are literals in config/demo.php rather than env-derived, so
        // this is the only assertion that can catch a bad number in the shipped
        // config - the seeded rows all come from the stand-in.
        $this->shippedPeople = array_merge(config('demo.accounts'), config('demo.owners'));

        // Stand in for the .env values the seeder normally reads, so the test
        // does not depend on a developer's local environment.
        config([
            'demo.owners_password' => 'demo-secret-1234',
            'demo.accounts' => [
                ['role' => 'Superadmin', 'name' => 'Ana Reyes', 'email' => 'ana.reyes@gmail.com', 'password' => 'admin-secret-1234', 'phone' => '+639170000001'],
                ['role' => 'Admin', 'name' => 'Miguel Santos', 'email' => 'miguel.santos@gmail.com', 'password' => 'demo-secret-1234', 'phone' => '+639170000002'],
                ['role' => 'Customer', 'name' => 'Liza Mendoza', 'email' => 'liza.mendoza@gmail.com', 'password' => 'demo-secret-1234', 'phone' => '+639170000003'],
            ],
            'demo.owners' => [
                ['name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@gmail.com', 'phone' => '+639170000004'],
                ['name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'phone' => '+639170000005'],
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

    /**
     * Every number the seeder writes comes from config rather than a hash, so
     * what lands in the database is what someone chose - and it has to be one
     * the app's own rule accepts.
     */
    public function test_every_seeded_person_gets_the_mobile_number_from_config(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (User::all() as $user) {
            $this->assertNotNull($user->phone, "{$user->name} was seeded without a number.");
            $this->assertTrue(
                $this->isMobileNumber($user->phone),
                "{$user->name} was seeded with a number the rule rejects: {$user->phone}",
            );
        }

        foreach (array_merge(config('demo.accounts'), config('demo.owners')) as $person) {
            $this->assertSame(
                $person['phone'],
                User::query()->where('email', $person['email'])->sole()->phone,
                "{$person['name']} did not get the number config holds for them.",
            );
        }
    }

    /** The address sits beside a real name, in the shape the brief asked for. */
    public function test_every_seeded_person_gets_a_named_gmail_address(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (User::all() as $user) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+\.[a-z]+@gmail\.com$/',
                $user->email,
                "{$user->name} was seeded with a placeholder address: {$user->email}",
            );

            $this->assertStringStartsWith(
                strtolower(explode(' ', $user->name)[0]),
                $user->email,
                "{$user->name}'s address does not begin with their first name.",
            );
        }
    }

    /**
     * The stand-in config above is what the other tests run against, so this is
     * the only test that reads the numbers actually shipped. They are literals
     * rather than env-derived, so it is stable in any environment.
     *
     * The prefix check lives here and not in PhilippineMobileNumber on purpose.
     * The rule matches any leading 9 followed by nine digits, so it accepts
     * +639000000000 - it validates the shape, never the allocation. That is the
     * right split: prefixes are issued and retired over the years, and a
     * validator that rejected a newly allocated one would be a worse bug than a
     * seeder using an unused one. So the allocation is a data assertion, against
     * this list, and never a rule in application code.
     */
    private const ALLOCATED_PREFIXES = ['918', '927', '936', '945', '955', '966', '971', '985'];

    public function test_the_shipped_demo_config_carries_realistic_contact_details(): void
    {
        $this->assertCount(8, $this->shippedPeople);

        foreach ($this->shippedPeople as $person) {
            $this->assertTrue(
                $this->isMobileNumber($person['phone']),
                "{$person['name']} has a number the rule rejects: {$person['phone']}",
            );

            // +63, then ten digits of which the first three are the network.
            $this->assertContains(
                substr($person['phone'], 3, 3),
                self::ALLOCATED_PREFIXES,
                "{$person['name']} has a number on a prefix no network allocates: {$person['phone']}",
            );
        }

        $numbers = array_column($this->shippedPeople, 'phone');
        $this->assertSame($numbers, array_values(array_unique($numbers)), 'Two people share a number.');

        // The owners are plain literals with no env() around them, so their
        // addresses can be asserted where the picker accounts' cannot.
        foreach (config('demo.owners') as $owner) {
            $this->assertMatchesRegularExpression(
                '/^[a-z]+\.[a-z]+@gmail\.com$/',
                $owner['email'],
                "{$owner['name']} has a placeholder address: {$owner['email']}",
            );
        }
    }

    private function isMobileNumber(string $value): bool
    {
        return Validator::make(
            ['phone' => $value],
            ['phone' => [new PhilippineMobileNumber]],
        )->passes();
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
