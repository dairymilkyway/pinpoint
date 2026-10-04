<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\User;
use App\Notifications\AddressRequestRaised;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_inbox_requires_authentication(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_the_inbox_lists_what_the_account_was_told(): void
    {
        $customer = $this->customer();
        $this->raise($customer);

        $html = $this->actingAs($customer)
            ->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('asked to add an address', $html);
    }

    public function test_one_account_cannot_open_another_accounts_notification(): void
    {
        $mine = $this->customer();
        $theirs = $this->customer();

        $this->raise($theirs, $mine);

        $notification = $mine->notifications()->sole();

        // Read through the signed-in account's own relation, so somebody else's
        // id is not found rather than forbidden - the id is not a fact about the
        // other account worth confirming.
        $this->actingAs($theirs)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        // The detail page reads through the same relation, so it answers the
        // same way rather than becoming a second, differently-guarded door onto
        // somebody else's notification.
        $this->actingAs($theirs)
            ->get(route('notifications.show', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_a_notification_whose_request_is_gone_still_renders(): void
    {
        $customer = $this->customer();
        $change = $this->raise($customer);

        // The payload was written as a snapshot precisely so it keeps making
        // sense after the thing it is about has gone. If the page ever starts
        // re-reading the request instead, this is what catches it.
        $change->delete();

        $notification = $customer->notifications()->sole();

        $html = $this->actingAs($customer)
            ->get(route('notifications.show', $notification->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('asked to add an address', $html);
    }

    public function test_opening_a_notification_marks_it_read_and_lands_on_its_page(): void
    {
        $customer = $this->customer();
        $this->raise($customer);

        $notification = $customer->notifications()->sole();
        $this->assertNull($notification->read_at);

        // The destination used to be the notification's stored url, which for a
        // decided request was the whole queue - the reader landed on a list with
        // no sign of which row they had been told about. It is now the
        // notification's own page.
        $this->actingAs($customer)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.show', $notification->id));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_notification_with_a_foreign_link_still_opens_its_own_page(): void
    {
        $customer = $this->customer();

        $notification = $customer->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => AddressRequestRaised::class,
            'data' => ['kind' => 'request.raised', 'url' => 'https://example.test/phish'],
        ]);

        // The links are written by this app, but a redirect built from a stored
        // string is the shape of an open redirect. Opening no longer redirects to
        // that string at all, so a foreign url cannot steer the navigation - the
        // comparison that used to guard the redirect now guards the link the
        // detail page renders, which is why this case also asserts on that page.
        $this->actingAs($customer)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.show', $notification->id));

        $html = $this->actingAs($customer)
            ->get(route('notifications.show', $notification->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('https://example.test/phish', $html);
    }

    /**
     * The bell carries a count at zero as well as above it. A badge that only
     * appears once the number is non-zero is read as decoration, so the first
     * time it does appear it looks like an alert rather than a total.
     */
    public function test_the_bell_carries_the_count_at_zero_and_above_it(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs($customer)->get(route('home'))->assertOk()->getContent();

        // The pill is the bell's alone, so the class pairing is what tells the
        // two states apart rather than a badge that happens to be on the page.
        $this->assertStringContainsString('badge rounded-pill badge-soft', $html);
        $this->assertStringContainsString('No unread notifications', $html);

        $this->raise($customer);

        // A fresh instance: the first render read unreadNotifications through
        // the model the test is holding, and a relation once read is cached on
        // it. A real request builds its own model, so only the test can be
        // caught out this way.
        $html = $this->actingAs($customer->fresh())->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('badge rounded-pill badge-amber', $html);
        $this->assertStringContainsString('1 unread notifications', $html);
    }

    public function test_marking_everything_read_leaves_nothing_unread(): void
    {
        $customer = $this->customer();
        $this->raise($customer);
        $this->raise($customer, $customer, AddressRequest::TYPE_DELETE);

        $this->assertSame(2, $customer->unreadNotifications()->count());

        $this->actingAs($customer)->post(route('notifications.readAll'))->assertRedirect();

        $this->assertSame(0, $customer->unreadNotifications()->count());
    }

    /** Raise a request and notify the given account, as the controller does. */
    private function raise(User $requester, ?User $notify = null, string $type = AddressRequest::TYPE_CREATE): AddressRequest
    {
        $address = $type === AddressRequest::TYPE_CREATE
            ? null
            : Address::factory()->for($requester)->create();

        $change = AddressRequest::create([
            'user_id' => $requester->id,
            'address_id' => $address?->id,
            'type' => $type,
            'payload' => ['label' => 'Warehouse'],
            'before' => $address?->snapshot(),
        ]);

        ($notify ?? $requester)->notify(new AddressRequestRaised($change));

        return $change;
    }

    private function customer(): User
    {
        return User::factory()->create()->assignRole('Customer');
    }
}
