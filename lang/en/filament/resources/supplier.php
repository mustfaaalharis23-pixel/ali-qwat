<?php

return [
    'label' => 'Supplier',
    'plural_label' => 'Suppliers',
    'navigation_label' => 'Suppliers',

    'fields' => [
        'name_ar' => 'Arabic name',
        'name_zh' => 'Chinese name',
        'phone' => 'Phone',
        'email' => 'Email',
        'contract' => 'Contract',
        'notes' => 'Notes',
        'payment_type' => 'Payment type',
    ],

    'actions' => [
        'distribute_payoff' => [
            'label' => 'Pay',
            'heading' => 'Pay :supplier',
            'debt' => 'Remaining debt: :debt. The amount is paid on the invoices from the oldest to the newest.',
            'amount' => 'Amount',
            'date' => 'Date',
            'done' => 'The amount was paid on the supplier\'s invoices.',
        ],
    ],

    'widgets' => [
        'debts_chart' => [
            'heading' => 'Supplier debts',
        ],
        'totals' => [
            'total_debt' => 'Total debt',
            'total_paid' => 'Total paid',
            'total_purchases' => 'Total purchases',
            'items_count' => 'Items',
        ],
        'totals_info' => [
            'total_debt' => [
                'explanation' => 'What is still owed to this supplier right now: for each of the supplier\'s purchase invoices, its amount minus what has been paid on it, all added together. Deleted invoices are not counted.',
                'example' => 'The supplier has two invoices: 1,000,000 IQD with 700,000 IQD paid, and 400,000 IQD with nothing paid. Total debt = 300,000 + 400,000 = 700,000 IQD.',
            ],
            'total_paid' => [
                'explanation' => 'Everything paid to this supplier so far: the sum of the payoffs on all of the supplier\'s purchase invoices. Deleted invoices are not counted.',
                'example' => 'The supplier was paid 700,000 IQD on the first invoice and 250,000 IQD on the second. Total paid = 700,000 + 250,000 = 950,000 IQD.',
            ],
            'total_purchases' => [
                'explanation' => 'The full value of everything bought from this supplier: the sum of the amounts of all of the supplier\'s purchase invoices, paid or not. Total purchases = total paid + total debt. Deleted invoices are not counted.',
                'example' => 'The supplier has two invoices: 1,000,000 IQD and 400,000 IQD. Total purchases = 1,400,000 IQD, of which 700,000 IQD is paid and 700,000 IQD is still owed.',
            ],
            'items_count' => [
                'explanation' => 'How many items are linked to this supplier, active or not. Deleted items are not counted.',
                'example' => 'The supplier supplies 8 items, 2 of them inactive. The card shows 8.',
            ],
        ],
    ],

    'payment_types' => [
        'cash' => 'Cash',
        'postpaid' => 'Postpaid',
    ],
];
