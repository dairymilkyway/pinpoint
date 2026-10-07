<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rbac;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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

    public function test_every_permission_has_a_readable_label(): void
    {
        // The matrix falls back to the machine name when a permission has no
        // label, so a gap here would not fail - it would quietly put
        // "addresses.create" back on the screen. This is what makes that
        // fallback unreachable rather than merely unlikely.
        $this->assertSame(
            [],
            array_diff(Rbac::PERMISSIONS, array_keys(Rbac::LABELS)),
            'a permission has no label',
        );

        // And the other direction: a label naming a permission that no longer
        // exists is dead weight that reads as though it were live.
        $this->assertSame(
            [],
            array_diff(array_keys(Rbac::LABELS), Rbac::PERMISSIONS),
            'a label names no permission',
        );
    }

    public function test_an_unlabelled_permission_falls_back_to_its_machine_name(): void
    {
        $this->assertSame('addresses.archive', Rbac::label('addresses.archive'));
    }

    public function test_the_matrix_shows_the_readable_label_beside_the_machine_name(): void
    {
        $html = $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('rbac.index'))->assertOk()->getContent();

        // The label is what a reader decides on, the machine name is what they
        // match against a constant or a denied policy check. Both stay.
        $this->assertStringContainsString('Create addresses', $html);
        $this->assertStringContainsString('addresses.create', $html);
        // Escaped, because Blade writes the ampersand as an entity and this
        // assertion reads the raw HTML rather than assertSee's escaped search.
        $this->assertStringContainsString('Manage roles &amp; permissions', $html);

        // A checkbox with a role name and a machine name for an accessible name
        // is read aloud as jargon, so the label is what goes in.
        $this->assertStringContainsString('aria-label="Create addresses for Superadmin"', $html);
    }

    public function test_every_permission_sits_in_exactly_one_module(): void
    {
        $grouped = array_merge(...array_values(Rbac::MODULES));

        $this->assertSame(count($grouped), count(array_unique($grouped)), 'A permission is listed in two modules.');
        $this->assertEqualsCanonicalizing(Rbac::PERMISSIONS, $grouped);
    }

    public function test_the_matrix_draws_permissions_grouped_by_module(): void
    {
        $html = $this->actingAs($this->userWithRole('Superadmin'))->get(route('rbac.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/>Addresses<.*addresses\.export.*>Requests<.*requests\.view.*addresses\.request.*addresses\.approve.*>Audit log<.*>Administration<.*rbac\.manage/s',
            $html,
        );
    }

    public function test_the_matrix_locks_rbac_manage_on_every_column(): void
    {
        $html = $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('rbac.index'))->assertOk()->getContent();

        // Every column, not only the Superadmin's. It was tickable on the other
        // two, and the box gates the page it is drawn on.
        foreach (['Superadmin', 'Admin', 'Customer'] as $role) {
            $this->assertMatchesRegularExpression(
                '/name="permissions\['.$role.'\]\[\]"\s+value="rbac\.manage"[^>]*disabled/',
                $html,
                "rbac.manage is not locked on the {$role} column",
            );
        }

        // Checked for the one role that holds it, unchecked for the rest, so the
        // display matches what the controller enforces.
        $this->assertMatchesRegularExpression(
            '/name="permissions\[Superadmin\]\[\]"\s+value="rbac\.manage"[^>]*checked/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/name="permissions\[Customer\]\[\]"\s+value="rbac\.manage"(?![^>]*checked)/',
            $html,
        );
    }

    public function test_rbac_manage_cannot_be_granted_to_another_role(): void
    {
        // Posted directly, the way a hand-made request would, because the box is
        // disabled and a disabled control is not what the rule rests on.
        $this->actingAs($this->userWithRole('Superadmin'))
            ->post(route('rbac.permissions'), $this->matrix(
                ['addresses.view', 'rbac.manage'],
                ['addresses.view', 'rbac.manage'],
            ))
            ->assertRedirect();

        // Holding it is what lets a role open this page and grant itself the
        // rest, so neither may keep it.
        $this->assertFalse(Role::findByName('Customer')->hasPermissionTo('rbac.manage'));
        $this->assertFalse(Role::findByName('Admin')->hasPermissionTo('rbac.manage'));

        // And the Superadmin keeps it, or nobody could undo any of this.
        $this->assertTrue(Role::findByName('Superadmin')->hasPermissionTo('rbac.manage'));

        // The rest of the submission still lands, so stripping it did not take
        // the other ticks with it.
        $this->assertTrue(Role::findByName('Customer')->hasPermissionTo('addresses.view'));
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
