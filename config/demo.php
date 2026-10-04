<?php

use App\Rbac;

return [

    /*
    |--------------------------------------------------------------------------
    | Demo account picker
    |--------------------------------------------------------------------------
    |
    | Renders a list of ready-made accounts on the sign-in page so a reviewer
    | can move between the Superadmin, Admin and Customer roles without typing
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
            'role' => Rbac::SUPERADMIN_ROLE,
            'name' => 'Ana Reyes',
            'email' => env('SEED_ADMIN_EMAIL', 'ana.reyes@gmail.com'),
            'password' => env('SEED_ADMIN_PASSWORD'),
            'phone' => '+639181234567',
        ],
        [
            'role' => Rbac::ADMIN_ROLE,
            'name' => 'Miguel Santos',
            'email' => env('SEED_MANAGER_EMAIL', 'miguel.santos@gmail.com'),
            'password' => env('SEED_DEMO_PASSWORD'),
            'phone' => '+639273456789',
        ],
        [
            'role' => Rbac::CUSTOMER_ROLE,
            'name' => 'Liza Mendoza',
            'email' => env('SEED_VIEWER_EMAIL', 'liza.mendoza@gmail.com'),
            'password' => env('SEED_DEMO_PASSWORD'),
            'phone' => '+639365432109',
        ],
    ],

    /*
    | Shared password for everything below the picker: the Admin and Customer
    | accounts, and the extra owners. Kept here rather than read with env() in
    | the seeder so it survives config caching.
    */
    'owners_password' => env('SEED_DEMO_PASSWORD'),

    /*
    | Extra address owners, seeded so the directory is not owned by three
    | accounts. They share the demo password and are not offered on the picker.
    | The mobile numbers are the canonical form the app stores - a real
    | allocation, so nothing in the directory reads as a placeholder. Held here
    | alongside the name they belong to rather than derived in the seeder.
    */
    'owners' => [
        ['name' => 'Juan Dela Cruz', 'email' => 'juan.delacruz@gmail.com', 'phone' => '+639451122334'],
        ['name' => 'Maria Santos', 'email' => 'maria.santos@gmail.com', 'phone' => '+639558899001'],
        ['name' => 'Jose Ramirez', 'email' => 'jose.ramirez@gmail.com', 'phone' => '+639667788990'],
        ['name' => 'Rosario Villanueva', 'email' => 'rosario.villanueva@gmail.com', 'phone' => '+639711223344'],
        ['name' => 'Antonio Bautista', 'email' => 'antonio.bautista@gmail.com', 'phone' => '+639855566778'],
    ],

];
