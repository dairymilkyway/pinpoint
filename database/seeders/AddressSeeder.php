<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class AddressSeeder extends Seeder
{
    /** How many addresses each owner should end up with. */
    private const PER_OWNER = 8;

    /**
     * Tops every owner up to PER_OWNER addresses rather than adding a fresh
     * batch, so re-running the seeder is a no-op instead of doubling the table.
     * The previous version created 25 rows unconditionally on every run.
     */
    public function run(): void
    {
        $owners = User::query()->get();

        if ($owners->isEmpty()) {
            return;
        }

        foreach ($owners as $owner) {
            $existing = $owner->addresses()->count();

            for ($i = $existing; $i < self::PER_OWNER; $i++) {
                Address::factory()->create(['user_id' => $owner->id]);
            }

            $this->ensureSingleDefault($owner);
        }
    }

    /** Each owner gets exactly one default address, chosen once. */
    private function ensureSingleDefault(User $owner): void
    {
        if ($owner->addresses()->where('is_default', true)->exists()) {
            return;
        }

        $owner->addresses()->inRandomOrder()->first()?->update(['is_default' => true]);
    }
}
