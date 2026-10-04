<?php

namespace Tests\Feature;

use App\ArchipelagoMap;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public pages: the landing, and the sign-in page with its demo picker.
 *
 * The suite already covers the two contract sentences (ExampleTest) and the demo
 * picker's sign-in path (DemoLoginTest). This covers what those miss: that the
 * map is really drawn from the dataset, that the page credits the datasets it
 * uses and claims nothing it does not, and that the atlas shell stayed where it
 * belongs.
 *
 * Nothing here touches layout, reflow or colour - CSS is invisible to PHP. Those
 * remain browser checks.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_landing_draws_every_city_as_a_point(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $counts = ArchipelagoMap::points()['counts'];

        // The honest figures, pinned. These are the numbers the caption states,
        // so a dataset change that moves them should fail here rather than
        // quietly making the page's own copy untrue.
        $this->assertSame(1642, $counts['total']);
        $this->assertSame(1490, $counts['placed']);
        $this->assertSame(152, $counts['approximate']);

        // Counted by prefix rather than by a closed class attribute. The pulse
        // animation marks a random handful of dots on every render, so a count
        // keyed off the exact class string would move between runs and fail
        // intermittently. These two are invariants whatever the pick was.
        $this->assertSame(1642, substr_count($html, 'class="atlas__dot'));
        $this->assertSame(152, substr_count($html, 'atlas__dot--approx'));

        // The pin count is fixed even though which points they land on is not:
        // 12 are chosen per render, so the class appears 12 times however the
        // pick fell. A pin is a separate element from the dots, so this counts
        // independently of the two above.
        $this->assertSame(12, substr_count($html, 'class="atlas__pin"'));

        // Dots carry no inline style now that the pulse has gone, so this keeps
        // the animation from creeping back onto the 1642 circles.
        $this->assertStringNotContainsString('atlas__dot--pulse', $html);
    }

    /**
     * The bounding box is computed from the dataset, so it cannot be a constant
     * that has drifted away from the data. Asserting the view emits whatever the
     * class derived - rather than a literal box - is the point of the test.
     */
    public function test_the_map_box_comes_from_the_dataset(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'viewBox="'.ArchipelagoMap::points()['viewBox'].'"',
            $html,
        );

        // Drawn from real coordinates, so it is a tall narrow country rather
        // than a square: 800 wide against roughly 1649 tall.
        $this->assertStringContainsString('viewBox="0 0 800 ', $html);
    }

    public function test_the_landing_credits_both_datasets_and_claims_no_tiles(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Philippine Standard Geographic Code', $html);
        $this->assertStringContainsString('GeoNames', $html);

        // Both datasets are CC BY 4.0. The partial's other paragraph credits
        // OpenStreetMap for map tiles; this page draws no tiles, so shipping
        // that line would credit work the page does not use.
        $this->assertStringNotContainsString('Map tiles', $html);
        $this->assertStringNotContainsString('openstreetmap.org/copyright', $html);
    }

    /**
     * No Google Fonts link, no CDN, no remote anything. The fonts are
     * self-hosted through @fontsource and the map is inline SVG, so a public
     * page should reach out to nobody.
     */
    public function test_the_public_pages_make_no_external_request(): void
    {
        foreach (['/', '/login', '/register'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            foreach ([
                'fonts.googleapis.com',
                'fonts.gstatic.com',
                'cdn.jsdelivr.net',
                'unpkg.com',
                'cdnjs.cloudflare.com',
            ] as $host) {
                $this->assertStringNotContainsString($host, $html, "{$path} reaches out to {$host}");
            }
        }
    }

    public function test_the_sign_in_page_offers_one_card_per_demo_role(): void
    {
        config([
            'demo.enabled' => true,
            'demo.accounts' => [
                ['role' => 'Superadmin', 'name' => 'Ana Reyes', 'email' => 'ana@example.test', 'password' => 'pw-one'],
                ['role' => 'Admin', 'name' => 'Miguel Santos', 'email' => 'miguel@example.test', 'password' => 'pw-two'],
                ['role' => 'Customer', 'name' => 'Liza Mendoza', 'email' => 'liza@example.test', 'password' => 'pw-three'],
            ],
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        // Counted by the card class, not by data-demo-account: the fill-and-submit
        // script queries '[data-demo-account]', so that substring appears once
        // more than there are cards.
        $this->assertSame(3, substr_count($html, 'class="demo-card"'));

        // Each card carries the credentials the fill-and-submit script needs,
        // and names the person, so a reviewer can tell the roles apart.
        foreach (['ana@example.test', 'miguel@example.test', 'liza@example.test'] as $email) {
            $this->assertStringContainsString($email, $html);
        }

        foreach (['Ana Reyes', 'Miguel Santos', 'Liza Mendoza'] as $name) {
            $this->assertStringContainsString($name, $html);
        }

        // Every role the picker offers is described truthfully from Rbac, so a
        // card cannot advertise a power its role does not hold.
        $this->assertStringContainsString('Superadmin', $html);
        $this->assertStringContainsString('Admin', $html);
        $this->assertStringContainsString('Customer', $html);
    }

    /**
     * Every auth view is the same shell now: .auth-shell wrapping one
     * .auth-card. Sign-in used to be the exception, seating a cartographic
     * panel beside the form in a two-column grid.
     *
     * The test this replaces held the trap that grid created - if it ever
     * reached .auth-card, the reset and verify pages would inherit a layout
     * they were never built for. The panel is gone, so that trap is gone with
     * it. This holds the invariant that replaced it: no auth view grows a
     * second column without failing here first.
     */
    public function test_every_auth_view_is_a_single_card_column(): void
    {
        foreach (['/login', '/register', '/password/reset'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('auth-shell', $html);
            $this->assertStringContainsString('auth-card', $html);
            $this->assertStringNotContainsString('auth-split', $html);
            $this->assertStringNotContainsString('auth-plate', $html);
        }
    }

    /**
     * The sign-in page no longer draws the archipelago. It is still drawn on
     * the landing page - test_the_landing_draws_every_city_as_a_point covers
     * that - so this only has to prove the panel left the login view, and that
     * it is not quietly waiting in some shared partial.
     */
    public function test_the_sign_in_page_no_longer_carries_the_atlas(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('auth-plate', $html);
        $this->assertStringNotContainsString('atlas__dot', $html);
    }

    /**
     * The landing page used to set a title section of 'Pinpoint', which the
     * layout then printed beside the app name - a tab reading
     * "Pinpoint · Pinpoint". The layout now suppresses the separator when a
     * view sets no title, so the front page gets the bare name and every other
     * page keeps the "Page · Pinpoint" shape.
     *
     * Asserted against config('app.name') rather than a literal, because the
     * tests only override APP_ENV: the name comes from .env.
     */
    public function test_the_page_title_never_repeats_the_product_name(): void
    {
        $name = config('app.name', 'Pinpoint');

        $landing = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("<title>{$name}</title>", $landing);
        $this->assertStringNotContainsString("{$name} &middot; {$name}", $landing);

        // A titled page still carries both halves, so the fix has not simply
        // dropped the app name from every tab.
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString("<title>Sign in &middot; {$name}</title>", $login);
    }

    /**
     * public/favicon.ico shipped as a 0-byte stub, so every browser asked for an
     * icon, received nothing, and drew a blank tab. The non-empty assertion is
     * the regression guard for exactly that: it would have failed before this
     * change and passes only because the file now holds a real image.
     */
    public function test_the_icon_set_is_declared_and_every_file_is_non_empty(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'] as $file) {
            $path = public_path($file);

            $this->assertFileExists($path, "{$file} is missing from public/");
            $this->assertGreaterThan(0, filesize($path), "{$file} is empty");
        }

        // SVG is text, so its contents are worth checking too: a browser given an
        // unparsable icon file shows nothing, and a valid one is cheap to assert.
        $svg = file_get_contents(public_path('favicon.svg'));
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 32 32"', $svg);

        $html = $this->get('/')->assertOk()->getContent();

        // Every icon the head is supposed to declare, plus the theme colour that
        // tints the mobile browser chrome to match the dark plate.
        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('favicon.ico', $html);
        $this->assertStringContainsString('favicon.svg', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('apple-touch-icon.png', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#0d1014">', $html);
    }

    /**
     * The brand mark in the header is the same pin as the favicon, and it is one
     * shape rather than two drawings that resemble each other: the two `d`
     * strings are compared byte for byte.
     *
     * Every view is checked on disk rather than by rendering, because the mark is
     * copy-pasted into eight views and a render check would only ever reach the
     * pages this test happens to request. The crosshair <i> it replaced must be
     * gone everywhere - one straggler would leave a second, different mark in the
     * header of whatever page it sits on.
     */
    public function test_the_brand_mark_is_the_pin_and_matches_the_favicon(): void
    {
        $svg = file_get_contents(public_path('favicon.svg'));
        $this->assertSame(1, preg_match('/<path d="([^"]+)"/', $svg, $matches));
        $pin = $matches[1];

        $views = [
            'landing.blade.php',
            'layouts/app.blade.php',
            'auth/login.blade.php',
            'auth/register.blade.php',
            'auth/verify.blade.php',
            'auth/passwords/confirm.blade.php',
            'auth/passwords/email.blade.php',
            'auth/passwords/reset.blade.php',
        ];

        foreach ($views as $view) {
            $source = file_get_contents(resource_path("views/{$view}"));

            $this->assertStringContainsString('app-brand__pin', $source, "{$view} has no pin mark");
            $this->assertStringContainsString($pin, $source, "{$view}'s pin differs from the favicon");
            $this->assertStringNotContainsString('bi-crosshair', $source, "{$view} still draws the old crosshair");
        }

        // And the same mark reaches the browser, not just the source.
        foreach (['/', '/login', '/register'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('app-brand__pin', $html, "{$path} renders no pin mark");
            $this->assertStringContainsString($pin, $html, "{$path}'s pin differs from the favicon");
        }
    }

    /**
     * Two screens were labelled wrongly. /addresses is the front door for a
     * reader of the whole book - it lists owners, not addresses - and
     * /addresses/create with no owner chosen is the picker that asks whose
     * address it is, not an editor.
     *
     * Reached with a Superadmin, since it takes a reader to see the users list
     * rather than their own book.
     */
    public function test_the_reader_front_door_and_the_owner_picker_name_themselves(): void
    {
        $name = config('app.name', 'Pinpoint');
        $superadmin = User::factory()->create()->assignRole('Superadmin');

        $html = $this->actingAs($superadmin)->get(route('addresses.index'))->assertOk()->getContent();
        $this->assertStringContainsString("<title>Address owners &middot; {$name}</title>", $html);

        $picked = $this->actingAs($superadmin)->get(route('addresses.create'))->assertOk()->getContent();
        $this->assertStringContainsString("<title>Choose an owner &middot; {$name}</title>", $picked);
    }
}
