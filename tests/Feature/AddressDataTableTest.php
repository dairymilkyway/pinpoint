<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressDataTableTest extends TestCase
{
    use RefreshDatabase;

    /** The directory table, which still carries the Owner column. */
    private const COLUMNS = ['owner', 'label', 'line1', 'city', 'country', 'map', 'is_default', 'actions'];

    /**
     * One owner's page. The Owner column would repeat a single name, and a
     * reader loses Map and Default there too - see hidesMapAndDefault().
     */
    private const SCOPED_COLUMNS = ['label', 'line1', 'city', 'country', 'actions'];

    /** Where Map sits in the directory column list, for an ordering request. */
    private const MAP_COLUMN = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_superadmin_row_actions_include_edit_and_delete(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->for($superadmin)->create();

        $row = $this->fetchRow($superadmin, $superadmin);

        $this->assertStringContainsString('Edit', $row['actions']);
        $this->assertStringContainsString('Delete', $row['actions']);
    }

    /**
     * A Customer cannot write to the directory, but the row is not left empty:
     * they get the three things they can actually do - ask for an edit, ask for
     * a deletion, and move their own default marker - and none of the two that
     * would write directly.
     */
    public function test_customer_row_actions_offer_requests_instead_of_writes(): void
    {
        $customer = $this->userWithRole('Customer');
        $address = Address::factory()->for($customer)->create();

        $row = $this->fetchRow($customer);

        $this->assertStringContainsString('Request edit', $row['actions']);
        $this->assertStringContainsString('Request deletion', $row['actions']);
        $this->assertStringContainsString('Make default', $row['actions']);

        $this->assertStringNotContainsString(route('addresses.edit', $address), $row['actions']);
        $this->assertStringNotContainsString('data-bs-target="#deleteAddressModal"', $row['actions']);
    }

    /** The reader's own row already is the default, so the star is not offered. */
    public function test_the_default_address_offers_no_make_default_action(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['is_default' => true]);

        $row = $this->fetchRow($customer);

        $this->assertStringNotContainsString('Make default', $row['actions']);
    }

    public function test_admin_row_actions_offer_edit_but_not_delete(): void
    {
        $admin = $this->userWithRole('Admin');
        Address::factory()->for($admin)->create();

        $row = $this->fetchRow($admin, $admin);

        $this->assertStringContainsString('Edit', $row['actions']);
        $this->assertStringNotContainsString('Delete', $row['actions']);
    }

    public function test_a_customer_only_sees_its_own_rows(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['label' => 'Mine']);
        Address::factory()->count(4)->create(['label' => 'Theirs']);

        $response = $this->ajax($customer);

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertSame('Mine', $response->json('data.0.label'));
    }

    public function test_a_directory_reader_sees_only_the_opened_users_rows(): void
    {
        $reader = $this->userWithRole('Superadmin');

        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        Address::factory()->count(2)->for($alice)->create(['label' => 'Alice row']);
        Address::factory()->count(3)->for($bob)->create(['label' => 'Bob row']);

        $aliceTable = $this->ajax($reader, $alice);

        $aliceTable->assertOk();
        $this->assertSame(2, $aliceTable->json('recordsTotal'));
        $this->assertSame(['Alice row', 'Alice row'], array_column($aliceTable->json('data'), 'label'));

        // The other owner's three are not merely below the fold, they are absent
        // from the count as well.
        $bobTable = $this->ajax($reader, $bob);

        $bobTable->assertOk();
        $this->assertSame(3, $bobTable->json('recordsTotal'));
        $this->assertSame(['Bob row', 'Bob row', 'Bob row'], array_column($bobTable->json('data'), 'label'));
    }

    public function test_the_scoped_table_drops_the_owner_column(): void
    {
        $reader = $this->userWithRole('Superadmin');
        Address::factory()->for($reader)->create();

        $scoped = $this->ajax($reader, $reader)->json('data.0');
        $this->assertArrayNotHasKey('owner', $scoped);

        // The unscoped directory table still offers it.
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create();

        $unscoped = $this->ajax($customer)->json('data.0');
        $this->assertArrayHasKey('owner', $unscoped);
    }

    public function test_search_filters_the_table(): void
    {
        $reader = $this->userWithRole('Superadmin');
        Address::factory()->for($reader)->create(['city' => 'Zzuniqueville']);
        Address::factory()->count(3)->for($reader)->create(['city' => 'Commonplace']);

        $response = $this->ajax($reader, $reader, ['search' => ['value' => 'Zzuniqueville']]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Zzuniqueville', $response->json('data.0.city'));
    }

    public function test_the_owner_search_cannot_reach_another_owners_rows(): void
    {
        $customer = $this->userWithRole('Customer');
        $other = User::factory()->create(['name' => 'Zzowner Person']);

        Address::factory()->for($customer)->create(['city' => 'Mine']);
        Address::factory()->for($other)->create(['city' => 'Theirs']);

        // The filter runs through the relation, so it must not become a way to
        // read a name the scope otherwise hides.
        $response = $this->ajax($customer, null, ['search' => ['value' => 'Zzowner']]);

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    /**
     * A reader opening somebody else's profile gets neither column. The default
     * marker is that owner's own business, and the Pins panel beside the table
     * already answers what the pin state answers, so the column is a second copy
     * of it. The flat table a Customer reads keeps both.
     */
    public function test_an_owners_page_drops_the_map_and_default_columns(): void
    {
        $reader = $this->userWithRole('Superadmin');
        Address::factory()->for($reader)->create(['is_default' => true]);

        $scoped = $this->ajax($reader, $reader)->json('data.0');

        // Absent from the payload, not merely undeclared: an addColumn value
        // rides along in the row whether or not a column is declared for it.
        $this->assertArrayNotHasKey('map', $scoped);

        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['is_default' => true]);

        $unscoped = $this->ajax($customer)->json('data.0');
        $this->assertArrayHasKey('map', $unscoped);

        // is_default is a real column rather than an added one, so it stays in
        // the payload either way - only the column that draws it is gone.
        $this->assertArrayHasKey('is_default', $scoped);
    }

    /**
     * The factory only ever builds cities GeoNames could place, so the pinned
     * row is the default and the unpinned one is stated outright. Both states
     * have to be legible, because the reader's question is "why is my pin
     * missing" and the answer is a property of the row.
     */
    public function test_the_map_column_says_which_rows_have_coordinates(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['label' => 'Pinned']);
        Address::factory()->for($customer)->create([
            'label' => 'Unpinned',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->ajax($customer);
        $response->assertOk();

        $rows = collect($response->json('data'))->keyBy('label');

        $this->assertStringContainsString('bi-geo-alt', $rows['Pinned']['map']);
        $this->assertStringNotContainsString('No location', $rows['Pinned']['map']);
        $this->assertStringContainsString('No location', $rows['Unpinned']['map']);
    }

    /**
     * Sorting is what turns the column into something a reader can act on: it
     * gathers the rows that will not be drawn, so they can be found without
     * scrolling the whole book.
     */
    public function test_the_map_column_sorts_the_unpinned_rows_together(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['label' => 'Pinned']);
        Address::factory()->for($customer)->create([
            'label' => 'Unpinned',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->ajax($customer, null, [
            'order' => [['column' => self::MAP_COLUMN, 'dir' => 'desc']],
        ]);

        $response->assertOk();

        // Descending puts the rows with no latitude on top.
        $this->assertSame('Unpinned', $response->json('data.0.label'));
    }

    public function test_pagination_limits_the_page_size(): void
    {
        $reader = $this->userWithRole('Superadmin');
        Address::factory()->count(7)->for($reader)->create();

        $response = $this->ajax($reader, $reader, ['length' => 5]);

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(7, $response->json('recordsTotal'));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    /** @return array<string, mixed> */
    private function fetchRow(User $actor, ?User $owner = null): array
    {
        $response = $this->ajax($actor, $owner);

        $response->assertOk();

        return $response->json('data.0');
    }

    /**
     * A DataTables request, sent to the directory table or to one owner's page.
     *
     * The column list has to match the table that will answer it, which is one
     * column shorter once the Owner column is dropped.
     */
    private function ajax(User $actor, ?User $owner = null, array $overrides = [])
    {
        $params = $this->dataTableParams(
            $owner === null ? self::COLUMNS : self::SCOPED_COLUMNS,
            $overrides,
        );

        $url = $owner === null
            ? route('addresses.index', $params)
            : route('addresses.user', array_merge(['user' => $owner->id], $params));

        return $this->actingAs($actor)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->get($url);
    }

    /** Build the parameter set a DataTables server-side request would send. */
    private function dataTableParams(array $columns, array $overrides): array
    {
        $params = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [],
        ];

        foreach ($columns as $index => $column) {
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
