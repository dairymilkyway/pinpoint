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
        return $user->can('addresses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('addresses.create');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->can('addresses.edit');
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->can('addresses.delete');
    }

    public function export(User $user): bool
    {
        return $user->can('addresses.export');
    }
}
