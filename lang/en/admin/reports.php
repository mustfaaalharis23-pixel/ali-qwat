<?php

return [
    'descriptions' => [
        'sales' => 'One row per day with sales: its cash sales, its postpaid sales, and their total. The sales are the orders\' total amounts, so a discount is counted. Deleted orders are not counted.',
        'purchases' => 'One row per supplier with its purchases inside the period, from the largest to the smallest. The purchases are the purchase invoices\' amounts, pending or confirmed. Open a supplier to see the items bought from it.',
        'item_sales' => 'The items sold inside the period with their sold quantity and sales amount, ranked by the chosen sort. Deleted orders and deleted lines are not counted.',
        'golden' => 'The items that carry the period: the head of each chosen list, each item once, from the highest profit down. The profit is the one every sale line froze when it was sold.',
        'profit_and_loss' => 'What the month earned, where it earned it, where it spent it, and whether the company value proves it. Untick a description to read the month without it.',
    ],
    'period' => [
        'single_day' => 'Single day',
        'last_three_months' => 'Last 3 months',
        'quick' => 'Quick periods',
    ],
    'chart' => [
        'daily_sales' => 'Sales by day',
    ],
    'expenses' => [
        'lines' => ':count lines',
    ],
];
