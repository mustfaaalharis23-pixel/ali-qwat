<?php

return [
    'label' => 'User',
    'plural_label' => 'Users',
    'navigation_label' => 'Users',

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'is_active' => 'Active',
        'is_super' => 'Super account',
        'is_cashier' => 'Cashier',
    ],

    'hints' => [
        'is_super' => 'Gets all permissions',
    ],

    'sections' => [
        'account' => 'Account',
        'role' => 'Status and role',
    ],
];
