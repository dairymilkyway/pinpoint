<?php

namespace Database\Seeders;

use App\Geo\PhLocations;
use App\Models\Address;
use App\Models\AddressRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * One waiting request of each kind, so the queue is not empty on a fresh install
 * and the approve and reject paths have something to act on.
 *
 * Idempotent by type: a type that already has something pending is left alone,
 * so re-running the seeder does not stack a second copy of the same demo.
 */
class AddressRequestSeeder extends Seeder
{
    public function run(): void
    {
        $owners = $this->owners();

        if ($owners->isEmpty()) {
            return;
        }

        foreach ([AddressRequest::TYPE_CREATE, AddressRequest::TYPE_UPDATE, AddressRequest::TYPE_DELETE] as $index => $type) {
            $this->raise($type, $owners[$index % $owners->count()]);
        }
    }

    private function raise(string $type, User $owner): void
    {
        if (AddressRequest::query()
            ->where('type', $type)
            ->where('status', AddressRequest::STATUS_PENDING)
            ->exists()
        ) {
            return;
        }

        // An addition has no address behind it; an edit and a deletion do. An
        // owner with nothing to edit is skipped rather than seeded a request
        // that can never be applied.
        $address = $type === AddressRequest::TYPE_CREATE
            ? null
            : $owner->addresses()->orderBy('id')->first();

        if ($type !== AddressRequest::TYPE_CREATE && $address === null) {
            return;
        }

        AddressRequest::create([
            'user_id' => $owner->id,
            'address_id' => $address?->id,
            'type' => $type,
            'payload' => $this->payload($type, $address),
            'before' => $address?->snapshot(),
            'note' => $this->note($type),
        ]);
    }

    /**
     * What the request is asking for.
     *
     * An edit carries every field the form would have posted, with two of them
     * moved, because that is what a real request looks like - the request screen
     * diffs the payload against the address and would otherwise show only the
     * fields the seeder happened to name.
     *
     * @return array<string, mixed>|null
     */
    private function payload(string $type, ?Address $address): ?array
    {
        if ($type === AddressRequest::TYPE_DELETE) {
            return null;
        }

        if ($type === AddressRequest::TYPE_UPDATE) {
            // is_default is dropped for the same reason the controller drops it:
            // a default marker is moved by its own immediate action, never by a
            // proposal.
            return array_merge(Arr::except($address->snapshot(), ['is_default']), [
                'line1' => '18B Sunrise Drive',
                'postal_code' => '1103',
            ]);
        }

        $cityCode = PhLocations::seedableCityCodes()[0];
        $city = PhLocations::find($cityCode);
        $geo = PhLocations::coordinatesFor($cityCode);

        return [
            'label' => 'Warehouse',
            'line1' => '7 Katipunan Avenue',
            'line2' => 'Unit 4B',
            'city' => $city['name'],
            'state' => PhLocations::stateFor($city),
            'postal_code' => $geo['postal'],
            'country' => 'Philippines',
            'region_code' => $city['region'],
            'province_code' => $city['province'],
            'city_code' => $cityCode,
            'latitude' => $geo['lat'],
            'longitude' => $geo['lng'],
        ];
    }

    private function note(string $type): string
    {
        return match ($type) {
            AddressRequest::TYPE_CREATE => 'A second delivery point for the north route.',
            AddressRequest::TYPE_UPDATE => 'We moved at the end of the month. Same barangay.',
            AddressRequest::TYPE_DELETE => 'The lease ended, nothing is delivered there now.',
        };
    }

    /**
     * The accounts that may raise a request. Both readers are skipped for the
     * same reason they are skipped when seeding addresses: they manage the book
     * rather than hold part of it.
     *
     * @return Collection<int, User>
     */
    private function owners(): Collection
    {
        return User::query()
            ->owningAccounts()
            ->orderBy('id')
            ->get()
            ->values();
    }
}
