<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class AddressExportTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['owner', 'label', 'line1', 'city', 'country', 'is_default', 'actions'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_export_returns_an_xlsx_download(): void
    {
        Address::factory()->count(3)->create();

        $response = $this->export($this->userWithRole('Admin'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_export_contains_only_the_rows_matching_the_search_filter(): void
    {
        Address::factory()->create(['city' => 'Zzuniqueville', 'label' => 'Keep']);
        Address::factory()->count(4)->create(['city' => 'Commonplace', 'label' => 'Drop']);

        $sheet = $this->exportSheet($this->userWithRole('Admin'), ['search' => ['value' => 'Zzuniqueville']]);

        $this->assertSame(['Owner', 'Label', 'Address', 'City', 'Country', 'Default'], $sheet[0]);
        $this->assertCount(2, $sheet, 'header row plus exactly one matching address');

        $cities = array_column(array_slice($sheet, 1), 3);
        $this->assertSame(['Zzuniqueville'], $cities);
    }

    public function test_export_excludes_the_actions_column(): void
    {
        Address::factory()->create();

        $sheet = $this->exportSheet($this->userWithRole('Admin'));

        $this->assertNotContains('Actions', $sheet[0]);
    }

    public function test_viewer_cannot_export(): void
    {
        Address::factory()->create();

        $this->export($this->userWithRole('Viewer'))->assertForbidden();
    }

    public function test_export_button_is_only_rendered_for_permitted_users(): void
    {
        Address::factory()->create();

        $adminHtml = $this->actingAs($this->userWithRole('Admin'))
            ->get(route('addresses.index'))->assertOk()->getContent();
        $viewerHtml = $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('addresses.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Export to Excel', $adminHtml);
        $this->assertStringNotContainsString('Export to Excel', $viewerHtml);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function export(User $user)
    {
        return $this->actingAs($user)->get(
            route('addresses.index', $this->dataTableParams(['action' => 'excel']))
        );
    }

    /** @return list<list<mixed>> */
    private function exportSheet(User $user, array $overrides = []): array
    {
        $response = $this->actingAs($user)->get(
            route('addresses.index', $this->dataTableParams(array_merge(['action' => 'excel'], $overrides)))
        );

        $response->assertOk();

        $file = $response->baseResponse->getFile()->getPathname();

        return IOFactory::load($file)->getActiveSheet()->toArray();
    }

    /** Build the parameter set a DataTables server-side request would send. */
    private function dataTableParams(array $overrides = []): array
    {
        $params = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 1, 'dir' => 'asc']],
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
