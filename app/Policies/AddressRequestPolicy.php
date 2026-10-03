<?php

namespace App\Policies;

use App\Models\AddressRequest;
use App\Models\User;
use App\Rbac;

class AddressRequestPolicy
{
    /**
     * The queue. A reader sees all of it, a Customer sees their own list on the
     * same screen - so both roles hold this, and the scoping is done by
     * AddressRequest::scopeVisibleTo() rather than by refusing entry.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Rbac::APPROVE_PERMISSION) || $user->can(Rbac::REQUEST_PERMISSION);
    }

    /** Raising one. */
    public function create(User $user): bool
    {
        return $user->can(Rbac::REQUEST_PERMISSION);
    }

    /**
     * Deciding one. Only while it is pending: a second decision on an already
     * decided request would apply the same proposal twice, and a deletion
     * applied twice is a 404 rather than a mistake the user can see.
     */
    public function decide(User $user, AddressRequest $addressRequest): bool
    {
        return $user->can(Rbac::APPROVE_PERMISSION) && $addressRequest->isPending();
    }
}
