<?php

return [
    'payment_types' => [
        'cash' => 'Cash',
        'postpaid' => 'Postpaid',
    ],

    'packages' => [
        'piece' => 'Piece',
        'dozen' => 'Dozen',
        'packet' => 'Packet',
    ],

    'package_counts' => [
        'piece' => ':count piece|:count pieces',
        'dozen' => ':count dozen|:count dozen',
        'packet' => ':count packet|:count packets',
    ],

    'validation' => [
        'quantity_not_whole_packages' => 'The quantity must make whole packages: one :package is :size pieces.',
        'quantity_more_than_available' => 'There is not enough of :item: only :available left.',
        'quantity_must_be_positive' => 'The quantity must be more than zero.',
        'price_must_be_positive' => 'The price must be more than zero.',
        'last_item_cannot_be_deleted' => 'The last item of an order cannot be deleted.',
        'customer_required' => 'An order needs an existing customer.',
        'payment_type_required' => 'An order needs a payment type.',
        'item_without_sale_price' => 'The item :item has no sale price.',
        'item_sale_price_below_cost_price' => 'The item :item cannot be sold for less than its cost price.',
        'order_has_payments' => 'An order that has payments on it cannot be deleted. Delete its payments first.',
        'payment_amount_must_be_positive' => 'The amount must be more than zero.',
        'payment_exceeds_customer_debt' => 'The amount cannot be more than the customer\'s remaining debt (:debt).',
        'payment_description_missing' => 'There is no description for postpaid money transfers.',
        'payment_needs_postpaid_order' => 'A payment can only be added to a postpaid order.',
        'payment_on_deleted_order' => 'A deleted order cannot get a payment.',
        'payment_order_cannot_change' => 'A payment cannot be moved to another order.',
        'payments_exceed_order_amount' => 'The payments cannot add up to more than the order amount.',
        'down_payment_postpaid_only' => 'A down payment can only be taken with a postpaid sale.',
        'down_payment_must_be_positive' => 'The down payment must be more than zero.',
        'down_payment_exceeds_order_amount' => 'The down payment cannot be more than the order amount.',
    ],
];
