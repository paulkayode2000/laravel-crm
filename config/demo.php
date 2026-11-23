<?php

/**
 * Local demo data used by:
 *   - the one-click sign-in panel on the admin login page;
 *   - Database\Seeders\LocalDemoUsersSeeder (roles + users);
 *   - Database\Seeders\LocalDemoDataSeeder (leads, quotes, contacts, products,
 *     activities, tags, warehouses, user groups).
 *
 * Keeping the accounts here means the login buttons and the seeded users can
 * never drift apart. Everything is local-environment only.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | One-click demo sign-in
    |--------------------------------------------------------------------------
    |
    | Disabled automatically outside the local environment. Set
    | DEMO_LOGIN_ENABLED=true/false to force it either way.
    |
    */
    'enabled' => (bool) env('DEMO_LOGIN_ENABLED', env('APP_ENV', 'production') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Demo roles
    |--------------------------------------------------------------------------
    |
    | ACL keys come from packages/Webkul/Admin/src/Config/acl.php.
    |
    */
    'roles' => [
        'Administrator' => [
            'description' => 'Unrestricted access to every module, setting and user.',
            'permission_type' => 'all',
            'permissions' => [],
        ],

        'Sales Manager' => [
            'description' => 'Owns the pipeline: full lead, quote, contact, activity and product access, including delete.',
            'permission_type' => 'custom',
            'permissions' => [
                'dashboard',
                'leads', 'leads.create', 'leads.create.quick-create', 'leads.view', 'leads.edit', 'leads.delete',
                'quotes', 'quotes.create', 'quotes.create.quick-create', 'quotes.mail', 'quotes.edit', 'quotes.print', 'quotes.delete',
                'activities', 'activities.create', 'activities.edit', 'activities.delete',
                'contacts',
                'contacts.persons', 'contacts.persons.create', 'contacts.persons.create.quick-create', 'contacts.persons.edit', 'contacts.persons.view', 'contacts.persons.delete',
                'contacts.organizations', 'contacts.organizations.create', 'contacts.organizations.create.quick-create', 'contacts.organizations.edit', 'contacts.organizations.delete',
                'products', 'products.create', 'products.create.quick-create', 'products.edit', 'products.view', 'products.delete',
                'mail', 'mail.inbox', 'mail.draft', 'mail.outbox', 'mail.sent', 'mail.trash', 'mail.compose', 'mail.compose.quick-create', 'mail.view', 'mail.edit', 'mail.delete',
                'help',
            ],
        ],

        'Sales Agent' => [
            'description' => 'Works leads and quotes day to day: create and edit, but cannot delete business records or change settings.',
            'permission_type' => 'custom',
            'permissions' => [
                'dashboard',
                'leads', 'leads.create', 'leads.create.quick-create', 'leads.view', 'leads.edit',
                'quotes', 'quotes.create', 'quotes.create.quick-create', 'quotes.mail', 'quotes.edit', 'quotes.print',
                'activities', 'activities.create', 'activities.edit',
                'contacts',
                'contacts.persons', 'contacts.persons.create', 'contacts.persons.create.quick-create', 'contacts.persons.edit', 'contacts.persons.view',
                'contacts.organizations', 'contacts.organizations.create', 'contacts.organizations.create.quick-create', 'contacts.organizations.edit',
                'products', 'products.view',
                'mail', 'mail.inbox', 'mail.draft', 'mail.outbox', 'mail.sent', 'mail.trash', 'mail.compose', 'mail.compose.quick-create', 'mail.view',
                'help',
            ],
        ],

        'Support Agent' => [
            'description' => 'Read-only access to customers and pipeline: can view and print, but cannot create, edit, delete or change settings.',
            'permission_type' => 'custom',
            'permissions' => [
                'dashboard',
                'leads', 'leads.view',
                'quotes', 'quotes.print',
                'activities',
                'contacts',
                'contacts.persons', 'contacts.persons.view',
                'contacts.organizations',
                'products', 'products.view',
                'mail', 'mail.inbox', 'mail.sent', 'mail.view',
                'help',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo accounts
    |--------------------------------------------------------------------------
    |
    | `label` is what the login panel shows; `description` explains which
    | permissions the account demonstrates. view_permission = individual means
    | the user only sees records they own (Krayin's built-in scoping).
    |
    */
    'accounts' => [
        [
            'label' => 'Administrator',
            'name' => 'Demo Administrator',
            'email' => 'admin@krayin.local',
            'password' => env('DEMO_ADMIN_PASSWORD', 'KrayinAdmin3307x2026'),
            'role' => 'Administrator',
            'view_permission' => 'global',
            'description' => 'Every record plus settings, users, roles, pipelines and configuration.',
        ],
        [
            'label' => 'Sales Manager',
            'name' => 'Priya Raman',
            'email' => 'manager@krayin.local',
            'password' => env('DEMO_USERS_PASSWORD', 'KrayinDemo3307x2026'),
            'role' => 'Sales Manager',
            'view_permission' => 'global',
            'description' => 'Sees the whole pipeline; full create/edit/delete on leads, quotes, contacts and products.',
        ],
        [
            'label' => 'Sales Agent',
            'name' => 'Daniel Okoro',
            'email' => 'agent@krayin.local',
            'password' => env('DEMO_USERS_PASSWORD', 'KrayinDemo3307x2026'),
            'role' => 'Sales Agent',
            'view_permission' => 'individual',
            'description' => 'Owns their own leads and quotes; can create and edit, but delete buttons are hidden.',
        ],
        [
            'label' => 'Support Agent',
            'name' => 'Mei Chen',
            'email' => 'support@krayin.local',
            'password' => env('DEMO_USERS_PASSWORD', 'KrayinDemo3307x2026'),
            'role' => 'Support Agent',
            'view_permission' => 'individual',
            'description' => 'Read-only: can view customers, quotes and activities, and print quotes.',
        ],
    ],
];
