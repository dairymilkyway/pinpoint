<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_profile_page_shows_the_accounts_details(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $alice = User::factory()->create([
            'name' => 'Alice Anders',
            'email' => 'alice@example.test',
        ])->assignRole('Customer');

        $html = $this->profile($reader, $alice);

        $this->assertStringContainsString('Alice Anders', $html);
        $this->assertStringContainsString('alice@example.test', $html);
        $this->assertStringContainsString('Customer', $html);

        // Two letters for the avatar, and the join date beside them.
        $this->assertMatchesRegularExpression('/class="avatar"[^>]*>AA</', $html);
        $this->assertStringContainsString($alice->created_at->format('M j, Y'), $html);

        $this->assertStringContainsString(route('addresses.index'), $html);
    }

    /**
     * The claim that matters: every figure describes the account on screen, not
     * the reader and not the other owner in the fixture.
     */
    public function test_every_figure_is_scoped_to_the_account_on_screen(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $alice = User::factory()->create();

        // Two regions, so a leak would show as a second bar rather than as a
        // bigger one, and a wrong count would show as a bigger total.
        Address::factory()->count(3)->for($alice)->create(['region_code' => '1300000000']);
        Address::factory()->count(5)->create(['region_code' => '0700000000']);

        $html = $this->profile($reader, $alice);

        $this->assertCount(1, $chart = $this->points($html, 'data-region-chart'));
        $this->assertSame(3, $chart[0]['value']);

        $this->assertCount(3, $this->points($html, 'data-address-map'));

        // The cards and the coverage line read the same three rows.
        $this->assertStringContainsString('3 of 3 addresses have coordinates', $html);
        $this->assertStringContainsString('held by this account', $html);
    }

    public function test_the_map_pins_name_nobody(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $alice = User::factory()->create();
        Address::factory()->count(2)->for($alice)->create();

        $pins = $this->points($this->profile($reader, $alice), 'data-address-map');

        // One owner's page: naming the owner on every pin would repeat the name
        // already in the page heading.
        $this->assertNull($pins[0]['owner']);
        $this->assertNotEmpty($pins[0]['city']);
        $this->assertNotNull($pins[0]['lat']);
    }

    public function test_the_profile_keeps_the_address_table_and_its_export(): void
    {
        $reader = $this->userWithRole('Superadmin');
        $alice = User::factory()->create();
        Address::factory()->for($alice)->create();

        $html = $this->profile($reader, $alice);

        $this->assertStringContainsString('addresses-table', $html);
        $this->assertStringNotContainsString('users-table', $html);
        $this->assertStringContainsString('Export to Excel', $html);
    }

    public function test_a_customer_gets_the_same_dashboard_scoped_to_them(): void
    {
        $customer = $this->userWithRole('Customer');

        Address::factory()->count(2)->for($customer)->create(['region_code' => '1300000000']);
        Address::factory()->count(4)->create(['region_code' => '0700000000']);

        $html = $this->profile($customer, $customer);

        $this->assertCount(1, $chart = $this->points($html, 'data-region-chart'));
        $this->assertSame(2, $chart[0]['value']);
        $this->assertCount(2, $this->points($html, 'data-address-map'));
        $this->assertStringContainsString('2 of 2 addresses have coordinates', $html);
    }

    public function test_a_customer_still_cannot_open_another_account(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('addresses.user', $this->userWithRole('Customer')))
            ->assertForbidden();
    }

    public function test_an_account_holding_nothing_renders_zeroed(): void
    {
        // The seeded Superadmin owns no addresses, so this is a real account
        // rather than a contrived one - and the coverage maths has to survive a
        // total of zero.
        $reader = $this->userWithRole('Superadmin');

        $html = $this->profile($reader, $reader);

        $this->assertSame([], $this->points($html, 'data-region-chart'));
        $this->assertSame([], $this->points($html, 'data-address-map'));
        $this->assertStringContainsString('0 of 0 addresses have coordinates', $html);
        $this->assertStringContainsString('0% of them have coordinates', $html);
    }

    public function test_the_dashboard_map_still_fetches_rather_than_carrying_its_pins(): void
    {
        $reader = $this->userWithRole('Superadmin');
        Address::factory()->count(2)->create();

        $html = $this->actingAs($reader)->get(route('home'))->assertOk()->getContent();

        // The dashboard's map is directory-wide and is still filled over ajax,
        // so the fetch branch of the module keeps its coverage.
        $this->assertStringContainsString(route('home.map'), $html);

        // And that endpoint still answers with the whole book.
        $pins = $this->actingAs($reader)->getJson(route('home.map'))->assertOk()->json();

        $this->assertCount(2, $pins);
        $this->assertNotNull($pins[0]['owner']);
    }

    public function test_the_view_action_carries_a_visible_label(): void
    {
        $alice = User::factory()->create();

        $html = view('addresses.partials.user-actions', ['user' => $alice])->render();

        $this->assertStringContainsString('bi-eye', $html);
        $this->assertStringContainsString(route('addresses.user', $alice), $html);

        // Labelled, not tooltip-only: a screen reader announces the icon's
        // neighbour, and a sighted reader can see what the button does.
        $this->assertStringContainsString('>View</span>', $html);
        $this->assertStringNotContainsString('visually-hidden', $html);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function profile(User $actor, User $owner): string
    {
        return $this->actingAs($actor)
            ->get(route('addresses.user', $owner))
            ->assertOk()
            ->getContent();
    }

    /**
     * The figures an element carries in its data-points attribute. Blade escapes
     * the JSON for the attribute, so it is decoded before being parsed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function points(string $html, string $marker): array
    {
        $this->assertMatchesRegularExpression(
            '/<div[^>]*'.preg_quote($marker, '/').'[^>]*data-points="[^"]*"/s',
            $html,
            "No data-points element marked {$marker}.",
        );

        preg_match('/<div[^>]*'.preg_quote($marker, '/').'[^>]*data-points="([^"]*)"/s', $html, $match);

        return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
    }
}
