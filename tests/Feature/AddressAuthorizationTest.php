<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressAuthorizationTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
    }

    public function test_viewer_can_open_the_index(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('addresses.index'))
            ->assertOk();
    }

    public function test_viewer_cannot_open_the_create_form(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('addresses.create'))
            ->assertForbidden();
    }

    public function test_viewer_cannot_store_an_address(): void
    {
        $this->actingAs($this->userWithRole('Viewer'))
            ->post(route('addresses.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_viewer_cannot_open_the_edit_form(): void
    {
        $address = Address::factory()->create();

        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('addresses.edit', $address))
            ->assertForbidden();
    }

    public function test_viewer_cannot_update_an_address(): void
    {
        $address = Address::factory()->create(['city' => 'Original']);

        $this->actingAs($this->userWithRole('Viewer'))
            ->put(route('addresses.update', $address), $this->validPayload(['city' => 'Tampered']))
            ->assertForbidden();

        $this->assertSame('Original', $address->fresh()->city);
    }

    public function test_viewer_cannot_delete_an_address(): void
    {
        $address = Address::factory()->create();

        $this->actingAs($this->userWithRole('Viewer'))
            ->delete(route('addresses.destroy', $address))
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_viewer_cannot_export(): void
    {
        Address::factory()->count(3)->create();

        $this->actingAs($this->userWithRole('Viewer'))
            ->get(route('addresses.index', ['action' => 'excel']))
            ->assertForbidden();
    }

    public function test_manager_can_create_and_edit_but_cannot_delete(): void
    {
        $manager = $this->userWithRole('Manager');
        $address = Address::factory()->create();

        $this->actingAs($manager)->get(route('addresses.create'))->assertOk();
        $this->actingAs($manager)->get(route('addresses.edit', $address))->assertOk();
        $this->actingAs($manager)->delete(route('addresses.destroy', $address))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'line1' => '1 Example Street',
            'line2' => null,
            'city' => 'Manila',
            'state' => null,
            'postal_code' => '1000',
            'country' => 'Philippines',
            'is_default' => false,
        ], $overrides);
    }
}
