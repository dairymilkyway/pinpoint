<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

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
        $owners = $this->owners();

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

    /**
     * The accounts that hold addresses.
     *
     * Both readers are skipped. They read the whole book because of their role,
     * not because they hold part of it: seeding them with rows made a reader's
     * own dashboard report owning eight addresses while the cards above it said
     * every address was visible, and put them in the users list as owners.
     *
     * The predicate is seesEveryAddress() rather than a role name. An earlier
     * version named only the Superadmin and argued the Admin kept its eight rows
     * to demonstrate that reach comes from the role rather than from ownership.
     * That argument lost: it made the account labelled Admin an owner of the very
     * book it manages. The reach-not-ownership point is still made, by a reader
     * seeing every address while owning none.
     *
     * @return Collection<int, User>
     */
    private function owners(): Collection
    {
        return User::query()
            ->with('roles')
            ->get()
            ->reject(fn (User $user) => $user->seesEveryAddress())
            ->values();
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
