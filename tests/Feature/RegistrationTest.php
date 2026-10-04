<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rbac;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers what a self-registered account can actually do. Registration used to
 * create a user with no role at all, which left the landing page's "Create an
 * account" path ending in an account that could see nothing.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function register(array $overrides = []): void
    {
        $this->post(route('register'), array_merge([
            'name' => 'Nena Cruz',
            'email' => 'nena@example.test',
            'phone' => '09171234567',
            'password' => 'register-secret-1234',
            'password_confirmation' => 'register-secret-1234',
        ], $overrides))->assertRedirect(route('home'));
    }

    public function test_registration_signs_the_user_in(): void
    {
        $this->register();

        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', ['email' => 'nena@example.test']);
    }

    public function test_registration_grants_the_customer_role(): void
    {
        $this->register();

        $user = User::where('email', 'nena@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole(Rbac::CUSTOMER_ROLE));
        $this->assertSame(1, $user->getRoleNames()->count());
    }

    public function test_a_new_account_lands_on_a_usable_dashboard(): void
    {
        $this->register();

        // The directory is reachable and the "nothing has been shared with you"
        // state is not what a fresh account sees.
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('nothing has been shared with you yet');

        $this->get(route('addresses.index'))->assertOk();
    }

    public function test_a_new_account_cannot_write(): void
    {
        $this->register();

        // Customer is the least privileged role, so registration must not hand out
        // anything more than read access.
        $this->get(route('addresses.create'))->assertForbidden();
        $this->get(route('rbac.index'))->assertForbidden();
    }
}
