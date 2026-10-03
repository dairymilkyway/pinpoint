<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoLoginTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'demo.admin@example.test';

    private const PASSWORD = 'demo-secret-1234';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
    }

    /** Configures a single demo account, independent of whatever is in .env. */
    private function configurePicker(bool $enabled = true): void
    {
        config([
            'demo.enabled' => $enabled,
            'demo.accounts' => [[
                'role' => 'Superadmin',
                'name' => 'Ana Reyes',
                'email' => self::EMAIL,
                'password' => self::PASSWORD,
            ]],
        ]);
    }

    public function test_the_picker_is_absent_unless_it_is_switched_on(): void
    {
        $this->configurePicker(enabled: false);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Demo accounts');
    }

    public function test_the_picker_lists_the_configured_accounts(): void
    {
        $this->configurePicker();

        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('Demo accounts', $html);
        $this->assertStringContainsString(self::EMAIL, $html);
        $this->assertStringContainsString('Ana Reyes', $html);
    }

    public function test_the_picker_is_refused_in_production(): void
    {
        $this->configurePicker();
        $this->app->detectEnvironment(fn () => 'production');

        $this->assertTrue($this->app->isProduction());

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Demo accounts');
    }

    public function test_a_listed_demo_account_can_actually_sign_in(): void
    {
        $this->configurePicker();

        User::create([
            'name' => 'Ana Reyes',
            'email' => self::EMAIL,
            'password' => Hash::make(self::PASSWORD),
        ])->assignRole('Superadmin');

        // The picker fills the real form with these values; the login must go
        // through the ordinary guard rather than any shortcut.
        $this->post(route('login'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();

        // /home used to redirect on to the directory. It is the dashboard now,
        // so signing in lands there directly.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dashboard');
    }
}
