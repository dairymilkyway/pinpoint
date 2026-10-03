<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['name', 'email', 'addresses', 'actions'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_users_list_requires_authentication(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
    }

    public function test_both_directory_reading_roles_get_the_users_list(): void
    {
        foreach (['Superadmin', 'Admin'] as $role) {
            $html = $this->actingAs($this->userWithRole($role))
                ->get(route('addresses.index'))->assertOk()->getContent();

            $this->assertStringContainsString('users-table', $html, "{$role} did not get the users list.");
            $this->assertStringNotContainsString('addresses-table', $html);
        }
    }

    public function test_a_customer_gets_their_own_address_table_instead(): void
    {
        $customer = $this->userWithRole('Customer');

        $html = $this->actingAs($customer)
            ->get(route('addresses.index'))->assertOk()->getContent();

        $this->assertStringContainsString('addresses-table', $html);
        $this->assertStringNotContainsString('users-table', $html);
    }

    public function test_the_list_shows_the_owners_with_a_view_link_and_a_count(): void
    {
        // Named rather than left to faker: rows are looked up by name below, and
        // a generated name makes that lookup non-repeatable.
        $reader = User::factory()->create(['name' => 'Rita Reader'])->assignRole('Superadmin');

        $alice = User::factory()->create(['name' => 'Alice Anders', 'email' => 'alice@example.test']);
        Address::factory()->count(3)->for($alice)->create();
        Address::factory()->for($reader)->create();

        $data = $this->rows($reader);

        // Alice, and nobody else. The reader does hold an address of its own,
        // but it is not an owner of the book it manages, so it is not listed.
        $this->assertSame(1, $data['recordsTotal']);
        $this->assertNull(collect($data['data'])->firstWhere('name', $reader->name));

        $row = collect($data['data'])->firstWhere('name', 'Alice Anders');
        $this->assertNotNull($row, 'Alice is missing from the users list.');
        $this->assertSame('alice@example.test', $row['email']);
        $this->assertSame(3, $row['addresses']);
        $this->assertStringContainsString(route('addresses.user', $alice), $row['actions']);
    }

    /**
     * Both roles manage the book rather than hold part of it. Listed as owners
     * they showed a zero in the count column and a link to an empty page, and
     * said the two accounts that run the directory are customers in it.
     */
    public function test_the_two_managing_roles_are_not_listed_as_owners(): void
    {
        $superadmin = User::factory()->create(['name' => 'Ana Reyes'])->assignRole('Superadmin');
        User::factory()->create(['name' => 'Miguel Santos'])->assignRole('Admin');
        User::factory()->create(['name' => 'Liza Mendoza'])->assignRole('Customer');

        $names = collect($this->rows($superadmin)['data'])->pluck('name');

        $this->assertTrue($names->contains('Liza Mendoza'), 'The Customer is missing from the users list.');
        $this->assertNotContains('Ana Reyes', $names);
        $this->assertNotContains('Miguel Santos', $names);
    }

    /**
     * The role column was removed. Yajra's addColumn feeds the row payload even
     * when the column is not declared in getColumns(), so dropping one side and
     * not the other leaves the data on the wire with no header above it - the
     * key has to be gone from the payload, not just from the table.
     */
    public function test_the_list_carries_no_role_column(): void
    {
        $reader = User::factory()->create()->assignRole('Superadmin');
        User::factory()->create(['name' => 'Liza Mendoza'])->assignRole('Customer');

        $row = $this->rows($reader)['data'][0];

        $this->assertArrayNotHasKey('role', $row);
    }

    public function test_the_list_search_filters_by_name(): void
    {
        $reader = $this->userWithRole('Superadmin');
        User::factory()->create(['name' => 'Zzunique Person']);

        $data = $this->rows($reader, ['search' => ['value' => 'Zzunique']]);

        $this->assertCount(1, $data['data']);
        $this->assertSame('Zzunique Person', $data['data'][0]['name']);
    }

    public function test_the_list_paginates(): void
    {
        $reader = $this->userWithRole('Superadmin');
        User::factory()->count(6)->create();

        $data = $this->rows($reader, ['length' => 3]);

        // The six factory accounts and nothing from the reader, who is a
        // managing role and so is not one of the owners being counted.
        $this->assertCount(3, $data['data']);
        $this->assertSame(6, $data['recordsTotal']);
    }

    public function test_the_users_list_has_no_export_action(): void
    {
        $superadmin = $this->userWithRole('Superadmin');

        // The parent DataTable registers an excel action by default and it is
        // reachable by query string even with no button on the page. Left on,
        // this would hand out an ungated dump of every user and their email.
        $response = $this->actingAs($superadmin)->get(
            route('addresses.index', $this->params(['action' => 'excel']))
        );

        $response->assertOk();
        $this->assertStringNotContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_opening_a_user_names_them_and_shows_only_their_addresses(): void
    {
        $reader = $this->userWithRole('Superadmin');

        $alice = User::factory()->create(['name' => 'Alice Anders', 'email' => 'alice@example.test']);
        Address::factory()->count(2)->for($alice)->create();
        Address::factory()->count(4)->for($reader)->create();

        $html = $this->actingAs($reader)
            ->get(route('addresses.user', $alice))->assertOk()->getContent();

        $this->assertStringContainsString('Alice Anders', $html);
        $this->assertStringContainsString('alice@example.test', $html);
        $this->assertStringContainsString(route('addresses.index'), $html);

        // The four held by the reader are not on Alice's page.
        $this->assertSame(2, $this->rows($reader, [], $alice)['recordsTotal']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    /** @return array<string, mixed> */
    private function rows(User $actor, array $overrides = [], ?User $owner = null): array
    {
        $params = $this->params($overrides);

        $url = $owner === null
            ? route('addresses.index', $params)
            : route('addresses.user', array_merge(['user' => $owner->id], $params));

        return $this->actingAs($actor)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->get($url)
            ->assertOk()
            ->json();
    }

    /** Build the parameter set a DataTables server-side request would send. */
    private function params(array $overrides = []): array
    {
        $params = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [],
        ];

        foreach (self::COLUMNS as $index => $column) {
            $params['columns'][$index] = [
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return array_replace_recursive($params, $overrides);
    }
}
