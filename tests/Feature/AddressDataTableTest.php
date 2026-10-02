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

    private const COLUMNS = ['owner', 'label', 'line1', 'city', 'country', 'is_default', 'actions'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_row_actions_include_edit_and_delete(): void
    {
        Address::factory()->create();

        $row = $this->fetchRow($this->userWithRole('Admin'));

        $this->assertStringContainsString('Edit', $row['actions']);
        $this->assertStringContainsString('Delete', $row['actions']);
    }

    public function test_viewer_row_actions_are_empty(): void
    {
        Address::factory()->create();

        $row = $this->fetchRow($this->userWithRole('Viewer'));

        $this->assertSame('', trim($row['actions']));
    }

    public function test_manager_row_actions_offer_edit_but_not_delete(): void
    {
        Address::factory()->create();

        $row = $this->fetchRow($this->userWithRole('Manager'));

        $this->assertStringContainsString('Edit', $row['actions']);
        $this->assertStringNotContainsString('Delete', $row['actions']);
    }

    public function test_search_filters_the_table(): void
    {
        Address::factory()->create(['city' => 'Zzuniqueville']);
        Address::factory()->count(3)->create(['city' => 'Commonplace']);

        $response = $this->ajax($this->userWithRole('Admin'), ['search' => ['value' => 'Zzuniqueville']]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Zzuniqueville', $response->json('data.0.city'));
    }

    public function test_search_matches_the_owner_name_across_the_relation(): void
    {
        $owner = User::factory()->create(['name' => 'Zzowner Person']);
        Address::factory()->for($owner)->create();
        Address::factory()->count(2)->create();

        $response = $this->ajax($this->userWithRole('Admin'), ['search' => ['value' => 'Zzowner']]);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Zzowner Person', $response->json('data.0.owner'));
    }

    public function test_pagination_limits_the_page_size(): void
    {
        Address::factory()->count(7)->create();

        $response = $this->ajax($this->userWithRole('Admin'), ['length' => 5]);

        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(7, $response->json('recordsTotal'));
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    /** @return array<string, mixed> */
    private function fetchRow(User $user): array
    {
        $response = $this->ajax($user);

        $response->assertOk();

        return $response->json('data.0');
    }

    private function ajax(User $user, array $overrides = [])
    {
        return $this->actingAs($user)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->get(route('addresses.index', $this->dataTableParams($overrides)));
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
