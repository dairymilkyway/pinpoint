<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AddressExportTest extends TestCase
{
    use RefreshDatabase;

    /** The directory table, which still carries the Owner column. */
    private const COLUMNS = ['owner', 'label', 'line1', 'city', 'country', 'map', 'is_default', 'actions'];

    /**
     * One owner's page, where the Owner column would repeat a single name and a
     * reader also loses Map and Default - see hidesMapAndDefault().
     */
    private const SCOPED_COLUMNS = ['label', 'line1', 'city', 'country', 'actions'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_export_returns_an_xlsx_download(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->count(3)->for($superadmin)->create();

        $response = $this->export($superadmin, $superadmin);

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_export_contains_only_the_rows_matching_the_search_filter(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->for($superadmin)->create(['city' => 'Zzuniqueville', 'label' => 'Keep']);
        Address::factory()->count(4)->for($superadmin)->create(['city' => 'Commonplace', 'label' => 'Drop']);

        $sheet = $this->exportSheet($superadmin, $superadmin, ['search' => ['value' => 'Zzuniqueville']]);

        // The export is cut from the same columns the table declares, so the
        // scoped sheet carries what the scoped page shows: no Owner, and now no
        // Map or Default either.
        $this->assertSame(['Label', 'Address', 'City', 'Country'], $sheet[0]);
        $this->assertCount(2, $sheet, 'header row plus exactly one matching address');

        $cities = array_column(array_slice($sheet, 1), 2);
        $this->assertSame(['Zzuniqueville'], $cities);
    }

    public function test_a_directory_reader_exports_only_the_opened_users_rows(): void
    {
        $reader = $this->userWithRole('Superadmin');

        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Address::factory()->count(2)->for($alice)->create(['city' => 'Zzalice']);
        Address::factory()->count(3)->for($bob)->create(['city' => 'Zzbob']);

        $sheet = $this->exportSheet($reader, $alice);

        $this->assertCount(3, $sheet, 'header row plus the two addresses Alice holds');
        $this->assertSame(['Zzalice', 'Zzalice'], array_column(array_slice($sheet, 1), 2));
    }

    public function test_a_scoped_role_only_exports_its_own_rows(): void
    {
        // The seeded Customer cannot export at all, so grant it the way the
        // permission matrix would. Otherwise this asserts the 403 rather than
        // the scoping, and the scoping is the part with no rule of its own.
        Role::findByName('Customer')->givePermissionTo('addresses.export');

        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['city' => 'Zzmine', 'label' => 'Keep']);
        Address::factory()->count(3)->create(['city' => 'Zztheirs', 'label' => 'Drop']);

        $sheet = $this->exportSheet($customer);

        // The export is built from the DataTable query, so it inherits the
        // scoping without a rule of its own - this is the test that says so.
        $this->assertCount(2, $sheet, 'header row plus exactly the one owned address');

        // This role reads the flat table, which keeps the Owner column, so the
        // city is located by its heading rather than by a fixed index.
        $city = array_search('City', $sheet[0], true);
        $this->assertSame(['Zzmine'], array_column(array_slice($sheet, 1), $city));
    }

    /**
     * The map state rides into the export, because the export strips markup and
     * keeps the text inside the badge. Which is why the pin carries a word and
     * not only an icon: an icon-only cell would export empty.
     *
     * Read on the flat table rather than on an owner's page, which drops the
     * column - and through a Customer, because a reader of the whole book lands
     * on the users list, which has no table to export.
     */
    public function test_the_export_carries_the_map_state_as_plain_text(): void
    {
        Role::findByName('Customer')->givePermissionTo('addresses.export');

        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['label' => 'Pinned']);
        Address::factory()->for($customer)->create([
            'label' => 'Unpinned',
            'latitude' => null,
            'longitude' => null,
        ]);

        $sheet = $this->exportSheet($customer);
        $map = array_search('Map', $sheet[0], true);

        $this->assertNotFalse($map, 'the export should carry the Map column');

        $label = array_search('Label', $sheet[0], true);
        $values = [];

        foreach (array_slice($sheet, 1) as $row) {
            $values[$row[$label]] = $row[$map];
        }

        $this->assertSame('Pinned', $values['Pinned']);
        $this->assertSame('No location', $values['Unpinned']);
    }

    public function test_export_excludes_the_actions_column(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->for($superadmin)->create();

        $sheet = $this->exportSheet($superadmin, $superadmin);

        $this->assertNotContains('Actions', $sheet[0]);
    }

    public function test_customer_cannot_export(): void
    {
        Address::factory()->create();

        $this->export($this->userWithRole('Customer'))->assertForbidden();
    }

    public function test_the_export_button_is_rendered_only_where_the_table_can_export(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->for($superadmin)->create();

        // On one owner's page the address table is what renders, so the button is there.
        $scoped = $this->actingAs($superadmin)
            ->get(route('addresses.user', $superadmin))->assertOk()->getContent();
        $this->assertStringContainsString('Export to Excel', $scoped);

        // The users list exports nothing - it has no export action at all.
        $users = $this->actingAs($superadmin)
            ->get(route('addresses.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Export to Excel', $users);

        $customer = $this->userWithRole('Customer');
        $ownTable = $this->actingAs($customer)
            ->get(route('addresses.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Export to Excel', $ownTable);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function export(User $actor, ?User $owner = null)
    {
        return $this->actingAs($actor)->get($this->exportUrl($owner));
    }

    /** @return list<list<mixed>> */
    private function exportSheet(User $actor, ?User $owner = null, array $overrides = []): array
    {
        $response = $this->actingAs($actor)->get($this->exportUrl($owner, $overrides));

        $response->assertOk();

        $file = $response->baseResponse->getFile()->getPathname();

        return IOFactory::load($file)->getActiveSheet()->toArray();
    }

    private function exportUrl(?User $owner, array $overrides = []): string
    {
        $params = $this->dataTableParams(
            $owner === null ? self::COLUMNS : self::SCOPED_COLUMNS,
            $overrides,
        );

        return $owner === null
            ? route('addresses.index', $params)
            : route('addresses.user', array_merge(['user' => $owner->id], $params));
    }

    /** Build the parameter set a DataTables server-side request would send. */
    private function dataTableParams(array $columns, array $overrides): array
    {
        $params = array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
            'columns' => [],
        ], ['action' => 'excel'], $overrides);

        foreach ($columns as $index => $column) {
            $params['columns'][$index] = array_replace_recursive([
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ], $params['columns'][$index] ?? []);
        }

        return $params;
    }
}
