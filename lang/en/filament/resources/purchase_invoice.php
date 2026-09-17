<?php

return [
    'label' => 'Purchase invoice',
    'plural_label' => 'Purchase invoices',
    'navigation_label' => 'Purchase invoices',

    'sections' => [
        'details' => 'Invoice details',
        'amounts' => 'Amounts',
    ],

    'fields' => [
        'items' => 'Lines',
        'item' => 'Item',
    ],

    'hints' => [
        'item' => 'Available: :available · Incoming: :incoming · Sale price: :sale_price',
        'cost' => 'Current unit cost: :cost_price · Previous: :last_cost_price',
    ],

    'actions' => [
        'confirm' => [
            'label' => 'Confirm as the :side',
            'heading' => 'Confirm as the :side',
            'description' => 'The invoice amount is :amount. Write it again to confirm.',
            'amount' => 'Amount',
            'submit' => 'Confirm',
            'done' => 'Confirmed as the :side',
        ],
        'revert_confirmation' => [
            'label' => 'Take back the last confirmation',
            'description' => 'The invoice goes back to the stage before its last confirmation.',
            'done' => 'The last confirmation was taken back',
        ],
        'add_line' => 'Add line',
    ],

    'widgets' => [
        'importer_confirmed_count' => 'Waiting for the receiver',
        'importer_confirmed_count_info' => [
            'explanation' => 'How many purchase invoices the importer confirmed and the receiver has not confirmed yet. Their goods are bought and on their way, so their quantity is the incoming quantity of the items, not the available one. Once the receiver confirms an invoice, it leaves this count and its quantity enters the stock. It is the count right now, so it has no range. Deleted invoices are not counted.',
            'example' => 'There are 6 purchase invoices: 2 still pending, 1 the importer confirmed, and 3 confirmed by both sides. The card shows 1.',
        ],
        'pending_count' => 'Pending invoices',
        'pending_count_info' => [
            'explanation' => 'How many purchase invoices are still pending, meaning they have not been confirmed yet. Once an invoice is confirmed, it leaves this count. It is the count right now, so it has no range. Deleted invoices are not counted.',
            'example' => 'There are 10 purchase invoices: 7 confirmed and 3 still pending. The card shows 3.',
        ],
        'purchases_chart' => [
            'heading' => 'Suppliers we buy from the most',
        ],
    ],
];
