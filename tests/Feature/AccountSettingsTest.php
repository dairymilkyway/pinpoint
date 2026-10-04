<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rbac;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The Customer's own account page: profile, password, and closure.
 *
 * Access is by role, not by a permission, because CUSTOMER_PERMISSIONS carries a
 * documented "nothing that writes" invariant and an account.manage permission
 * would falsify it. That makes the Superadmin/Admin 403 cases the real
 * specification here, not an afterthought.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    /** A user with a known password and no faker apostrophes in the assertions. */
    private function account(string $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Nena Cruz',
            'email' => 'nena@example.test',
            'phone' => '+639171234567',
        ], $attributes))->assignRole($role);
    }

    public function test_a_customer_can_open_their_account_page(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        $this->actingAs($customer)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee($customer->email)
            ->assertSee('+639171234567');
    }

    public function test_a_reader_is_refused_every_account_action(): void
    {
        // Both reader roles already hold create and edit over the whole book, so
        // the temptation is to let them in. They are refused, and on every verb -
        // a guard that only covers GET would leave the writes open.
        foreach ([Rbac::SUPERADMIN_ROLE, Rbac::ADMIN_ROLE] as $role) {
            $reader = $this->account($role, ['email' => strtolower($role).'@example.test']);

            $this->actingAs($reader)->get(route('account.edit'))->assertForbidden();

            $this->actingAs($reader)->put(route('account.update'), [
                'name' => 'Someone Else',
                'email' => 'someone@example.test',
                'phone' => '09171234567',
            ])->assertForbidden();

            $this->actingAs($reader)->put(route('account.password'), [
                'current_password' => 'password',
                'password' => 'a-new-secret-1234',
                'password_confirmation' => 'a-new-secret-1234',
            ])->assertForbidden();

            $this->actingAs($reader)->delete(route('account.destroy'))->assertForbidden();
        }
    }

    public function test_a_customer_updates_their_details(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        $this->actingAs($customer)->put(route('account.update'), [
            'name' => 'Nena Cruz-Reyes',
            'email' => 'nena.reyes@example.test',
            'phone' => '0917 123 4567',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $customer->refresh();

        $this->assertSame('Nena Cruz-Reyes', $customer->name);
        $this->assertSame('nena.reyes@example.test', $customer->email);
        // The same rule that guards registration writes the stored form here, so
        // a spaced entry lands canonical rather than as the owner typed it.
        $this->assertSame('+639171234567', $customer->phone);
    }

    public function test_the_owner_may_keep_their_own_email(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        // Rule::unique()->ignore(self): without the ignore, re-saving the profile
        // would collide with the account's own row.
        $this->actingAs($customer)->put(route('account.update'), [
            'name' => 'Nena Cruz',
            'email' => 'nena@example.test',
            'phone' => '+639171234567',
        ])->assertSessionHasNoErrors();
    }

    public function test_another_accounts_email_is_refused(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);
        $this->account(Rbac::CUSTOMER_ROLE, ['email' => 'taken@example.test']);

        $this->actingAs($customer)->put(route('account.update'), [
            'name' => 'Nena Cruz',
            'email' => 'taken@example.test',
            'phone' => '+639171234567',
        ])->assertSessionHasErrors('email');

        $this->assertSame('nena@example.test', $customer->refresh()->email);
    }

    public function test_a_landline_is_refused_on_the_profile(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        $this->actingAs($customer)->put(route('account.update'), [
            'name' => 'Nena Cruz',
            'email' => 'nena@example.test',
            'phone' => '0212345678',
        ])->assertSessionHasErrors('phone');
    }

    public function test_a_wrong_current_password_is_refused(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        // The discriminator for the current_password rule: drop it and a stolen
        // session can set a new password without knowing the old one.
        $this->actingAs($customer)->put(route('account.password'), [
            'current_password' => 'not-the-password',
            'password' => 'a-new-secret-1234',
            'password_confirmation' => 'a-new-secret-1234',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $customer->refresh()->password));
    }

    public function test_a_customer_changes_their_password(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        $this->actingAs($customer)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'a-new-secret-1234',
            'password_confirmation' => 'a-new-secret-1234',
        ])->assertSessionHasNoErrors();

        // Hashed exactly once: the model casts password to 'hashed', so a second
        // Hash::make in the controller would store a hash of a hash and this
        // check would fail.
        $this->assertTrue(Hash::check('a-new-secret-1234', $customer->refresh()->password));
    }

    public function test_a_mismatched_confirmation_is_refused(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        $this->actingAs($customer)->put(route('account.password'), [
            'current_password' => 'password',
            'password' => 'a-new-secret-1234',
            'password_confirmation' => 'a-different-one-1234',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $customer->refresh()->password));
    }

    public function test_deactivating_the_account_soft_deletes_it_and_signs_out(): void
    {
        $customer = $this->account(Rbac::CUSTOMER_ROLE);

        $this->actingAs($customer)
            ->delete(route('account.destroy'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // assertSoftDeleted, not assertDatabaseMissing: the row is still there and
        // a Superadmin can restore it, so a hard delete would satisfy the wrong
        // assertion just as well.
        $this->assertSoftDeleted('users', ['id' => $customer->id]);
    }
}
