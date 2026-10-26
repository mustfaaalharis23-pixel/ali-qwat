<?php

return [
    'label' => 'Customer payment',
    'plural_label' => 'Customer payments',
    'navigation_label' => 'Customer payments',

    'fields' => [
        'customer_id' => 'Customer',
        'amount' => 'Amount',
        'is_down_payment' => 'Down payment',
        'date' => 'Date',
        'image' => 'Receipt image',
        'notes' => 'Notes',
        'user_id' => 'Recorded by',
        'invoice_id' => 'Withdrawal invoice',
        'allocations' => 'Paid orders',
        'order' => 'Order',
        'order_date' => 'Order date',
        'order_total' => 'Order total',
        'allocation_amount' => 'Paid on it',
    ],

    'sections' => [
        'details' => 'Payment details',
        'receipt' => 'Receipt',
    ],

    'actions' => [
        'print_receipt' => 'Print receipt',
    ],

    'filters' => [
        'from' => 'From',
        'until' => 'Until',
    ],
];
