<?php

return [
    'title' => 'Customer payments',

    'tabs' => [
        'payments' => 'Payments',
        'statement' => 'Account statement',
        'due_today' => 'Due today',
        'late' => 'Late payments',
    ],

    'reminders' => [
        'customer' => 'Customer',
        'phone' => 'Phone',
        'due_amount' => 'Due today',
        'late_amount' => 'Late',
        'due_since' => 'Due since',
        'days_late' => 'Days late',
        'days' => ':days days',
        'empty_due' => 'No payments are due today',
        'empty_late' => 'No late payments',
    ],

    'order_reminders' => [
        'due_today_value' => ':amount due today',
        'late_value' => ':amount late',
        'remaining_value' => ':amount left',
        'tab_today' => 'Due today',
        'tab_late' => 'Late',
        'tab_all' => 'All',
        'due_today' => 'Due today',
        'empty_today' => 'No payments due today',
        'empty_late' => 'No late payments',
        'heading' => 'Weekly payment reminders',
        'description' => 'Every postpaid order with a weekly payment, until it is fully paid. Late customers come first.',
        'remaining' => 'Left',
        'orders' => 'Orders',
        'order' => 'Order #:number',
        'weekly' => ':amount a week',
        'not_late' => 'Not late',
        'late_for' => 'For :days days',
        'late' => ':amount late · :days days',
        'next' => 'Next :amount · :date',
        'next_today' => 'Next :amount · today',
        'no_next' => 'Every payment fell due',
        'left' => ':amount left · payments left :weeks',
        'empty' => 'No orders with weekly payments left',
    ],
];
