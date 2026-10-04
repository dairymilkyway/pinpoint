<?php

namespace Tests\Feature;

use App\Models\AddressRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deactivating an account is a soft delete on User, which puts a global scope on
 * every User query. That one fact reaches further than the RBAC screen: it is
 * what refuses the sign-in, and it is what would silently blank the owner's name
 * on every row that renders one. Both directions are asserted here.
 */
class UserDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * The point of the feature, and the test to red-check: Laravel's Eloquent
     * user provider retrieves credentials through the model, so the SoftDeletes
     * scope is the whole mechanism. Drop the trait and this becomes a
     * successful login while every other test here still passes.
     */
    public function test_a_deactivated_user_cannot_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'closed@example.com']);

        $user->delete();

        // The row must still be there, deactivated rather than gone. Without
        // this the test passes for the wrong reason: with the trait removed the
        // account is hard-deleted, so sign-in fails because there is no row at
        // all rather than because of the scope - red-checked, and it did exactly
        // that before this line existed.
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $this->post(route('login'), [
            'email' => 'closed@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reactivating_a_user_restores_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'back@example.com']);

        $user->delete();
        $user->restore();

        $this->post(route('login'), [
            'email' => 'back@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_superadmin_can_deactivate_another_user(): void
    {
        $superadmin = $this->reader();
        $customer = $this->customer();

        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted('users', ['id' => $customer->id]);
    }

    public function test_a_user_cannot_deactivate_themselves(): void
    {
        $superadmin = $this->reader();

        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $superadmin))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('users', ['id' => $superadmin->id]);
    }

    /**
     * Same reason as the existing no-demote rule: whoever holds the Superadmin
     * role is the only one who can reach this screen, so losing the last one
     * cannot be undone from inside the app.
     */
    public function test_a_user_holding_the_superadmin_role_cannot_be_deactivated(): void
    {
        $superadmin = $this->reader();
        $other = User::factory()->create()->assignRole('Superadmin');

        $this->actingAs($superadmin)
            ->post(route('rbac.users.deactivate', $other))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('users', ['id' => $other->id]);
    }

    /** Gated on the permission, not on the button being hidden. */
    public function test_a_non_manager_cannot_deactivate_anyone(): void
    {
        $customer = $this->customer();
        $victim = $this->customer();

        $this->actingAs($customer)
            ->post(route('rbac.users.deactivate', $victim))
            ->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $victim->id]);
    }

    public function test_a_superadmin_can_reactivate_a_deactivated_user(): void
    {
        $superadmin = $this->reader();
        $customer = $this->customer();

        $customer->delete();

        $this->actingAs($superadmin)
            ->post(route('rbac.users.reactivate', $customer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSoftDeleted('users', ['id' => $customer->id]);
    }

    /**
     * The row has to survive the global scope on the controller's query, or the
     * account cannot be brought back at all.
     */
    public function test_the_roles_screen_keeps_a_deactivated_user_in_the_table(): void
    {
        $superadmin = $this->reader();
        $customer = $this->customer();

        $customer->delete();

        $html = $this->actingAs($superadmin)
            ->get(route('rbac.index'))
            ->assertOk()
            ->getContent();

        // Faker produces apostrophes and Blade escapes them, so compare against
        // the escaped form rather than the raw name.
        $this->assertStringContainsString(e($customer->name), $html);
        $this->assertStringContainsString(route('rbac.users.reactivate', $customer), $html);
    }

    /**
     * row.blade.php renders the requester's name with no null guard, so a
     * trashed requester takes the whole queue down rather than rendering a
     * blank. The relation carrying withTrashed is what holds this.
     */
    public function test_the_requests_queue_renders_a_deactivated_requesters_name(): void
    {
        $superadmin = $this->reader();
        $customer = $this->customer();

        AddressRequest::create([
            'user_id' => $customer->id,
            'address_id' => null,
            'type' => AddressRequest::TYPE_CREATE,
            'payload' => [
                'label' => 'Warehouse',
                'line1' => '7 Katipunan Avenue',
                'city' => 'Quezon City',
                'country' => 'Philippines',
            ],
            'before' => null,
        ]);

        $customer->delete();

        $html = $this->actingAs($superadmin)
            ->get(route('requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e($customer->name), $html);
    }

    /**
     * The owner's decision was import fills the width while create stays capped,
     * so the two are asserted together - a change to either one has to be
     * deliberate rather than a shared constant moving underneath both.
     *
     * The cap used to be an inline style and is now a class, so the assertion
     * follows it there. What is held is the asymmetry between the two panels,
     * not the mechanism that produces it - both sides are still stated.
     */
    public function test_the_import_panel_fills_the_width_and_the_create_panel_stays_capped(): void
    {
        $superadmin = $this->reader();
        $owner = $this->customer();

        $import = $this->actingAs($superadmin)
            ->get(route('addresses.import.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('max-width', $this->panelTag($import)[0]);
        $this->assertStringNotContainsString('panel--reading', $this->panelTag($import)[0]);

        $create = $this->actingAs($superadmin)
            ->get(route('addresses.create', ['user' => $owner->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('panel--reading', $this->panelTag($create)[0]);
    }

    /**
     * The row keeps its role select and Update when deactivated - that was
     * explicit - so those controls have to work. Without withTrashed on the
     * route binding the POST resolves through the SoftDeletes scope and 404s,
     * leaving a control on screen that errors when used.
     */
    public function test_the_role_of_a_deactivated_user_can_still_be_changed(): void
    {
        $superadmin = $this->reader();
        $customer = $this->customer();

        $customer->delete();

        $this->actingAs($superadmin)
            ->post(route('rbac.users.role', $customer), ['role' => 'Admin'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($customer->fresh()->hasRole('Admin'));
    }

    /**
     * "Keep them, keep the owner's name" is the owner's decision, and this is
     * where it is actually reachable: a reader finds an owner in the users list
     * and their book is the page behind it. If either one drops the trashed
     * account, the addresses the deactivation was meant to preserve have no way
     * left to be read, and the account is only recoverable from the RBAC screen.
     */
    public function test_a_deactivated_owner_is_still_reachable_from_the_directory(): void
    {
        $superadmin = $this->reader();
        $open = User::factory()->create(['name' => 'Oscar Open'])->assignRole('Customer');
        $closed = User::factory()->create(['name' => 'Cara Closed'])->assignRole('Customer');

        $closed->delete();

        // Asserted through the list's own endpoint. Not through owningAccounts():
        // that scope is the *pickers'* seam and must keep excluding deactivated
        // accounts, which is the next test's job. The list reads UserDataTable,
        // and the two are deliberately different questions.
        $rows = $this->userRows($superadmin)['data'];

        $listed = collect($rows)->first(fn (array $row) => str_contains($row['name'], 'Cara Closed'));

        $this->assertNotNull($listed, 'The deactivated owner is missing from the users list.');
        $this->assertStringContainsString('Deactivated', $listed['name']);
        $this->assertSame('is-deactivated', $listed['DT_RowClass']);

        // And the marker is not just on every row.
        $active = collect($rows)->first(fn (array $row) => str_contains($row['name'], 'Oscar Open'));

        $this->assertNotNull($active);
        $this->assertStringNotContainsString('Deactivated', $active['name']);
        $this->assertSame('', $active['DT_RowClass'] ?? '');

        // An owner in the list is only reachable if the page behind it opens.
        $this->actingAs($superadmin)
            ->get(route('addresses.user', $closed))
            ->assertOk();
    }

    public function test_a_deactivated_account_is_not_offered_as_an_address_owner(): void
    {
        $superadmin = $this->reader();
        $closed = $this->customer();

        $closed->delete();

        $html = $this->actingAs($superadmin)
            ->get(route('addresses.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(e($closed->name), $html);
    }

    /**
     * The panel opening tag on a page, so a width assertion reads the element
     * rather than the whole document - other rules on the page may legitimately
     * carry a max-width.
     *
     * @return array<int, string>
     */
    private function panelTag(string $html): array
    {
        preg_match('/<div class="panel[^"]*"[^>]*>/', $html, $match);

        $this->assertNotEmpty($match, 'The page renders a panel element.');

        return $match;
    }

    /**
     * The users list, as its own DataTables endpoint answers it. Column order
     * has to match UserDataTable::getColumns().
     *
     * @return array<string, mixed>
     */
    private function userRows(User $actor): array
    {
        $params = [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'columns' => [],
        ];

        foreach (['name', 'email', 'addresses', 'actions'] as $index => $column) {
            $params['columns'][$index] = [
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false'],
            ];
        }

        return $this->actingAs($actor)
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ])
            ->get(route('addresses.index', $params))
            ->assertOk()
            ->json();
    }

    private function reader(): User
    {
        return User::factory()->create()->assignRole('Superadmin');
    }

    private function customer(): User
    {
        return User::factory()->create()->assignRole('Customer');
    }
}
