<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the root route, which serves a public landing page to guests and
 * forwards authenticated users to their dashboard.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_guest_sees_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Every address your team needs, in one place.');
    }

    public function test_the_landing_page_offers_a_way_to_sign_in(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('login'));
    }

    public function test_an_authenticated_user_is_forwarded_to_the_dashboard(): void
    {
        $user = User::factory()->create()->assignRole('Customer');

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('home'));
    }
}
