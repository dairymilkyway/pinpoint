<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Rbac;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four actions on the Roles & permissions screen used to leave no trace at
 * all. This covers what each one records, and - at least as importantly - that
 * a refused attempt and a save that changed nothing record nothing, so the
 * append-only log does not fill with entries saying "nothing happened".
 */
class RbacAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_changing_a_users_role_is_recorded(): void
    {
        $superadmin = $this->superadmin();
        $customer = User::factory()->create()->assignRole('Customer');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.role', $customer), ['role' => 'Admin'])
            ->assertRedirect();

        $log = AuditLog::query()->where('event', AuditLog::USER_ROLE_CHANGED)->sole();

        $this->assertSame($customer->id, $log->subject_id);
        $this->assertSame($superadmin->id, $log->actor_id);
        $this->assertSame('Customer', $log->before['role']);
        $this->assertSame('Admin', $log->after['role']);
        // The subject column renders as "User #7", so the name is carried here.
        $this->assertSame($customer->name, $log->after['account']);
    }

    public function test_setting_the_role_the_user_already_holds_records_nothing(): void
    {
        $superadmin = $this->superadmin();
        $customer = User::factory()->create()->assignRole('Customer');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.role', $customer), ['role' => 'Customer'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** The matrix the seeder already installed, posted back untouched. */
    public function test_saving_the_matrix_unchanged_records_nothing(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('rbac.permissions'), ['permissions' => Rbac::ROLE_PERMISSIONS])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * One entry per role that actually moved, not one per save. The Superadmin
     * and Admin sets are posted at their seeded values, so only the Customer
     * may be recorded - a single entry for the whole save would pass a weaker
     * assertion but tell the reader nothing about which role changed.
     */
    public function test_only_the_role_whose_permissions_moved_is_recorded(): void
    {
        $matrix = Rbac::ROLE_PERMISSIONS;

        // Dropping one permission guarantees a diff without depending on what
        // the seeded Customer set happens to be.
        $matrix[Rbac::CUSTOMER_ROLE] = array_slice($matrix[Rbac::CUSTOMER_ROLE], 1);

        $this->actingAs($this->superadmin())
            ->post(route('rbac.permissions'), ['permissions' => $matrix])
            ->assertRedirect();

        $logs = AuditLog::query()->where('event', AuditLog::RBAC_PERMISSIONS_CHANGED)->get();

        $this->assertCount(1, $logs);

        $log = $logs->first();

        $this->assertSame('Customer', $log->after['role']);
        $this->assertNotSame($log->before['permissions'], $log->after['permissions']);
        // Flat strings, because the Change column echoes the value straight
        // into the page and an array would print as "Array".
        $this->assertIsString($log->before['permissions']);
        $this->assertIsString($log->after['permissions']);
    }

    /** A role change is not a permission change and must not be logged as one. */
    public function test_a_role_assignment_only_writes_the_role_event(): void
    {
        $superadmin = $this->superadmin();
        $customer = User::factory()->create()->assignRole('Customer');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.role', $customer), ['role' => 'Admin'])
            ->assertRedirect();

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLog::USER_ROLE_CHANGED]);
    }

    public function test_deactivating_a_user_is_recorded(): void
    {
        $superadmin = $this->superadmin();
        $customer = User::factory()->create(['name' => 'Cara Closed'])->assignRole('Customer');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $customer))
            ->assertRedirect();

        $log = AuditLog::query()->where('event', AuditLog::USER_DEACTIVATED)->sole();

        $this->assertSame($customer->id, $log->subject_id);
        $this->assertSame($superadmin->id, $log->actor_id);
        $this->assertSame('Cara Closed', $log->after['account']);
        $this->assertSame('Deactivated', $log->after['state']);
    }

    public function test_reactivating_a_user_is_recorded(): void
    {
        $superadmin = $this->superadmin();
        $customer = User::factory()->create(['name' => 'Cara Closed'])->assignRole('Customer');

        $customer->delete();
        $customer->restore();

        $this->actingAs($superadmin)
            ->post(route('rbac.users.reactivate', $customer))
            ->assertRedirect();

        $log = AuditLog::query()->where('event', AuditLog::USER_REACTIVATED)->sole();

        $this->assertSame($customer->id, $log->subject_id);
        $this->assertSame('Cara Closed', $log->after['account']);
        $this->assertSame('Reactivated', $log->after['state']);
    }

    /**
     * Attempts are not acts. Each of these returns to the screen with an error
     * and must leave the log alone - otherwise the log says an account was
     * deactivated when it was not.
     */
    public function test_a_refused_deactivation_records_nothing(): void
    {
        $superadmin = $this->superadmin();
        $other = User::factory()->create()->assignRole('Superadmin');

        // Onto themselves.
        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $superadmin))
            ->assertSessionHas('error');

        // Onto another holder of the role that gates this screen.
        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $other))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_refused_role_change_records_nothing(): void
    {
        $superadmin = $this->superadmin();
        $other = User::factory()->create()->assignRole('Superadmin');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.role', $other), ['role' => 'Customer'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** The gate is the middleware, so a 403 never reaches a record() call. */
    public function test_a_non_manager_writes_no_audit_entry(): void
    {
        $customer = User::factory()->create()->assignRole('Customer');
        $victim = User::factory()->create()->assignRole('Customer');

        $this->actingAs($customer)
            ->post(route('rbac.users.role', $victim), ['role' => 'Admin'])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('rbac.users.deactivate', $victim))
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('rbac.permissions'), ['permissions' => Rbac::ROLE_PERMISSIONS])
            ->assertForbidden();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * The audit screen needed no view change for any of this: it renders any
     * subject type, and the filter dropdown is built from EVENT_LABELS.
     */
    public function test_the_audit_screen_lists_and_filters_the_new_events(): void
    {
        $superadmin = $this->superadmin();
        $closed = User::factory()->create(['name' => 'Filter Me In'])->assignRole('Customer');
        $moved = User::factory()->create(['name' => 'Filter Me Out'])->assignRole('Customer');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $closed))
            ->assertRedirect();

        $this->actingAs($superadmin)
            ->post(route('rbac.users.role', $moved), ['role' => 'Admin'])
            ->assertRedirect();

        // Both events are in the dropdown, because it is built from the labels.
        $this->actingAs($superadmin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Account deactivated')
            ->assertSee('Role changed')
            ->assertSee('Filter Me In')
            ->assertSee('Filter Me Out');

        // And the filter narrows to one of them. The other entry's account name
        // is asserted absent because the dropdown holds every label, so the
        // label alone cannot tell a filtered page from an unfiltered one.
        $this->actingAs($superadmin)
            ->get(route('audit.index', ['event' => AuditLog::USER_DEACTIVATED]))
            ->assertOk()
            ->assertSee('Filter Me In')
            ->assertDontSee('Filter Me Out');
    }

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('Superadmin');
    }
}
