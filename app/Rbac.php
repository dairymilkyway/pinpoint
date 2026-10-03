<?php

namespace App;

/**
 * Single source of truth for role and permission names, shared by the seeder
 * and the RBAC controller so the two can never drift apart.
 */
final class Rbac
{
    public const VIEW_PERMISSION = 'addresses.view';

    public const CREATE_PERMISSION = 'addresses.create';

    public const EDIT_PERMISSION = 'addresses.edit';

    public const DELETE_PERMISSION = 'addresses.delete';

    public const EXPORT_PERMISSION = 'addresses.export';

    /**
     * Asking for a change rather than making one. This is the Customer's only
     * write-adjacent capability, and it is what the requests queue is fed by.
     */
    public const REQUEST_PERMISSION = 'addresses.request';

    /**
     * Deciding those requests. Held by both readers, because both already hold
     * create and edit: the authority to approve is the same authority, and
     * splitting it would mean the queue a reader can see is not one they can
     * clear.
     */
    public const APPROVE_PERMISSION = 'addresses.approve';

    /**
     * Reading the audit log. Deliberately separate from addresses.view: knowing
     * who changed what is not the same as being allowed to see the addresses.
     */
    public const AUDIT_PERMISSION = 'audit.view';

    public const MANAGE_PERMISSION = 'rbac.manage';

    /**
     * Every permission, named through the constants above.
     *
     * Written as constants rather than as a second list of strings because a
     * literal that drifts from the constant is invisible: the policy would check
     * a name no role holds and every request would be denied, with nothing in
     * the code looking wrong.
     */
    public const PERMISSIONS = [
        self::VIEW_PERMISSION,
        self::CREATE_PERMISSION,
        self::EDIT_PERMISSION,
        self::DELETE_PERMISSION,
        self::EXPORT_PERMISSION,
        self::REQUEST_PERMISSION,
        self::APPROVE_PERMISSION,
        self::AUDIT_PERMISSION,
        self::MANAGE_PERMISSION,
    ];

    /**
     * The roles, in descending authority.
     *
     * Superadmin and Admin both read the whole directory - that is
     * User::seesEveryAddress(), and it is what separates them from Customer,
     * which only ever sees the addresses it owns. What separates the two
     * reading roles is authority rather than visibility: the Superadmin also
     * governs roles and may delete, the Admin may not.
     */
    public const SUPERADMIN_ROLE = 'Superadmin';

    public const ADMIN_ROLE = 'Admin';

    public const CUSTOMER_ROLE = 'Customer';

    /**
     * What the Superadmin holds: every permission except asking.
     *
     * Asking is what an account with no write access does. Both readers write to
     * the directory directly, so a request raised by one of them would be a
     * proposal they could approve themselves - a row in the queue that means
     * nothing. Written out rather than derived from PERMISSIONS because a class
     * constant cannot call array_diff(), and a role whose set is "all of them"
     * is exactly the drift this file exists to prevent.
     */
    public const SUPERADMIN_PERMISSIONS = [
        self::VIEW_PERMISSION,
        self::CREATE_PERMISSION,
        self::EDIT_PERMISSION,
        self::DELETE_PERMISSION,
        self::EXPORT_PERMISSION,
        self::APPROVE_PERMISSION,
        self::AUDIT_PERMISSION,
        self::MANAGE_PERMISSION,
    ];

    /**
     * What the Admin holds: the Superadmin's set without deletion or role
     * management. Asking is absent for the same reason.
     */
    public const ADMIN_PERMISSIONS = [
        self::VIEW_PERMISSION,
        self::CREATE_PERMISSION,
        self::EDIT_PERMISSION,
        self::EXPORT_PERMISSION,
        self::APPROVE_PERMISSION,
        self::AUDIT_PERMISSION,
    ];

    /**
     * What the Customer holds: seeing their own addresses, and asking for a
     * change to them. Nothing that writes.
     */
    public const CUSTOMER_PERMISSIONS = [
        self::VIEW_PERMISSION,
        self::REQUEST_PERMISSION,
    ];

    /** Permission sets granted to each seeded role. */
    public const ROLE_PERMISSIONS = [
        self::SUPERADMIN_ROLE => self::SUPERADMIN_PERMISSIONS,
        self::ADMIN_ROLE => self::ADMIN_PERMISSIONS,
        self::CUSTOMER_ROLE => self::CUSTOMER_PERMISSIONS,
    ];
}
