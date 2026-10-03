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

    public function test_superadmin_can_open_the_rbac_page(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('rbac.index'))
            ->assertOk()
            ->assertSee('Permission matrix');
    }

    public function test_admin_cannot_open_the_rbac_page(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('rbac.index'))
            ->assertForbidden();
    }

    public function test_customer_cannot_save_permissions(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->post(route('rbac.permissions'), $this->matrix(['addresses.view']))
            ->assertForbidden();
    }

    public function test_granting_a_permission_takes_effect_on_the_next_request(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)->get(route('addresses.create'))->assertForbidden();

        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.permissions'), $this->matrix(['addresses.view', 'addresses.create']))
            ->assertRedirect();

        $this->assertTrue($customer->fresh()->can('addresses.create'));

        $this->actingAs($customer->fresh())->get(route('addresses.create'))->assertOk();
    }

    public function test_revoking_a_permission_takes_effect_on_the_next_request(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get(route('addresses.create'))->assertOk();

        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.permissions'), $this->matrix(['addresses.view'], ['addresses.view']))
            ->assertRedirect();

        $this->actingAs($admin->fresh())->get(route('addresses.create'))->assertForbidden();
    }

    public function test_superadmin_role_cannot_lose_rbac_manage(): void
    {
        // Post an Admin row with every box unchecked, as a hostile form would.
        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.permissions'), [
                'permissions' => [
                    'Superadmin' => [],
                    'Admin' => ['addresses.view'],
                    'Customer' => ['addresses.view'],
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(Role::findByName('Superadmin')->hasPermissionTo(Rbac::MANAGE_PERMISSION));

        $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('rbac.index'))
            ->assertOk();
    }

    public function test_superadmin_can_assign_a_role_to_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.users.role', $user), ['role' => 'Admin'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasRole('Admin'));
    }

    /**
     * The form disables the control for a Superadmin, but a disabled control is
     * a rendering decision. Demoting the last one would leave nobody holding
     * rbac.manage, and there is no way back from inside the app.
     */
    public function test_a_superadmin_cannot_be_demoted(): void
    {
        $superadmin = $this->userWithRole('Superadmin');

        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.users.role', $superadmin), ['role' => 'Admin'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($superadmin->fresh()->hasRole('Superadmin'));
        $this->assertFalse($superadmin->fresh()->hasRole('Admin'));
    }

    public function test_the_matrix_locks_rbac_manage_on_the_superadmin_column(): void
    {
        $html = $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('rbac.index'))->assertOk()->getContent();

        // Matched loosely on purpose: the assertion is that this one box cannot
        // be operated, not the order Blade writes its attributes in.
        $this->assertMatchesRegularExpression(
            '/name="permissions\[Superadmin\]\[\]"\s+value="rbac\.manage"[^>]*disabled/',
            $html,
        );

        // And only that one - the rest of the column still posts, or saving the
        // matrix would strip the Superadmin of everything but rbac.manage.
        $this->assertMatchesRegularExpression(
            '/name="permissions\[Superadmin\]\[\]"\s+value="addresses\.view"(?![^>]*disabled)/',
            $html,
        );
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.users.role', $user), ['role' => 'Superuser'])
            ->assertSessionHasErrors('role');
    }

    /**
     * Build a complete matrix submission, the way the real form posts it.
     *
     * @param  list<string>  $customerPermissions
     * @param  list<string>|null  $adminPermissions  Null keeps the seeded Admin set.
     * @return array<string, mixed>
     */
    private function matrix(array $customerPermissions, ?array $adminPermissions = null): array
    {
        return [
            'permissions' => [
                'Superadmin' => Rbac::PERMISSIONS,
                'Admin' => $adminPermissions
                    ?? ['addresses.view', 'addresses.create', 'addresses.edit', 'addresses.export'],
                'Customer' => $customerPermissions,
            ],
        ];
    }
}
