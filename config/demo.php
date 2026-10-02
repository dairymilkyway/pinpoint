<?php

use App\Rbac;

return [

    /*
    |--------------------------------------------------------------------------
    | Demo account picker
    |--------------------------------------------------------------------------
    |
    | Renders a list of ready-made accounts on the sign-in page so a reviewer
    | can move between the Admin, Manager and Viewer roles without typing
    | credentials. This exists for a showcase and is off unless explicitly
    | switched on; DemoLogin::enabled() also refuses it outright whenever
    | APP_ENV is production, so turning it on by accident cannot expose a
    | working admin login on a live deployment.
    |
    | The passwords are read from the environment - never committed here - and
    | the accounts below are the same ones UserSeeder creates, so the picker can
    | never advertise an account that does not exist.
    |
    */

    'enabled' => (bool) env('DEMO_LOGIN_ENABLED', false),

    'accounts' => [
        [
            'role' => Rbac::ADMIN_ROLE,
            'name' => 'Ana Reyes',
            'email' => env('SEED_ADMIN_EMAIL'),
            'password' => env('SEED_ADMIN_PASSWORD'),
        ],
        [
            'role' => Rbac::MANAGER_ROLE,
            'name' => 'Miguel Santos',
            'email' => env('SEED_MANAGER_EMAIL', 'manager@example.com'),
            'password' => env('SEED_DEMO_PASSWORD'),
        ],
        [
            'role' => Rbac::VIEWER_ROLE,
            'name' => 'Liza Mendoza',
            'email' => env('SEED_VIEWER_EMAIL', 'viewer@example.com'),
            'password' => env('SEED_DEMO_PASSWORD'),
        ],
    ],

    /*
    | Shared password for everything below the picker: the Manager and Viewer
    | accounts, and the extra owners. Kept here rather than read with env() in
    | the seeder so it survives config caching.
    */
    'owners_password' => env('SEED_DEMO_PASSWORD'),

    /*
    | Extra address owners, seeded so the directory is not owned by three
    | accounts. They share the demo password and are not offered on the picker.
    */
    'owners' => [
        'Juan Dela Cruz' => 'juan.delacruz@example.com',
        'Maria Santos' => 'maria.santos@example.com',
        'Jose Ramirez' => 'jose.ramirez@example.com',
        'Rosario Villanueva' => 'rosario.villanueva@example.com',
        'Antonio Bautista' => 'antonio.bautista@example.com',
    ],

];
