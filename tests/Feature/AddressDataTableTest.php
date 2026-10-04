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

    /** The directory table, which still carries the Owner and Phone columns. */
    private const COLUMNS = ['owner', 'owner_phone', 'label', 'line1', 'city', 'country', 'actions'];

    /**
     * One owner's page. The Owner column would repeat a single name, and a
     * reader loses the default marker there too - see hidesDefaultMarker().
     */
    private const SCOPED_COLUMNS = ['label', 'line1', 'city', 'country', 'actions'];

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
    /**
     * A Customer's own book is the one shape where the unscoped columns are
     * reachable, so it is the only place the Phone column is drawn. One owner's
     * page drops it along with Owner - the number appears once in that page's
     * identity header instead, which UserProfileTest covers.
     */
    public function test_the_phone_column_reaches_a_customer_on_their_own_book(): void
    {
        $customer = User::factory()->create(['phone' => '+639171234567'])->assignRole('Customer');
        Address::factory()->for($customer)->create();

        $this->assertSame('+639171234567', $this->fetchRow($customer)['owner_phone']);
    }

    public function test_the_phone_column_is_dropped_on_one_owners_page(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->for($superadmin)->create();

        // Same reasoning as the Owner column: repeated on every row, so the
        // header states it once and the column goes.
        $this->assertArrayNotHasKey('owner_phone', $this->fetchRow($superadmin, $superadmin));
    }

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
     * The Map column is gone from the table and from the payload. An addColumn
     * value rides along in the row whether or not a column is declared for it,
     * so absence is asserted on the payload rather than on the header - a header
     * assertion would pass while the key was still being shipped to the browser.
     */
    public function test_the_map_column_is_gone_from_the_payload(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create();

        $this->assertArrayNotHasKey('map', $this->ajax($customer)->json('data.0'));
    }

    /**
     * The default marker has no column of its own now - it rides in the label
     * cell. A reader opening somebody else's profile still does not see it: the
     * marker is that owner's own business and moving it is theirs to do. The flat
     * table a Customer reads keeps it.
     */
    public function test_an_owners_page_hides_the_default_marker(): void
    {
        $reader = $this->userWithRole('Superadmin');
        Address::factory()->for($reader)->create(['label' => 'Mine', 'is_default' => true]);

        $scoped = $this->ajax($reader, $reader)->json('data.0');
        $this->assertStringNotContainsString('Default', $scoped['label']);

        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['label' => 'Theirs', 'is_default' => true]);

        $unscoped = $this->ajax($customer)->json('data.0');
        $this->assertStringContainsString('Default', $unscoped['label']);
    }

    /** A row that is not the default carries no marker and no filler text. */
    public function test_a_row_that_is_not_the_default_renders_no_marker(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['label' => 'Plain', 'is_default' => false]);

        $this->assertSame('Plain', $this->fetchRow($customer)['label']);
    }

    /**
     * The label cell is raw HTML now, because the Default badge renders inside
     * it. The label is user input, so it has to be escaped by hand -
     * rawColumns() turns off the escaping Blade would otherwise have given it.
     */
    public function test_a_label_containing_markup_is_escaped_not_rendered(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create([
            'label' => '<script>alert(1)</script>',
            'is_default' => true,
        ]);

        $row = $this->fetchRow($customer);

        $this->assertStringNotContainsString('<script>', $row['label']);
        $this->assertStringContainsString('&lt;script&gt;', $row['label']);
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

    /**
     * The Owner column only reaches a viewer on an unscoped table, which for a
     * reader is the users list rather than this one, so the two deactivation
     * paths that carry an owner's name are asserted in UserDeactivationTest
     * where they are actually reachable.
     */
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
     * The column list has to match the table that will answer it, which is two
     * columns shorter once the Owner and Phone columns are dropped.
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
            // Whichever index Label sits at in this shape, so the request follows
            // the table's own default instead of a number that goes stale the
            // next time a column is added in front of it.
            'order' => [['column' => array_search('label', $columns), 'dir' => 'asc']],
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
