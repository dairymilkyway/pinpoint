<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_all_three_roles_reach_the_dashboard(): void
    {
        foreach (['Admin', 'Manager', 'Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('Dashboard');
        }
    }

    public function test_the_signed_in_landing_redirects_to_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('landing'))
            ->assertRedirect(route('home'));
    }

    public function test_only_admin_sees_the_access_panel(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Access');

        foreach (['Manager', 'Viewer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee('>Access<', false);
        }
    }

    public function test_only_roles_that_may_create_get_the_new_address_action(): void
    {
        foreach (['Admin', 'Manager'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('New address');
        }

        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('New address');
    }

    public function test_the_counts_reflect_the_stored_addresses(): void
    {
        $owner = $this->userWithRole('Admin');

        Address::factory()->count(3)->create([
            'user_id' => $owner->id,
            'city_code' => '1380100000',
            'region_code' => '1300000000',
            'latitude' => 14.65,
            'longitude' => 120.98,
        ]);

        Address::factory()->create([
            'user_id' => $owner->id,
            'city_code' => '0722000000',
            'region_code' => '0700000000',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('home'))->assertOk();

        // Four addresses, one owner, two distinct cities, two distinct regions.
        $response->assertSee('4');
        $response->assertSee('75%');
        $response->assertSee('3 of 4 addresses have coordinates.');
    }

    public function test_an_account_with_no_role_gets_a_dashboard_not_a_403(): void
    {
        // Registration assigns no role, and the landing page must not become a
        // dead end for that account.
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('nothing has been shared with you yet');
    }

    public function test_an_empty_directory_renders_without_errors(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('No addresses on file yet.')
            ->assertSee('0%');
    }
}
