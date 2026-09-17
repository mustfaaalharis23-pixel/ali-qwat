<?php

return [
    'section' => 'Permissions',

    'abilities' => [
        'view' => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
        'restore' => 'Restore',
        'transfer_cashier_money' => 'Transfer cashier money',
    ],

    'app' => [
        'group' => 'Sales application',
        'orders' => 'Orders screen',
        'checkout' => 'Selling (checkout)',
    ],

    'copy' => [
        'label' => 'Copy from user',
        'user' => 'User',
        'copied' => 'Permissions copied. Click Save to apply them.',
    ],
];
