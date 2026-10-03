<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;
use App\Rbac;

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
     * Making one of your own addresses the default.
     *
     * Not an edit: the default is a marker on the owner's own book that changes
     * nothing anyone else sees, which is why a Customer may do it directly and
     * needs no approval. It is owner-only for the same reason - a reader may set
     * a default through the edit form, but not reach into someone else's book to
     * move their marker.
     */
    public function setDefault(User $user, Address $address): bool
    {
        return $user->can(Rbac::VIEW_PERMISSION) && $address->user_id === $user->id;
    }

    /**
     * Asking an administrator for a change to this address, rather than making
     * it. Your own only: a Customer who could propose an edit to somebody else's
     * row would be reaching into a book they cannot even see.
     *
     * The subject is an Address, so the rule lives here rather than on
     * AddressRequestPolicy - a policy is looked up from the model being
     * authorized, whatever the method is about.
     */
    public function requestFor(User $user, Address $address): bool
    {
        return $user->can(Rbac::REQUEST_PERMISSION) && $address->user_id === $user->id;
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
