<?php

namespace Tests\Feature;

use App\ArchipelagoMap;
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

        $this->assertSame(1490, substr_count($html, 'class="atlas__dot"'));
        $this->assertSame(152, substr_count($html, 'class="atlas__dot atlas__dot--approx"'));
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
     * The atlas shell is shared by six auth views. The two-column treatment is
     * sign-in only - if it ever reaches .auth-card itself, the reset and verify
     * pages inherit a grid they were never laid out for.
     *
     * This is the trap the redesign had to avoid, so it is held by a test rather
     * than by a comment.
     */
    public function test_the_two_column_treatment_is_confined_to_sign_in(): void
    {
        $this->assertStringContainsString(
            'auth-split',
            $this->get('/login')->assertOk()->getContent(),
        );

        // Register and the password-reset entry point both borrow the shell.
        foreach (['/register', '/password/reset'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('auth-split', false)
                ->assertSee('auth-card', false);
        }
    }
}
