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
     * Seeing the whole requests queue without being able to decide it. A
     * Customer reaches their own requests through REQUEST_PERMISSION and an
     * approver through APPROVE_PERMISSION, so this is only needed for a role
     * that should watch the queue read-only.
     */
    public const REQUESTS_VIEW_PERMISSION = 'requests.view';

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
        self::REQUESTS_VIEW_PERMISSION,
        self::REQUEST_PERMISSION,
        self::APPROVE_PERMISSION,
        self::AUDIT_PERMISSION,
        self::MANAGE_PERMISSION,
    ];

    /**
     * The roles matrix, grouped by the screen each permission governs, in the
     * order the matrix draws them. Grouped here rather than by machine-name
     * prefix because addresses.request and addresses.approve belong to the
     * Requests screen; renaming them would orphan the rows already granted in
     * every database. RbacTest asserts every permission sits in exactly one
     * module.
     */
    public const MODULES = [
        'Addresses' => [
            self::VIEW_PERMISSION,
            self::CREATE_PERMISSION,
            self::EDIT_PERMISSION,
            self::DELETE_PERMISSION,
            self::EXPORT_PERMISSION,
        ],
        'Requests' => [
            self::REQUESTS_VIEW_PERMISSION,
            self::REQUEST_PERMISSION,
            self::APPROVE_PERMISSION,
        ],
        'Audit log' => [
            self::AUDIT_PERMISSION,
        ],
        'Administration' => [
            self::MANAGE_PERMISSION,
        ],
    ];

    /**
     * What each permission is called where a person reads it rather than
     * matches it.
     *
     * Every label leads with its verb, so rows scan as actions. Order on the
     * matrix comes from MODULES, not from sorting these.
     *
     * Keyed by the constants rather than written as a second list of strings,
     * for the same reason PERMISSIONS is: a literal that drifts is invisible.
     * RbacTest asserts the two maps cover each other.
     */
    public const LABELS = [
        self::VIEW_PERMISSION => 'View addresses',
        self::CREATE_PERMISSION => 'Create addresses',
        self::EDIT_PERMISSION => 'Edit addresses',
        self::DELETE_PERMISSION => 'Delete addresses',
        self::EXPORT_PERMISSION => 'Export to Excel',
        self::REQUESTS_VIEW_PERMISSION => 'View all requests',
        self::REQUEST_PERMISSION => 'Request a change',
        self::APPROVE_PERMISSION => 'Approve requests',
        self::AUDIT_PERMISSION => 'Read the audit log',
        self::MANAGE_PERMISSION => 'Manage roles & permissions',
    ];

    /**
     * The readable name for a permission, or the machine name if it has none.
     *
     * Falls back rather than throwing: a permission added without a label should
     * render as its raw name on the roles screen, not take the screen down.
     * RbacTest is what makes the fallback unreachable in practice.
     */
    public static function label(string $permission): string
    {
        return self::LABELS[$permission] ?? $permission;
    }

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
        self::REQUESTS_VIEW_PERMISSION,
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
        self::REQUESTS_VIEW_PERMISSION,
        self::APPROVE_PERMISSION,
        self::AUDIT_PERMISSION,
    ];

    /**
     * What the Customer holds: seeing their own addresses, asking for a change
     * to them, and exporting what they can see.
     *
     * Export is not a write - the export is built from the same scoped query the
     * table is, so a Customer can only ever download their own rows. Nothing
     * that writes.
     */
    public const CUSTOMER_PERMISSIONS = [
        self::VIEW_PERMISSION,
        self::EXPORT_PERMISSION,
        self::REQUEST_PERMISSION,
    ];

    /** Permission sets granted to each seeded role. */
    public const ROLE_PERMISSIONS = [
        self::SUPERADMIN_ROLE => self::SUPERADMIN_PERMISSIONS,
        self::ADMIN_ROLE => self::ADMIN_PERMISSIONS,
        self::CUSTOMER_ROLE => self::CUSTOMER_PERMISSIONS,
    ];
}
