<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_log_needs_authentication(): void
    {
        $this->get(route('audit.index'))->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_read_the_log(): void
    {
        $this->actingAs($this->customer())
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    public function test_both_managing_roles_can_read_the_log(): void
    {
        foreach (['Superadmin', 'Admin'] as $role) {
            $this->actingAs(User::factory()->create()->assignRole($role))
                ->get(route('audit.index'))
                ->assertOk();
        }
    }

    public function test_creating_an_address_is_recorded_with_who_did_it(): void
    {
        $reader = $this->reader();

        $this->actingAs($reader)->post(route('addresses.store'), $this->newPayload([
            'label' => 'Warehouse',
        ]))->assertRedirect();

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditLog::ADDRESS_CREATED, $log->event);
        $this->assertSame($reader->id, $log->actor_id);
        $this->assertSame('Warehouse', $log->after['label']);

        // Nothing to compare against: a creation has one side.
        $this->assertNull($log->before);
    }

    /**
     * An Admin's own edit, not request traffic: without this the most powerful
     * writes in the app would be the only ones with no record.
     */
    public function test_an_edit_records_only_the_fields_that_moved(): void
    {
        $reader = User::factory()->create()->assignRole('Admin');
        $address = Address::factory()->create(['line1' => '1 Old Road', 'label' => 'Home']);

        $this->actingAs($reader)->put(route('addresses.update', $address), $this->payload($address, [
            'line1' => '18B Sunrise Drive',
        ]))->assertRedirect();

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditLog::ADDRESS_UPDATED, $log->event);
        // Only the field that moved. The form posts every field, so a diff that
        // logged the whole body would be a record of what the client sent rather
        // than of what changed.
        $this->assertSame(['line1'], array_keys($log->after));
        $this->assertSame('1 Old Road', $log->before['line1']);
        $this->assertSame('18B Sunrise Drive', $log->after['line1']);
    }

    public function test_deleting_an_address_keeps_what_it_held(): void
    {
        $reader = $this->reader();
        $address = Address::factory()->create(['label' => 'Billing']);

        $this->actingAs($reader)->delete(route('addresses.destroy', $address))->assertRedirect();

        $log = AuditLog::query()->sole();

        $this->assertSame(AuditLog::ADDRESS_DELETED, $log->event);
        // The row is gone; the record of it is not.
        $this->assertSame('Billing', $log->before['label']);
        $this->assertNull($log->after);
    }

    public function test_a_customers_default_toggle_is_recorded_too(): void
    {
        $customer = $this->customer();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->post(route('addresses.default', $address))
            ->assertRedirect(route('addresses.index'));

        $log = AuditLog::query()->sole();

        // The one write a Customer performs directly, and so the one that would
        // otherwise be the only change nobody could account for.
        $this->assertSame(AuditLog::ADDRESS_UPDATED, $log->event);
        $this->assertSame($customer->id, $log->actor_id);
        $this->assertTrue($log->after['is_default']);
    }

    public function test_every_request_event_is_recorded(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('requests.store'), [
            'type' => AddressRequest::TYPE_DELETE,
            'address_id' => $address->id,
        ]);

        $change = AddressRequest::query()->sole();
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLog::REQUEST_RAISED]);

        $this->actingAs($reader)->post(route('requests.approve', $change));
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLog::REQUEST_APPROVED,
            'actor_id' => $reader->id,
        ]);

        // A rejected request is refused, not erased: it is the record of a
        // decision somebody may have to explain.
        $this->actingAs($customer)->post(route('requests.store'), [
            'type' => AddressRequest::TYPE_CREATE,
            'label' => 'Warehouse',
            'line1' => '7 Katipunan Avenue',
            'city' => 'Quezon City',
            'postal_code' => '1100',
            'country' => 'Philippines',
        ]);

        $this->actingAs($reader)->post(route('requests.reject',
            AddressRequest::query()->where('type', AddressRequest::TYPE_CREATE)->sole(),
        ));

        $this->assertDatabaseHas('audit_logs', ['event' => AuditLog::REQUEST_REJECTED]);
    }

    public function test_the_screen_shows_the_history_and_filters_by_event(): void
    {
        $reader = $this->reader();
        $address = Address::factory()->create(['line1' => '1 Old Road']);

        $this->actingAs($reader)->put(route('addresses.update', $address), $this->payload($address, [
            'line1' => '18B Sunrise Drive',
        ]));

        $html = $this->actingAs($reader)->get(route('audit.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Address edited', $html);
        $this->assertStringContainsString('18B Sunrise Drive', $html);

        // The filter narrows to the event it names.
        $this->actingAs($reader)->get(route('audit.index', ['event' => AuditLog::ADDRESS_DELETED]))
            ->assertOk()
            ->assertDontSee('18B Sunrise Drive');
    }

    /**
     * The body an address form posts, taken from the address itself so the only
     * difference is the one the test names.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Address $address, array $overrides = []): array
    {
        // Read back from the database first. A freshly created model still holds
        // the city code as an integer, because the geo dataset is keyed by
        // numeric strings and PHP turns those keys into ints - and the form
        // always submits a string.
        $address = $address->fresh() ?? $address;

        return array_merge([
            'label' => $address->label,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'region_code' => $address->region_code,
            'province_code' => $address->province_code,
            'city_code' => $address->city_code,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ], $overrides);
    }

    /**
     * The body for a creation, which has no address to copy from.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'line1' => '1 Old Road',
            'city' => 'Quezon City',
            'state' => 'Metro Manila',
            'postal_code' => '1100',
            'country' => 'Philippines',
        ], $overrides);
    }

    private function customer(): User
    {
        return User::factory()->create()->assignRole('Customer');
    }

    private function reader(): User
    {
        return User::factory()->create()->assignRole('Superadmin');
    }
}
