<?php

namespace Tests\Feature;

use App\AddressStats;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_the_dashboard_requires_authentication(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_all_three_roles_reach_the_dashboard(): void
    {
        foreach (['Superadmin', 'Admin', 'Customer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('Dashboard');
        }
    }

    public function test_the_signed_in_landing_redirects_to_the_dashboard(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('landing'))
            ->assertRedirect(route('home'));
    }

    public function test_only_the_superadmin_sees_the_access_panel(): void
    {
        $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Access');

        foreach (['Admin', 'Customer'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertDontSee('>Access<', false);
        }
    }

    public function test_only_roles_that_may_create_get_the_new_address_action(): void
    {
        foreach (['Superadmin', 'Admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('New address');
        }

        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('New address');
    }

    public function test_the_counts_reflect_the_stored_addresses(): void
    {
        $owner = $this->userWithRole('Superadmin');

        Address::factory()->count(3)->create([
            'user_id' => $owner->id,
            'city_code' => '1380100000',
            'region_code' => '1300000000',
            'latitude' => 14.65,
            'longitude' => 120.98,
        ]);

        Address::factory()->create([
            'user_id' => $owner->id,
            'city_code' => '0722000000',
            'region_code' => '0700000000',
            'latitude' => null,
            'longitude' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('home'))->assertOk();

        // Four addresses, one owner, two distinct cities, two distinct regions.
        $response->assertSee('4');
    }

    public function test_an_account_with_no_role_gets_a_dashboard_not_a_403(): void
    {
        // Registration assigns no role, and the landing page must not become a
        // dead end for that account.
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('nothing has been shared with you yet');
    }

    public function test_the_counts_are_scoped_for_a_customer(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->count(2)->for($customer)->create([
            'city_code' => '1380100000',
            'region_code' => '1300000000',
        ]);
        Address::factory()->count(5)->create();

        $response = $this->actingAs($customer)->get(route('home'))->assertOk();

        // Two of the seven, not all seven.
        $response->assertSee('on file under your name');
    }

    public function test_both_directory_reading_roles_get_the_owners_card(): void
    {
        Address::factory()->count(3)->create();

        foreach (['Superadmin', 'Admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('people holding at least one');
        }

        // Scoped to yourself it could only ever read 1, so it is dropped.
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('people holding at least one');
    }

    public function test_the_dashboard_map_follows_the_same_scope_as_the_cards(): void
    {
        Address::factory()->count(2)->create(['label' => 'Mine']);
        Address::factory()->count(3)->create(['label' => 'Theirs']);

        // Both readers pin everything, so the map agrees with the 5 in the
        // cards above it rather than contradicting them.
        foreach (['Superadmin', 'Admin'] as $role) {
            $points = $this->actingAs($this->userWithRole($role))
                ->getJson(route('home.map'))->assertOk()->json();

            $this->assertCount(5, $points);
        }

        // The Customer sees only its own, and gets no owner to tell apart.
        $customer = $this->userWithRole('Customer');
        Address::factory()->count(2)->for($customer)->create(['label' => 'Mine']);

        $customerPoints = $this->actingAs($customer)->getJson(route('home.map'))->assertOk()->json();

        $this->assertCount(2, $customerPoints);
        $this->assertSame(['Mine', 'Mine'], array_column($customerPoints, 'label'));
        $this->assertSame([null, null], array_column($customerPoints, 'owner'));
    }

    public function test_only_a_directory_reader_gets_an_owner_on_each_pin(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->for($superadmin)->create();

        $points = $this->actingAs($superadmin)->getJson(route('home.map'))->assertOk()->json();

        $this->assertNotNull($points[0]['owner']);
    }

    public function test_the_map_panel_is_named_for_what_the_role_pins(): void
    {
        Address::factory()->count(2)->create();

        foreach (['Superadmin', 'Admin'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get(route('home'))
                ->assertOk()
                ->assertSee('User locations')
                ->assertSee('Every address on file')
                ->assertDontSee('Your locations');
        }

        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create();

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Your locations')
            ->assertSee('Your own addresses')
            ->assertDontSee('User locations');
    }

    public function test_the_dashboard_map_requires_authentication(): void
    {
        $this->get(route('home.map'))->assertRedirect(route('login'));
    }

    public function test_the_map_panel_is_present_even_with_nothing_to_pin(): void
    {
        $customer = $this->userWithRole('Customer');
        Address::factory()->for($customer)->create(['latitude' => null, 'longitude' => null]);

        // The panel is unconditional; the module renders its own empty state
        // once an empty response comes back, so there is no branch here.
        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Your locations')
            ->assertSee(route('home.map'));
    }

    /**
     * The panel asked one question - how much of the book is actually placed -
     * and answered it with a single figure over a strip of supporting stats.
     * That is the shape the research names as the default AI dashboard, so it
     * was removed rather than restyled. Nothing on the dashboard reports the
     * figure now; the fact itself stays in the directory's Map column, the
     * import result and each pin's own popup.
     */
    public function test_the_placement_panel_is_gone_from_the_dashboard(): void
    {
        $owner = $this->userWithRole('Superadmin');
        Address::factory()->count(2)->for($owner)->create();

        $this->actingAs($owner)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('How much of the book')
            ->assertDontSee('addresses have coordinates');
    }

    public function test_an_empty_directory_renders_without_errors(): void
    {
        $this->actingAs($this->userWithRole('Customer'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('You have not asked for a change yet.');
    }

    public function test_the_superadmin_gets_the_chart_ranked_and_named_by_region(): void
    {
        $superadmin = $this->userWithRole('Superadmin');
        Address::factory()->count(2)->for($superadmin)->create([
            'city_code' => '1380100000',
            'region_code' => '1300000000',
        ]);
        Address::factory()->for($superadmin)->create([
            'city_code' => '0722000000',
            'region_code' => '0700000000',
        ]);

        $response = $this->actingAs($superadmin)->get(route('home'))->assertOk();

        $response->assertSee('data-region-chart', false);
        $response->assertSee('By region');

        // Ordered, so the ranking is asserted and not just the totals. The
        // capital region is labelled the way it is written in an address, not
        // the way PSGC classifies it - see PhLocations::REGION_LABELS.
        $response->assertSeeInOrder([
            'Metro Manila: 2 addresses',
            'Central Visayas: 1 address',
        ]);

        // The PSGC code is a grouping key, not something to put in front of a user.
        $response->assertDontSee('1300000000');
    }

    public function test_the_chart_covers_the_whole_directory_for_either_reader(): void
    {
        Address::factory()->create([
            'city_code' => '1380100000',
            'region_code' => '1300000000',
        ]);
        Address::factory()->count(3)->create([
            'city_code' => '0722000000',
            'region_code' => '0700000000',
        ]);

        // The panel is only ever shown to a directory-wide reader, so neither
        // of them gets a chart scoped to its own rows.
        foreach (['Superadmin', 'Admin'] as $role) {
            $response = $this->actingAs($this->userWithRole($role))->get(route('home'))->assertOk();

            $response->assertSee('data-region-chart', false);
            $response->assertSeeInOrder([
                'Central Visayas: 3 addresses',
                'Metro Manila: 1 address',
            ]);
        }
    }

    /**
     * The Customer's column used to be the six most recently added addresses,
     * which said nothing the address table does not already say. It is now the
     * one thing this role does that no other panel mentions.
     *
     * The panel in that column and the chart row under it used to share one
     * condition, so handing this role a chart swapped their request list away to
     * get it. They are two conditions now, and this asserts both at once: the
     * list still in their place, the chart now on top of it.
     */
    public function test_a_customer_gets_their_own_requests_and_the_region_chart(): void
    {
        $customer = $this->userWithRole('Customer');
        $theirs = $this->userWithRole('Customer');

        $mine = Address::factory()->for($customer)->create(['label' => 'Home']);
        $notMine = Address::factory()->for($theirs)->create(['label' => 'Their Depot']);

        AddressRequest::create([
            'user_id' => $customer->id,
            'address_id' => $mine->id,
            'type' => AddressRequest::TYPE_UPDATE,
            'payload' => ['label' => 'Home'],
            'before' => $mine->snapshot(),
        ]);

        AddressRequest::create([
            'user_id' => $theirs->id,
            'address_id' => $notMine->id,
            'type' => AddressRequest::TYPE_UPDATE,
            'payload' => ['label' => 'Their Depot'],
            'before' => $notMine->snapshot(),
        ]);

        $html = $this->actingAs($customer)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Your requests', $html);
        $this->assertStringContainsString('1 waiting on a decision.', $html);
        $this->assertStringContainsString('Home', $html);

        // Scoped like every other figure on the page.
        $this->assertStringNotContainsString('Their Depot', $html);

        $this->assertStringContainsString('data-region-chart', $html);
        $this->assertStringContainsString('By region', $html);
    }

    public function test_the_chart_panel_replaces_recent_for_a_reader(): void
    {
        $admin = $this->userWithRole('Admin');
        Address::factory()->for($admin)->create();

        // a reader is charted, so Recent is not the panel in that column at all.
        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('By region')
            ->assertDontSee('>Recent<', false);
    }

    public function test_a_reader_with_nothing_on_file_still_gets_the_chart_panel(): void
    {
        // An empty array is falsy in PHP, so the view has to test for null - a
        // wrong check here would silently swap the panel back to Recent.
        $this->actingAs($this->userWithRole('Superadmin'))
            ->get(route('home'))
            ->assertOk()
            ->assertSee('By region')
            ->assertSee('data-points="[]"', false)
            ->assertDontSee('>Recent<', false);
    }

    /**
     * Both halves of the same rule. With no role there is no directory to chart
     * and no figures to band, so the page falls back to the one honest panel
     * rather than a row of zeroes.
     */
    public function test_an_account_with_no_role_gets_no_chart_and_no_metric_band(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('data-region-chart', false)
            ->assertDontSee('metric-band', false);
    }

    /**
     * The lead metric's sparkline: cumulative addresses, one point per week,
     * oldest first.
     *
     * Rows are dated explicitly rather than by travelling the clock forward,
     * because the interesting case is a row that falls OUTSIDE the window - it
     * contributes to the running total without ever owning a bucket.
     */
    public function test_the_trend_is_cumulative_and_ends_at_the_address_total(): void
    {
        $this->travelTo(Carbon::parse('2026-10-04 12:00:00'));

        $admin = $this->userWithRole('Admin');

        // Twenty weeks back is past the window, so this one only seeds the total.
        Address::factory()->for($admin)->create(['created_at' => now()->subWeeks(20)]);
        Address::factory()->for($admin)->count(2)->create(['created_at' => now()->subWeeks(3)]);
        Address::factory()->for($admin)->create(['created_at' => now()->subWeeks(1)]);

        $query = Address::query()->visibleTo($admin);
        $points = AddressStats::trend($query);

        $this->assertCount(12, $points);

        // The first bucket holds only what was already on file when it opened.
        $this->assertSame(1, $points[0]['value']);

        // The last point and the figure printed beside it cannot disagree.
        $this->assertSame(
            AddressStats::counts($query)['addresses'],
            end($points)['value'],
        );

        $values = array_column($points, 'value');
        $ascending = $values;
        sort($ascending);
        $this->assertSame($ascending, $values, 'A cumulative series never decreases.');
    }

    /**
     * A sparkline that ignored the scope would leak another owner's growth rate
     * onto a Customer's own dashboard, which is the one thing this page is not
     * allowed to do.
     */
    public function test_the_trend_is_scoped_to_the_rows_the_caller_can_see(): void
    {
        $customer = $this->userWithRole('Customer');
        $other = $this->userWithRole('Customer');

        Address::factory()->for($customer)->count(2)->create();
        Address::factory()->for($other)->count(5)->create();

        $points = AddressStats::trend(Address::query()->visibleTo($customer));

        $this->assertSame(2, end($points)['value']);
    }
}
