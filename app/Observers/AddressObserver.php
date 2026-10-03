<?php

namespace App\Observers;

use App\Models\Address;
use App\Models\AuditLog;

/**
 * Records every write to an address, whichever door it came through.
 *
 * This is deliberately an observer rather than calls in the controller: the
 * direct Admin edit, the approved request, the default toggle and the seeder all
 * end up in the same save, so hanging the record on the model means none of them
 * can be forgotten. The seeder's own writes are dropped by AuditLog::record(),
 * which refuses to write without a signed-in actor.
 */
class AddressObserver
{
    public function created(Address $address): void
    {
        AuditLog::record(AuditLog::ADDRESS_CREATED, $address, null, $address->snapshot());
    }

    public function updated(Address $address): void
    {
        // getChanges() includes updated_at and anything else the save touched.
        // Only the tracked attributes are an edit; a touch is not.
        $changed = array_values(array_intersect(array_keys($address->getChanges()), Address::TRACKED));

        if ($changed === []) {
            return;
        }

        // Both sides go through the model's casts, so a boolean does not read as
        // 1 in the before and true in the after.
        $before = (new Address)->setRawAttributes($address->getRawOriginal(), true)->only($changed);

        AuditLog::record(AuditLog::ADDRESS_UPDATED, $address, $before, $address->only($changed));
    }

    public function deleted(Address $address): void
    {
        AuditLog::record(AuditLog::ADDRESS_DELETED, $address, $address->snapshot(), null);
    }
}
