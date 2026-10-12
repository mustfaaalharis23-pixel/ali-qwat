<?php

return [
    'title' => 'Profit and loss report',

    'picker' => [
        'label' => 'Month',
        'last_month' => 'Last month',
        'this_month' => 'This month',
        'earlier_year' => 'The year before',
        'later_year' => 'The year after',
        'done' => 'Done',
    ],

    'exclusions' => [
        'clear' => 'Put everything back',
        'excluded' => 'Left out',
        'after' => 'After exclusions',
        'not_checked' => 'Matching is not checked',
        'include' => 'Count :name in the totals',
    ],

    'sections' => [
        'revenue' => 'Revenue',
        'expenses' => 'Expenses',
        'summary' => 'Summary',
    ],

    'columns' => [
        'section' => 'Section',
        'detail' => 'Line',
        'note' => 'Details',
        'amount' => 'Amount',
    ],

    'expenses' => [
        'label' => 'Expenses',
        'grouped' => 'By description',
        'detailed' => 'Line by line',
    ],

    'rows' => [
        'invoice' => 'Invoice',
        'sales_profit' => 'Profit of the sales',
        'total_revenue' => 'Total revenue',
        'total_expenses' => 'Total expenses',
        'net_profit' => 'Net profit',
        'capital_movement' => 'Capital movement (in − out)',
        'value_at_start' => 'Company value at the start of the period',
        'computed_value' => 'Computed company value (start + net profit + capital)',
        'value_at_end' => 'Real company value at the end of the period',
        'matching' => 'Matching',
        'matched' => '✓ Matches',
        'not_matched' => '✗ Does not match',
        'difference' => 'Difference (real − computed)',
    ],

    'value' => [
        'moment' => 'What the company was worth at the end of :moment',
        'stock' => 'Stock at cost',
        'unconfirmed_purchases' => 'Purchases waiting for a confirmation',
        'supplier_balance' => 'Owed to the suppliers',
        'accountant_box' => "Accountant's box",
        'cash_sales_box' => 'Cash sales box',
        'postpaid_sales_box' => 'Postpaid sales box',
        'total' => 'Company value',
    ],

    'totals' => [
        'sales' => 'Total sales',
        'purchases' => 'Total purchases',
        'sales_profit' => 'Sales profit',
        'sales_margin' => 'Profit margin on sales (%)',
    ],
];
