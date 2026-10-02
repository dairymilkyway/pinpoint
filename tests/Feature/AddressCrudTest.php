<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('Admin');
    }

    public function test_admin_can_create_an_address(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('addresses.store'), [
                'label' => 'Head Office',
                'line1' => '99 Ayala Avenue',
                'line2' => 'Floor 12',
                'city' => 'Makati',
                'state' => 'Metro Manila',
                'postal_code' => '1226',
                'country' => 'Philippines',
                'is_default' => '1',
            ])
            ->assertRedirect(route('addresses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $admin->id,
            'label' => 'Head Office',
            'city' => 'Makati',
            'is_default' => true,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->admin())
            ->post(route('addresses.store'), ['label' => ''])
            ->assertSessionHasErrors(['label', 'line1', 'city', 'postal_code', 'country']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_admin_can_update_an_address(): void
    {
        $address = Address::factory()->create(['label' => 'Old', 'is_default' => true]);

        $this->actingAs($this->admin())
            ->put(route('addresses.update', $address), [
                'label' => 'New',
                'line1' => $address->line1,
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                // is_default omitted: an unchecked box must clear the flag.
            ])
            ->assertRedirect(route('addresses.index'))
            ->assertSessionHas('success');

        $this->assertSame('New', $address->fresh()->label);
        $this->assertFalse($address->fresh()->is_default);
    }

    public function test_admin_can_delete_an_address(): void
    {
        $address = Address::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('addresses.destroy', $address))
            ->assertRedirect(route('addresses.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_deleting_a_user_cascades_to_their_addresses(): void
    {
        $address = Address::factory()->create();

        $address->user->delete();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_admin_sees_the_create_button_and_row_actions(): void
    {
        Address::factory()->create();

        $html = $this->actingAs($this->admin())->get(route('addresses.index'))->assertOk()->getContent();

        // Assert on the route rather than the button copy, so the gate stays
        // under test even if the label is reworded.
        $this->assertStringContainsString(route('addresses.create'), $html);
        $this->assertStringContainsString('addresses-table', $html);
    }

    public function test_viewer_index_omits_the_create_button(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');

        $html = $this->actingAs($viewer)->get(route('addresses.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('addresses.create'), $html);
    }
}
