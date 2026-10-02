<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('addresses.view');
    }

    public function view(User $user, Address $address): bool
    {
        return $user->can('addresses.view') && $this->reachable($user, $address);
    }

    public function create(User $user): bool
    {
        return $user->can('addresses.create');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->can('addresses.edit') && $this->reachable($user, $address);
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->can('addresses.delete') && $this->reachable($user, $address);
    }

    /**
     * The policy half of the visibility rule. It has to repeat what
     * Address::scopeVisibleTo() does, because a row that is hidden from the
     * listing must also be refused when it is asked for by id directly.
     */
    private function reachable(User $user, Address $address): bool
    {
        return $user->seesEveryAddress() || $address->user_id === $user->id;
    }

    public function export(User $user): bool
    {
        return $user->can('addresses.export');
    }
}
