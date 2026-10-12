<?php

return [
    'label' => 'Order',
    'plural_label' => 'Orders',
    'navigation_label' => 'Orders',

    'fields' => [
        'cost_price' => 'Cost price',
        'total_cost' => 'Total cost',
        'profit' => 'Profit',
        'profit_margin_percentage' => 'Profit margin %',
        'customer_id' => 'Customer',
        'user_id' => 'Created by',
        'cashier_id' => 'Cashier',
        'payment_type' => 'Payment type',
        'date' => 'Date',
        'amount' => 'Amount',
        'total_amount' => 'Total amount',
        'paid_amount' => 'Paid',
        'remaining' => 'Remaining',
        'down_payment' => 'Down payment',
        'notes' => 'Notes',
        'items' => 'Items',
        'items_count' => 'Items count',
        'item' => 'Item',
        'quantity' => 'Quantity',
        'price' => 'Price',
        'total_price' => 'Total price',
    ],

    'sections' => [
        'details' => 'Order details',
        'amounts' => 'Amounts',
    ],

    'actions' => [
        'add_line' => 'Add item',
    ],
];
