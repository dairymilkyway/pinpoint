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

    public function test_customer_can_open_the_index(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('addresses.index'))
            ->assertOk();
    }

    public function test_customer_cannot_open_the_create_form(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('addresses.create'))
            ->assertForbidden();
    }

    public function test_customer_cannot_store_an_address(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->post(route('addresses.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_customer_cannot_open_the_edit_form(): void
    {
        $address = Address::factory()->create();

        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('addresses.edit', $address))
            ->assertForbidden();
    }

    public function test_customer_cannot_update_an_address(): void
    {
        $address = Address::factory()->create(['city' => 'Original']);

        $this->actingAs($this->userWithRole('Customer'))
            ->put(route('addresses.update', $address), $this->validPayload(['city' => 'Tampered']))
            ->assertForbidden();

        $this->assertSame('Original', $address->fresh()->city);
    }

    public function test_customer_cannot_delete_an_address(): void
    {
        $address = Address::factory()->create();

        $this->actingAs($this->userWithRole('Customer'))
            ->delete(route('addresses.destroy', $address))
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', ['id' => $address->id]);
    }

    public function test_customer_cannot_export(): void
    {
        Address::factory()->count(3)->create();

        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('addresses.index', ['action' => 'excel']))
            ->assertForbidden();
    }

    public function test_admin_can_create_and_edit_but_cannot_delete(): void
    {
        $admin = $this->userWithRole('Admin');
        $own = Address::factory()->for($admin)->create();

        $this->actingAs($admin)->get(route('addresses.create'))->assertOk();
        $this->actingAs($admin)->get(route('addresses.edit', $own))->assertOk();
        $this->actingAs($admin)->delete(route('addresses.destroy', $own))->assertForbidden();
    }

    public function test_admin_can_open_another_owners_address_by_url(): void
    {
        $admin = $this->userWithRole('Admin');

        // The Admin reads the whole book, so the row it cannot see in its own
        // listing is not a row it is refused - the policy and the scope agree.
        $this->actingAs($admin)
            ->get(route('addresses.edit', Address::factory()->create()))
            ->assertOk();
    }

    public function test_admin_can_update_another_owners_address(): void
    {
        $admin = $this->userWithRole('Admin');
        $theirs = Address::factory()->create(['city' => 'Original']);

        $this->actingAs($admin)
            ->put(route('addresses.update', $theirs), $this->validPayload(['city' => 'Revised']))
            ->assertRedirect();

        $this->assertSame('Revised', $theirs->fresh()->city);
    }

    public function test_a_customer_cannot_open_another_owners_address_by_url(): void
    {
        $customer = $this->userWithRole('Customer');

        // The narrow role still needs the guessable id refused, not just hidden.
        $this->actingAs($customer)
            ->get(route('addresses.edit', Address::factory()->create()))
            ->assertForbidden();
    }

    public function test_a_customer_cannot_update_another_owners_address(): void
    {
        $customer = $this->userWithRole('Customer');
        $theirs = Address::factory()->create(['city' => 'Original']);

        $this->actingAs($customer)
            ->put(route('addresses.update', $theirs), $this->validPayload(['city' => 'Tampered']))
            ->assertForbidden();

        $this->assertSame('Original', $theirs->fresh()->city);
    }

    public function test_superadmin_can_open_any_address(): void
    {
        $superadmin = $this->userWithRole('Superadmin');

        $this->actingAs($superadmin)
            ->get(route('addresses.edit', Address::factory()->create()))
            ->assertOk();
    }

    public function test_a_customer_only_reaches_its_own_address_page(): void
    {
        $customer = $this->userWithRole('Customer');
        $other = $this->userWithRole('Customer');

        // The narrow role's own page is fine; anyone else's is refused outright
        // rather than answered with an empty table.
        $this->actingAs($customer)
            ->get(route('addresses.user', $other))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('addresses.user', $customer))
            ->assertOk();
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
