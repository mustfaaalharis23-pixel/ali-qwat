<?php

return [
    'label' => 'Customer',
    'plural_label' => 'Customers',
    'navigation_label' => 'Customers',

    'fields' => [
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'contract' => 'Contract',
        'payment_type' => 'Payment type',
    ],

    'sections' => [
        'details' => 'Customer details',
    ],

    'actions' => [
        'pay_debt' => [
            'label' => 'Pay debt',
            'heading' => 'Pay the debt of :customer',
            'debt' => 'Remaining debt: :debt',
            'customer' => 'Customer',
            'amount' => 'Amount paid',
            'pay_all' => 'Pay all',
            'date' => 'Payment date',
            'image' => 'Receipt or cheque image',
            'notes' => 'Notes',
            'submit' => 'Record payment',
            'done' => 'The payment was recorded.',
            'preview' => [
                'choose_customer' => 'Choose a customer to see their debt.',
                'current_debt' => 'Current debt',
                'paying' => 'Paying now',
                'after' => 'Remaining after this payment',
                'spread' => 'The orders the customer owes on, oldest first. The amount pays them in this order:',
                'order' => 'Order #:id · :date',
                'order_remaining' => 'Remaining on it: :amount',
                'full' => 'Fully paid',
                'partial' => 'Partly paid',
                'untouched' => 'Not paid now',
            ],
        ],
        'statement' => 'Account statement',
    ],

    'statement' => [
        'fields' => [
            'customer' => 'Customer',
            'customer_placeholder' => 'Choose a customer',
            'from' => 'From date',
            'until' => 'Until date',
        ],
        'summary' => [
            'orders' => 'Postpaid orders',
            'payments' => 'Payments',
            'balance' => 'Balance owed',
        ],
        'columns' => [
            'date' => 'Date',
            'movement' => 'Movement',
            'owed' => 'Owed (+)',
            'paid' => 'Paid (−)',
            'balance' => 'Balance owed',
        ],
        'types' => [
            'opening_balance' => 'Previous balance',
            'order' => 'Postpaid order',
            'payment' => 'Payment',
            'down_payment' => 'Down payment',
        ],
        'details' => [
            'opening_balance' => 'Everything before :date',
            'order' => 'Order #:id · :items items',
            'payment_by' => 'Recorded by :user',
        ],
        'actions' => [
            'open_order' => 'Order',
            'open_receipt' => 'Receipt',
        ],
        'empty' => 'No postpaid orders or payments in this period.',
        'choose_customer' => 'Choose a customer above to see their account statement.',
    ],

    'widgets' => [
        'days' => 'day|days',
        'since_date' => ':unit · :date',
        'totals' => [
            'debt' => 'Debt',
            'total_paid' => 'Total paid',
            'last_payment' => 'Since last payment',
            'total_sales' => 'Total sales',
            'orders_count' => 'Orders',
            'average_order' => 'Average order',
            'items_count' => 'Items',
            'last_order' => 'Since last order',
        ],
        'totals_info' => [
            'debt' => [
                'explanation' => 'What this customer still owes right now: for each of the customer\'s postpaid orders, its total amount minus what has been paid on it, all added together. Cash orders are paid when they are sold, so they have no debt. Deleted orders are not counted.',
                'example' => 'The customer has two postpaid orders: 500,000 IQD with 300,000 IQD paid, and 200,000 IQD with nothing paid. Debt = 200,000 + 200,000 = 400,000 IQD.',
            ],
            'total_paid' => [
                'explanation' => 'Everything this customer has paid so far: the sum of the amounts of the customer\'s payments. Deleted payments are not counted.',
                'example' => 'The customer paid 300,000 IQD, then 150,000 IQD. Total paid = 450,000 IQD.',
            ],
            'last_payment' => [
                'explanation' => 'How many days have passed since this customer\'s last payment, with the date of that payment beside them. It helps to spot customers who stopped paying. Deleted payments are not counted, and a customer without payments shows a dash.',
                'example' => 'Today is 2026/05/20 and the customer\'s last payment was on 2026/05/15. The card shows 5 days · 2026/05/15.',
            ],
            'total_sales' => [
                'explanation' => 'The full value of everything sold to this customer: the sum of the total amounts of all of the customer\'s orders, cash or postpaid. Deleted orders are not counted.',
                'example' => 'The customer has two orders: 250,000 IQD and 150,000 IQD. Total sales = 400,000 IQD.',
            ],
            'orders_count' => [
                'explanation' => 'How many orders this customer has made, cash or postpaid. Deleted orders are not counted.',
                'example' => 'The customer made 5 orders and one of them was deleted. The card shows 4.',
            ],
            'average_order' => [
                'explanation' => 'How much this customer spends in one order on average: total sales divided by the number of orders. Deleted orders are not counted.',
                'example' => 'The customer has three orders: 100,000, 200,000, and 300,000 IQD. Average order = 600,000 ÷ 3 = 200,000 IQD.',
            ],
            'items_count' => [
                'explanation' => 'How many different items this customer has bought. An item bought in more than one order is counted once. Items of deleted orders are not counted.',
                'example' => 'The customer bought item A in two orders and item B in one order. The card shows 2.',
            ],
            'last_order' => [
                'explanation' => 'How many days have passed since this customer\'s last order, with the date of that order beside them. It helps to spot customers who stopped ordering. Deleted orders are not counted, and a customer without orders shows a dash.',
                'example' => 'Today is 2026/05/20 and the customer\'s last order was on 2026/05/08. The card shows 12 days · 2026/05/08.',
            ],
        ],
    ],

    'payment_types' => [
        'cash' => 'Cash',
        'postpaid' => 'Postpaid',
    ],
];
