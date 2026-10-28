<?php

return [
    'payment_types' => [
        'cash' => 'Cash',
        'postpaid' => 'Postpaid',
    ],

    'packages' => [
        'packet' => 'Packet',
    ],

    'package_counts' => [
        'packet' => ':count packet|:count packets',
    ],

    'dozens' => ':count dozens',

    'validation' => [
        'quantity_not_whole_packets' => 'An item is sold by the whole packet only: one packet is :size pieces.',
        'quantity_must_be_whole_pieces' => 'The quantity must be whole pieces: there is no half a piece.',
        'item_has_no_packet' => ':item has no packet size, so it cannot be sold.',
        'quantity_more_than_available' => 'There is not enough of :item: only :available left.',
        'quantity_more_than_left_of_power' => 'There is not enough of :item at power :power: only :available left.',
        'choose_a_power' => ':item is sold at a lens power, so choose the power.',
        'quantity_more_than_left_of_packet_type' => 'There is not enough of :item at this power and these colors: only :available packets left.',
        'choose_the_colors' => ':item is sold in mixes of colors, so choose the colors of the packets.',
        'quantity_must_be_positive' => 'The quantity must be more than zero.',
        'price_must_be_positive' => 'The price must be more than zero.',
        'discount_must_not_be_negative' => 'The discount cannot be below zero.',
        'discount_more_than_order' => 'The discount must be less than the order\'s amount.',
        'discount_below_order_cost' => 'The discount is too big: it takes the order below its cost.',
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
        'weekly_payment_postpaid_only' => 'A weekly payment can only be set on a postpaid sale.',
        'weekly_payment_must_be_positive' => 'The weekly payment must be more than zero.',
        'weekly_payment_exceeds_remaining' => 'The weekly payment cannot be more than what is left after the down payment.',
        'invoice_by_invoice_customer_has_debt' => 'This customer works invoice by invoice and still owes money; they cannot take a new order until their debt is zero.',
        'order_needs_items' => 'An order needs at least one item.',
        'paid_order_keeps_customer' => 'This order has payments on it, so its customer cannot change.',
        'paid_order_keeps_payment_type' => 'This order has payments on it, so its payment type cannot change.',
        'order_below_paid_amount' => 'The order cannot be less than what was paid on it (:paid).',
    ],
];
