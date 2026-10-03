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

    private function customer(): User
    {
        return User::factory()->create()->assignRole('Customer');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'label' => 'Head Office',
            'line1' => '99 Ayala Avenue',
            'line2' => 'Floor 12',
            'city' => 'Makati',
            'state' => 'Metro Manila',
            'postal_code' => '1226',
            'country' => 'Philippines',
            'is_default' => '1',
        ];
    }

    /**
     * A reader of the whole book holds no addresses of their own, so the one
     * they make lands on the account they named and on no other.
     */
    public function test_a_reader_creates_an_address_on_the_chosen_account(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->customer();

        $this->actingAs($superadmin)
            ->post(route('addresses.store', ['user' => $customer->id]), $this->validPayload())
            // A reader of the whole book lands on the owner's page, not the
            // users list, so the address they just made is on screen.
            ->assertRedirect(route('addresses.user', $customer))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $customer->id,
            'label' => 'Head Office',
            'city' => 'Makati',
            'is_default' => true,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('addresses.store', ['user' => $this->customer()->id]), ['label' => ''])
            ->assertSessionHasErrors(['label', 'line1', 'city', 'postal_code', 'country']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_creating_starts_by_choosing_the_account(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs($this->superadmin())
            ->get(route('addresses.create'))->assertOk()->getContent();

        // The picker and nothing else: no address to fill in before the account
        // it belongs to has been settled.
        $this->assertStringContainsString('Whose address is this?', $html);
        $this->assertStringContainsString(e($customer->name), $html);
        $this->assertStringNotContainsString('name="line1"', $html);
    }

    public function test_the_form_is_built_for_the_chosen_account(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs($this->superadmin())
            ->get(route('addresses.create', ['user' => $customer->id]))->assertOk()->getContent();

        $this->assertStringContainsString(e($customer->name), $html);
        $this->assertStringContainsString('name="line1"', $html);
        // The account rides in the action, not in a field the reader could edit.
        $this->assertStringContainsString(route('addresses.store', ['user' => $customer->id]), $html);
        $this->assertStringNotContainsString('name="user_id"', $html);
    }

    public function test_creating_without_choosing_an_account_writes_nothing(): void
    {
        $this->actingAs($this->superadmin())
            ->post(route('addresses.store'), $this->validPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_the_chosen_account_must_be_one_that_can_hold_addresses(): void
    {
        // A reader owns nothing, so naming one names no owner at all.
        $reader = $this->superadmin();

        $this->actingAs($this->superadmin())
            ->post(route('addresses.store', ['user' => $reader->id]), $this->validPayload())
            ->assertNotFound();

        $this->actingAs($this->superadmin())
            ->post(route('addresses.store', ['user' => 999999]), $this->validPayload())
            ->assertNotFound();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_an_unusable_account_offers_the_choice_back(): void
    {
        // Not a form locked onto an account that cannot take the address: the
        // reader gets the picker instead of a page with no way forward.
        $reader = $this->superadmin();
        $this->customer();

        $html = $this->actingAs($this->superadmin())
            ->get(route('addresses.create', ['user' => $reader->id]))->assertOk()->getContent();

        $this->assertStringContainsString('Whose address is this?', $html);
        $this->assertStringNotContainsString('name="line1"', $html);
    }

    public function test_the_new_address_button_carries_the_account_when_one_is_open(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs($this->superadmin())
            ->get(route('addresses.user', $customer))->assertOk()->getContent();

        $this->assertStringContainsString(
            route('addresses.create', ['user' => $customer->id]),
            $html,
        );
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
