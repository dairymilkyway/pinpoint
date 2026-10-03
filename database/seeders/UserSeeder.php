<?php

namespace Database\Seeders;

use App\Models\User;
use App\Rbac;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Creates the demo accounts and the extra address owners.
     *
     * Keyed on email and written with updateOrCreate, so running the seeder
     * twice updates these rows instead of inserting a second copy of each. The
     * previous version called User::factory()->count(4) here, which is why the
     * database this replaced had eight role-less faker users in it.
     *
     * Passwords are read from the environment and never written down in source.
     * The accounts themselves live in config/demo.php so the sign-in page and
     * this seeder cannot drift apart.
     */
    public function run(): void
    {
        foreach (config('demo.accounts', []) as $account) {
            if (blank($account['email'] ?? null) || blank($account['password'] ?? null)) {
                throw new RuntimeException(
                    "The {$account['role']} demo account needs both an email and a password in .env "
                    .'(SEED_ADMIN_EMAIL/SEED_ADMIN_PASSWORD for the Superadmin, SEED_DEMO_PASSWORD for the rest).'
                );
            }

            $this->upsert($account['email'], $account['name'], $account['password'], $account['role']);
        }

        $password = config('demo.owners_password');

        if (blank($password)) {
            throw new RuntimeException('SEED_DEMO_PASSWORD must be set in .env before seeding.');
        }

        foreach (config('demo.owners', []) as $name => $email) {
            $this->upsert($email, $name, $password, Rbac::CUSTOMER_ROLE);
        }
    }

    private function upsert(string $email, string $name, string $password, string $role): void
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        // Not mass assignable on the model, and there is no verification step in
        // the auth flow, so these accounts are marked verified explicitly.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->syncRoles([$role]);
    }
}
