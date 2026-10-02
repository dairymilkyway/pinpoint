<?php

namespace App;

/**
 * Single source of truth for role and permission names, shared by the seeder
 * and the RBAC controller so the two can never drift apart.
 */
final class Rbac
{
    public const PERMISSIONS = [
        'addresses.view',
        'addresses.create',
        'addresses.edit',
        'addresses.delete',
        'addresses.export',
        'rbac.manage',
    ];

    public const ADMIN_ROLE = 'Admin';

    public const MANAGER_ROLE = 'Manager';

    public const VIEWER_ROLE = 'Viewer';

    public const MANAGE_PERMISSION = 'rbac.manage';

    /** Permission sets granted to each seeded role. */
    public const ROLE_PERMISSIONS = [
        self::ADMIN_ROLE => self::PERMISSIONS,
        self::MANAGER_ROLE => [
            'addresses.view',
            'addresses.create',
            'addresses.edit',
            'addresses.export',
        ],
        self::VIEWER_ROLE => [
            'addresses.view',
        ],
    ];
}
