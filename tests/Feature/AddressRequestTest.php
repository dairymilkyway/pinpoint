<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\User;
use App\Notifications\AddressRequestDecided;
use App\Notifications\AddressRequestRaised;
use App\Notifications\AddressRequestSettled;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AddressRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_queue_requires_authentication(): void
    {
        $this->get(route('requests.index'))->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_reach_the_request_form_when_they_have_no_address_to_edit(): void
    {
        // An edit with no address named is not a request, it is a mistake.
        $this->actingAs($this->customer())
            ->get(route('requests.create', ['type' => 'update']))
            ->assertNotFound();
    }

    public function test_the_request_form_renders_for_an_addition_and_for_an_edit(): void
    {
        $customer = $this->customer();
        $address = Address::factory()->for($customer)->create(['label' => 'Home']);

        $this->actingAs($customer)
            ->get(route('requests.create', ['type' => 'create']))
            ->assertOk()
            ->assertSee('Submit request');

        $html = $this->actingAs($customer)
            ->get(route('requests.create', ['address' => $address->id]))
            ->assertOk()
            ->assertSee('Home')
            ->getContent();

        // It is a proposal, so it carries the kind and a place to say why.
        $this->assertStringContainsString('name="type"', $html);
        $this->assertStringContainsString('name="note"', $html);

        // But not the default marker: that has its own immediate action, and a
        // proposal that could move it would move it without the owner asking.
        $this->assertStringNotContainsString('name="is_default"', $html);
    }

    /**
     * A reader writes to the directory directly, so a request from one of them
     * would be a proposal they could approve themselves - which is why asking is
     * the one permission the Superadmin does not hold. Enforced by the
     * permission rather than by hiding the button.
     */
    public function test_only_a_customer_may_raise_a_request(): void
    {
        foreach (['Superadmin', 'Admin'] as $role) {
            $reader = User::factory()->create()->assignRole($role);

            $this->actingAs($reader)
                ->get(route('requests.create', ['type' => 'create']))
                ->assertForbidden();

            $this->actingAs($reader)
                ->post(route('requests.store'), [
                    'type' => AddressRequest::TYPE_CREATE,
                    'label' => 'Warehouse',
                    'line1' => '7 Katipunan Avenue',
                    'city' => 'Quezon City',
                    'postal_code' => '1100',
                    'country' => 'Philippines',
                ])
                ->assertForbidden();

            $html = $this->actingAs($reader)->get(route('requests.index'))->assertOk()->getContent();
            $this->assertStringNotContainsString('Request a new address', $html);
        }

        $this->assertSame(0, AddressRequest::query()->count());
    }

    public function test_the_review_modal_carries_the_values_and_the_reason(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create(['label' => 'Home', 'line1' => '1 Old Road']);

        $this->actingAs($customer)
            ->post(route('requests.store'), $this->editPayload($address, ['line1' => '18B Sunrise Drive']));

        $change = AddressRequest::query()->sole();

        $html = $this->actingAs($reader)->get(route('requests.index'))->assertOk()->getContent();

        // The decision is made with the values on screen, not from memory of a
        // row: the modal it opens is the one that carries them.
        $this->assertStringContainsString('review-'.$change->id, $html);
        $this->assertStringContainsString('1 Old Road', $html);
        $this->assertStringContainsString('18B Sunrise Drive', $html);

        $this->assertStringContainsString('name="decision_note"', $html);
        $this->assertStringContainsString('Approve and apply', $html);

        // The refusal is a second modal, and the two take turns rather than
        // stacking: each names the other by id. A swap whose target does not
        // match renders fine and does nothing when clicked, so the pairing is
        // worth pinning - and the confirmation must not be nested in the review
        // modal, which is what strands a backdrop.
        $this->assertStringContainsString('data-modal-swap="reject-'.$change->id.'"', $html);
        $this->assertStringContainsString('data-modal-swap="review-'.$change->id.'"', $html);
        $this->assertStringContainsString('id="reject-'.$change->id.'"', $html);
    }

    /**
     * The reason is the one part of a request a reader wants before opening
     * anything, so the row carries it too rather than leaving it behind the
     * button. A request raised without one leaves no empty line behind.
     */
    public function test_the_row_carries_the_reason_and_an_empty_one_leaves_no_line(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();

        $quiet = Address::factory()->for($customer)->create(['label' => 'Quiet']);
        $this->actingAs($customer)->post(route('requests.store'), $this->editPayload($quiet, [
            'label' => 'Quiet edited',
        ]));

        $loud = Address::factory()->for($customer)->create(['label' => 'Loud']);
        $this->actingAs($customer)->post(route('requests.store'), $this->editPayload($loud, [
            'label' => 'Loud edited',
            'note' => 'Zztenant has moved out',
        ]));

        $html = $this->actingAs($reader)->get(route('requests.index'))->assertOk()->getContent();

        // The modal renders the reason quoted inside a <p>; only the row renders
        // it in the summary's own treatment. Matching that shape is what proves
        // the reason reached the row and not just the modal a click away.
        $this->assertStringContainsString(
            '<div class="text-dim small mt-1">Zztenant has moved out</div>',
            $html,
        );

        // The request raised with no reason renders no empty line where one would
        // have been.
        $this->assertStringNotContainsString('<div class="text-dim small mt-1"></div>', $html);
    }

    public function test_the_review_modal_is_read_only_for_the_requester(): void
    {
        $customer = $this->customer();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->post(route('requests.store'), $this->editPayload($address, ['line1' => '18B Sunrise Drive']));

        $html = $this->actingAs($customer)->get(route('requests.index'))->assertOk()->getContent();

        // They may look at what they asked for, and that is all.
        $this->assertStringContainsString('View', $html);
        $this->assertStringNotContainsString('name="decision_note"', $html);
        $this->assertStringNotContainsString('Approve and apply', $html);
    }

    public function test_a_customer_sees_their_own_requests_and_nobody_elses(): void
    {
        $mine = $this->customer();
        $theirs = $this->customer();

        $this->raise($mine, AddressRequest::TYPE_CREATE, null, ['label' => 'My Warehouse']);
        $this->raise($theirs, AddressRequest::TYPE_CREATE, null, ['label' => 'Their Warehouse']);

        $html = $this->actingAs($mine)->get(route('requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('My Warehouse', $html);
        $this->assertStringNotContainsString('Their Warehouse', $html);
    }

    public function test_a_reader_sees_the_whole_queue(): void
    {
        $reader = $this->reader();
        $this->raise($this->customer(), AddressRequest::TYPE_CREATE, null, ['label' => 'Their Warehouse']);

        $html = $this->actingAs($reader)->get(route('requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Their Warehouse', $html);
        $this->assertStringContainsString('Approve', $html);
    }

    public function test_a_customer_cannot_decide_a_request(): void
    {
        $customer = $this->customer();
        $change = $this->raise($customer, AddressRequest::TYPE_CREATE, null, ['label' => 'Warehouse']);

        $this->actingAs($customer)
            ->post(route('requests.approve', $change))
            ->assertForbidden();

        $this->assertTrue($change->fresh()->isPending());
    }

    public function test_a_customer_cannot_request_a_change_to_somebody_elses_address(): void
    {
        $other = Address::factory()->for($this->customer())->create();

        $this->actingAs($this->customer())
            ->post(route('requests.store'), $this->editPayload($other, ['line1' => '1 Elsewhere']))
            ->assertForbidden();
    }

    public function test_raising_a_request_notifies_the_approvers_and_writes_nothing_yet(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->post(route('requests.store'), $this->editPayload($address, ['line1' => '18B Sunrise Drive']))
            ->assertRedirect(route('requests.index'));

        $this->assertDatabaseHas('address_requests', [
            'user_id' => $customer->id,
            'address_id' => $address->id,
            'type' => AddressRequest::TYPE_UPDATE,
            'status' => AddressRequest::STATUS_PENDING,
        ]);

        // The whole point of the queue: the address is untouched until somebody
        // decides.
        $this->assertSame($address->line1, $address->fresh()->line1);

        Notification::assertSentTo($reader, AddressRequestRaised::class);
        Notification::assertNotSentTo($customer, AddressRequestRaised::class);
    }

    public function test_approving_an_addition_creates_the_address(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();

        $this->actingAs($customer)->post(route('requests.store'), [
            'type' => AddressRequest::TYPE_CREATE,
            'label' => 'Warehouse',
            'line1' => '7 Katipunan Avenue',
            'city' => 'Quezon City',
            'postal_code' => '1100',
            'country' => 'Philippines',
            'note' => 'A second delivery point.',
        ])->assertRedirect(route('requests.index'));

        $change = AddressRequest::query()->sole();

        $this->actingAs($reader)->post(route('requests.approve', $change))->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'user_id' => $customer->id,
            'label' => 'Warehouse',
            'line1' => '7 Katipunan Avenue',
        ]);

        $this->assertDatabaseHas('address_requests', [
            'id' => $change->id,
            'status' => AddressRequest::STATUS_APPROVED,
            'decided_by' => $reader->id,
        ]);
    }

    public function test_approving_an_edit_changes_the_address_in_place(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create(['line1' => '1 Old Road']);

        $this->actingAs($customer)
            ->post(route('requests.store'), $this->editPayload($address, ['line1' => '18B Sunrise Drive']));

        $change = AddressRequest::query()->sole();

        $this->actingAs($reader)->post(route('requests.approve', $change))->assertRedirect();

        // Written in place: an approved edit is not a new row.
        $this->assertSame(1, Address::query()->count());
        $this->assertSame('18B Sunrise Drive', $address->fresh()->line1);
    }

    public function test_approving_a_deletion_removes_the_address(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('requests.store'), [
            'type' => AddressRequest::TYPE_DELETE,
            'address_id' => $address->id,
        ])->assertRedirect(route('requests.index'));

        $change = AddressRequest::query()->sole();
        $this->assertNull($change->payload);

        $this->actingAs($reader)->post(route('requests.approve', $change))->assertRedirect();

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
        $this->assertDatabaseHas('address_requests', [
            'id' => $change->id,
            'status' => AddressRequest::STATUS_APPROVED,
        ]);
    }

    public function test_rejecting_leaves_the_address_alone_and_tells_the_requester(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create(['line1' => '1 Old Road']);

        $this->actingAs($customer)
            ->post(route('requests.store'), $this->editPayload($address, ['line1' => '18B Sunrise Drive']));

        $change = AddressRequest::query()->sole();

        $this->actingAs($reader)->post(route('requests.reject', $change), [
            'decision_note' => 'Not on the delivery route.',
        ])->assertRedirect();

        $this->assertSame('1 Old Road', $address->fresh()->line1);
        $this->assertDatabaseHas('address_requests', [
            'id' => $change->id,
            'status' => AddressRequest::STATUS_REJECTED,
            'decision_note' => 'Not on the delivery route.',
        ]);

        Notification::assertSentTo($customer, AddressRequestDecided::class);
    }

    public function test_the_other_approver_hears_that_a_request_was_settled(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $decider = $this->reader();
        $other = $this->reader();

        $this->raise($customer, AddressRequest::TYPE_CREATE, null, ['label' => 'Warehouse']);
        $change = AddressRequest::query()->sole();

        $this->actingAs($decider)->post(route('requests.reject', $change));

        // Told, so a shared queue is not worked twice. Not told back what they
        // themselves just did.
        Notification::assertSentTo($other, AddressRequestSettled::class);
        Notification::assertNotSentTo($decider, AddressRequestSettled::class);
    }

    public function test_a_second_pending_request_of_the_same_kind_is_refused(): void
    {
        $customer = $this->customer();
        $address = Address::factory()->for($customer)->create();

        $payload = $this->editPayload($address, ['line1' => '18B Sunrise Drive']);

        $this->actingAs($customer)->post(route('requests.store'), $payload)->assertRedirect();
        $this->actingAs($customer)->post(route('requests.store'), $payload)
            ->assertSessionHasErrors('address_id');

        $this->assertSame(1, AddressRequest::query()->count());
    }

    public function test_a_request_carries_no_default_marker(): void
    {
        $customer = $this->customer();
        $address = Address::factory()->for($customer)->create();

        $this->actingAs($customer)->post(route('requests.store'), array_merge(
            $this->editPayload($address, ['line1' => '18B Sunrise Drive']),
            ['is_default' => '1'],
        ));

        // The marker has its own immediate action. Letting it ride along in a
        // proposal would move it without the owner asking twice.
        $this->assertArrayNotHasKey('is_default', AddressRequest::query()->sole()->payload);
    }

    /**
     * The one write a Customer makes directly. It needs no approval because it
     * is a marker on their own book that changes nothing anyone else sees, and
     * it must leave exactly one row marked.
     */
    public function test_making_an_address_default_moves_the_marker_without_a_request(): void
    {
        $customer = $this->customer();
        $first = Address::factory()->for($customer)->create(['is_default' => true]);
        $second = Address::factory()->for($customer)->create(['is_default' => false]);

        $this->actingAs($customer)
            ->post(route('addresses.default', $second))
            ->assertRedirect(route('addresses.index'));

        $this->assertSame(1, $customer->addresses()->where('is_default', true)->count());
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);

        $this->assertSame(0, AddressRequest::query()->count());
    }

    public function test_a_customer_cannot_move_somebody_elses_default_marker(): void
    {
        $address = Address::factory()->for($this->customer())->create();

        $this->actingAs($this->customer())
            ->post(route('addresses.default', $address))
            ->assertForbidden();
    }

    public function test_an_edit_request_is_stale_once_the_address_moves(): void
    {
        $customer = $this->customer();
        $address = Address::factory()->for($customer)->create(['line1' => '1 Old Road']);

        $this->actingAs($customer)
            ->post(route('requests.store'), $this->editPayload($address, ['line1' => '18B Sunrise Drive']));

        $change = AddressRequest::query()->sole();
        $this->assertFalse($change->isStale());

        // Somebody else got there first.
        $address->update(['line1' => '2 Other Road']);
        $this->assertTrue($change->fresh()->isStale());

        // A move the request is not about does not count. Marking a request out
        // of date for a default toggle would train a reader to ignore the flag.
        $address->update(['line1' => '1 Old Road', 'is_default' => true]);
        $this->assertFalse($change->fresh()->isStale());

        // Flagged, not cancelled: a reader who has seen the clash can still
        // approve it, and then the proposal is what lands.
        $address->update(['line1' => '2 Other Road']);

        $this->actingAs($this->reader())
            ->post(route('requests.approve', $change->fresh()))
            ->assertRedirect();

        $this->assertSame('18B Sunrise Drive', $address->fresh()->line1);
    }

    public function test_a_request_whose_address_is_gone_says_so_and_cannot_be_approved(): void
    {
        $customer = $this->customer();
        $reader = $this->reader();
        $address = Address::factory()->for($customer)->create();

        $this->raise($customer, AddressRequest::TYPE_UPDATE, $address, ['label' => 'Home']);
        $change = AddressRequest::query()->sole();

        $address->delete();
        $this->assertTrue($change->fresh()->isOrphaned());

        $this->actingAs($reader)->post(route('requests.approve', $change))->assertSessionHas('error');
        $this->assertTrue($change->fresh()->isPending());

        $html = $this->actingAs($reader)->get(route('requests.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Address is gone', $html);
    }

    private function customer(): User
    {
        return User::factory()->create()->assignRole('Customer');
    }

    private function reader(): User
    {
        return User::factory()->create()->assignRole('Superadmin');
    }

    /** @param array<string, mixed> $payload */
    private function raise(User $user, string $type, ?Address $address, array $payload): AddressRequest
    {
        return AddressRequest::create([
            'user_id' => $user->id,
            'address_id' => $address?->id,
            'type' => $type,
            'payload' => $payload,
            'before' => $address?->snapshot(),
        ]);
    }

    /**
     * The body the request form posts for an edit: every field, as the form
     * would send it, with the differences layered on top.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayload(Address $address, array $overrides = []): array
    {
        // Read back from the database first. A freshly created model still holds
        // the city code as an integer, because the geo dataset is keyed by
        // numeric strings and PHP turns those keys into ints - and the form
        // always submits a string.
        $address = $address->fresh() ?? $address;

        return array_merge([
            'type' => AddressRequest::TYPE_UPDATE,
            'address_id' => $address->id,
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
}
