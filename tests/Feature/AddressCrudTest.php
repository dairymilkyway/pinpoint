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

    private function superadmin(): User
    {
        return User::factory()->create()->assignRole('Superadmin');
    }

    public function test_superadmin_can_create_an_address(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)
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
            // A reader of the whole book lands on the owner's page, not the
            // users list, so the address they just made is on screen.
            ->assertRedirect(route('addresses.user', $superadmin))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $superadmin->id,
            'label' => 'Head Office',
            'city' => 'Makati',
            'is_default' => true,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('addresses.store'), ['label' => ''])
            ->assertSessionHasErrors(['label', 'line1', 'city', 'postal_code', 'country']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_superadmin_can_update_an_address(): void
    {
        $address = Address::factory()->create(['label' => 'Old', 'is_default' => true]);

        $this->actingAs($this->superadmin())
            ->put(route('addresses.update', $address), [
                'label' => 'New',
                'line1' => $address->line1,
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                // is_default omitted: an unchecked box must clear the flag.
            ])
            ->assertRedirect(route('addresses.user', $address->user))
            ->assertSessionHas('success');

        $this->assertSame('New', $address->fresh()->label);
        $this->assertFalse($address->fresh()->is_default);
    }

    public function test_superadmin_can_delete_an_address(): void
    {
        $address = Address::factory()->create();

        $this->actingAs($this->superadmin())
            ->delete(route('addresses.destroy', $address))
            ->assertRedirect(route('addresses.user', $address->user))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_deleting_a_user_cascades_to_their_addresses(): void
    {
        $address = Address::factory()->create();

        $address->user->delete();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_superadmin_gets_the_users_list_with_the_create_button(): void
    {
        Address::factory()->create();

        $html = $this->actingAs($this->superadmin())->get(route('addresses.index'))->assertOk()->getContent();

        // Assert on the route rather than the button copy, so the gate stays
        // under test even if the label is reworded.
        $this->assertStringContainsString(route('addresses.create'), $html);

        // The whole book gets the users list, not a flat address table.
        $this->assertStringContainsString('users-table', $html);
        $this->assertStringNotContainsString('addresses-table', $html);
    }

    public function test_the_opened_users_page_carries_the_address_table(): void
    {
        $superadmin = $this->superadmin();
        Address::factory()->for($superadmin)->create();

        $html = $this->actingAs($superadmin)
            ->get(route('addresses.user', $superadmin))->assertOk()->getContent();

        $this->assertStringContainsString('addresses-table', $html);
        $this->assertStringNotContainsString('users-table', $html);
    }

    public function test_customer_index_omits_the_create_button(): void
    {
        $customer = User::factory()->create()->assignRole('Customer');

        $html = $this->actingAs($customer)->get(route('addresses.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('addresses.create'), $html);
    }
}
