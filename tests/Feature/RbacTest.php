<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rbac;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    public function test_admin_can_open_the_rbac_page(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('rbac.index'))
            ->assertOk()
            ->assertSee('Permission matrix');
    }

    public function test_manager_cannot_open_the_rbac_page(): void
    {
        $this->actingAs($this->userWithRole('Manager'))
            ->get(route('rbac.index'))
            ->assertForbidden();
    }

    public function test_viewer_cannot_save_permissions(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->post(route('rbac.permissions'), $this->matrix(['addresses.view']))
            ->assertForbidden();
    }

    public function test_granting_a_permission_takes_effect_on_the_next_request(): void
    {
        $viewer = $this->userWithRole('Viewer');

        $this->actingAs($viewer)->get(route('addresses.create'))->assertForbidden();

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('rbac.permissions'), $this->matrix(['addresses.view', 'addresses.create']))
            ->assertRedirect();

        $this->assertTrue($viewer->fresh()->can('addresses.create'));

        $this->actingAs($viewer->fresh())->get(route('addresses.create'))->assertOk();
    }

    public function test_revoking_a_permission_takes_effect_on_the_next_request(): void
    {
        $manager = $this->userWithRole('Manager');

        $this->actingAs($manager)->get(route('addresses.create'))->assertOk();

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('rbac.permissions'), $this->matrix(['addresses.view'], ['addresses.view']))
            ->assertRedirect();

        $this->actingAs($manager->fresh())->get(route('addresses.create'))->assertForbidden();
    }

    public function test_admin_role_cannot_lose_rbac_manage(): void
    {
        // Post an Admin row with every box unchecked, as a hostile form would.
        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('rbac.permissions'), [
                'permissions' => [
                    'Admin' => [],
                    'Manager' => ['addresses.view'],
                    'Viewer' => ['addresses.view'],
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(Role::findByName('Admin')->hasPermissionTo(Rbac::MANAGE_PERMISSION));

        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('rbac.index'))
            ->assertOk();
    }

    public function test_admin_can_assign_a_role_to_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('rbac.users.role', $user), ['role' => 'Manager'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasRole('Manager'));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->userWithRole('Admin'))
            ->post(route('rbac.users.role', $user), ['role' => 'Superuser'])
            ->assertSessionHasErrors('role');
    }

    /**
     * Build a complete matrix submission, the way the real form posts it.
     *
     * @param  list<string>  $viewerPermissions
     * @param  list<string>|null  $managerPermissions  Null keeps the seeded Manager set.
     * @return array<string, mixed>
     */
    private function matrix(array $viewerPermissions, ?array $managerPermissions = null): array
    {
        return [
            'permissions' => [
                'Admin' => Rbac::PERMISSIONS,
                'Manager' => $managerPermissions
                    ?? ['addresses.view', 'addresses.create', 'addresses.edit', 'addresses.export'],
                'Viewer' => $viewerPermissions,
            ],
        ];
    }
}
