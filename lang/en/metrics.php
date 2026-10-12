<?php

return [
    'range' => 'Range',

    'info' => [
        'open' => 'About this card',
        'explanation' => 'Explanation',
        'example' => 'Example',
    ],

    'currencies' => [
        'IQD' => 'IQD',
        'USD' => '$',
        'CNY' => '¥',
    ],

    'cashier_money_transfer' => [
        'cashier' => 'Cashier',
        'choose_cashier' => 'Choose a cashier',
        'transfer' => 'Transfer',
        'confirm_heading' => 'Confirm the transfer',
        'confirm_description' => 'All the money on the cashier will be transferred to the accountant\'s box. Part of it cannot be transferred.',
        'description' => 'Description',
        'amount' => 'Amount',
        'transferred' => 'An amount of :amount was transferred from :cashier to the accountant\'s box.',
        'failed' => 'The transfer was not made',
    ],

    'cards' => [
        'stock_value' => 'Stock value',
        'cashier_money_transfer' => 'Cashier money transfer',
        'cash_sales_box' => 'Cash sales box',
        'postpaid_sales_box' => 'Postpaid sales box',
        'accountant_balance' => 'Accountant\'s box',
        'general_expenses' => 'General expenses',
        'purchase_expenses' => 'Purchase expenses',
        'revenues' => 'Revenues',
        'invoice_count' => 'Invoice count',
        'total_debt' => 'Total debt',
        'item_count' => 'Total items',
        'active_item_count' => 'Active items',
        'inactive_item_count' => 'Inactive items',
        'item_without_supplier_count' => 'Items without a supplier',
    ],

    'cards_info' => [
        'stock_value' => [
            'explanation' => 'What the stock is worth right now: for every active item that still has a quantity, its available quantity times what one unit of it last cost, all added up. An item that ran out adds nothing, and an inactive or deleted item is not counted. A quantity that was bought and did not arrive yet is not counted either, because it is not in the stock. It is the value now, so it has no range.',
            'example' => 'Two items have stock: 108 units at a cost of 12,000 and 45 units at a cost of 13,000. The stock value = 1,296,000 + 585,000 = 1,881,000 IQD.',
        ],
        'cash_sales_box' => [
            'explanation' => 'The money in the cash sales box now that is not transferred to the accountant\'s box yet: all the cash sales from the orders of every cashier, minus everything transferred from the cash. An order counts with its final amount after any discount. Deleted orders and invoices are not counted. It is the balance as of today, so it has no range.',
            'example' => 'Cashier A sold 800,000 IQD in cash and cashier B sold 500,000 IQD, and 900,000 IQD of cash was transferred. Cash sales box = 1,300,000 − 900,000 = 400,000 IQD.',
        ],
        'postpaid_sales_box' => [
            'explanation' => 'The money in the postpaid sales box now that is not transferred to the accountant\'s box yet: all the postpaid sales from the orders, minus everything transferred from the postpaid. An order counts with its final amount after any discount. Deleted orders and invoices are not counted. It is the balance as of today, so it has no range.',
            'example' => 'Postpaid orders were sold for 1,200,000 IQD, and 700,000 IQD of postpaid was transferred. Postpaid sales box = 1,200,000 − 700,000 = 500,000 IQD.',
        ],
        'cashier_money_transfer' => [
            'explanation' => 'The list shows every cashier with the money they hold that is not transferred to the accountant\'s box yet: the cashier\'s cash sales from orders minus everything transferred from the cashier. Choose a cashier and click Transfer: a confirmation shows the cashier, the description, and the amount, then all of the money is transferred, and part of it cannot be transferred. A transfer creates an input invoice under the cash transfer description, so the money on the cashier becomes zero and the accountant\'s box goes up by the same amount. Postpaid sales are not counted here, and deleted orders and invoices are not counted.',
            'example' => 'Cashier A sold 820,000 IQD in cash, and 410,000 IQD was transferred before, so the list shows: Cashier A — 410,000 IQD. The transfer moves all 410,000 IQD, and the money on the cashier becomes 0 IQD.',
        ],
        'accountant_balance' => [
            'explanation' => 'The money in the accountant\'s box right now: everything paid on the revenues (Input invoices) minus everything paid on the expenses (Output invoices, purchase invoices included). For a purchase invoice, only what has been paid to the supplier counts, not the full invoice amount. Deleted invoices are not counted. A negative number means more money went out than came in.',
            'example' => '1,000,000 IQD came in and 400,000 IQD went out. The box = 1,000,000 − 400,000 = 600,000 IQD.',
        ],
        'general_expenses' => [
            'explanation' => 'The total money paid on the Output general invoices dated in the chosen range, without the purchase invoices. A general invoice is always fully paid, so it counts with its full amount. Purchase invoices are not here; they show in the purchase expenses. Deleted invoices are not counted.',
            'example' => 'This month there is an electricity invoice of 150,000 IQD and a rent invoice of 300,000 IQD. General expenses = 150,000 + 300,000 = 450,000 IQD.',
        ],
        'purchase_expenses' => [
            'explanation' => 'The total money paid to the suppliers on the purchase invoices dated in the chosen range. Only what has been paid counts, not the full invoice amount; what is still owed shows in the total debt. A payment counts on the date of its invoice. Deleted invoices are not counted.',
            'example' => 'This month there is a purchase invoice of 1,000,000 ¥ with 400,000 ¥ paid. Purchase expenses = 400,000 ¥, and the remaining 600,000 ¥ is in the total debt.',
        ],
        'revenues' => [
            'explanation' => 'The total money that came in on the Input invoices dated in the chosen range. An Input invoice is always fully paid, so it counts with its full amount. Deleted invoices are not counted.',
            'example' => 'This month there are two Input invoices: 700,000 IQD and 300,000 IQD. Revenues = 700,000 + 300,000 = 1,000,000 IQD.',
        ],
        'invoice_count' => [
            'explanation' => 'How many invoices are dated in the chosen range, of every kind: Input invoices and Output invoices, purchase invoices included, whatever their status. Deleted invoices are not counted.',
            'example' => 'Last month there were 3 Input invoices and 6 Output invoices (2 of them purchase invoices). Invoice count = 3 + 6 = 9.',
        ],
        'total_debt' => [
            'explanation' => 'What is still owed to all the suppliers right now: for every purchase invoice, its amount minus what has been paid on it, all added together. A fully paid invoice adds nothing. It is the debt as of today, so it has no range. Deleted invoices are not counted.',
            'example' => 'Supplier A has an invoice of 1,000,000 ¥ with 600,000 ¥ paid, and supplier B has an invoice of 500,000 ¥ with nothing paid. Total debt = 400,000 + 500,000 = 900,000 ¥.',
        ],
        'item_count' => [
            'explanation' => 'How many items there are, active and inactive together. Deleted items are not counted. It is the count right now, so it has no range.',
            'example' => 'There are 40 active items and 5 inactive items. Total items = 40 + 5 = 45.',
        ],
        'active_item_count' => [
            'explanation' => 'How many items are marked as active. Deleted items are not counted. It is the count right now, so it has no range.',
            'example' => 'Out of 45 items, 5 are inactive. Active items = 45 − 5 = 40.',
        ],
        'inactive_item_count' => [
            'explanation' => 'How many items are marked as inactive. Deleted items are not counted. It is the count right now, so it has no range.',
            'example' => 'Out of 45 items, 40 are active. Inactive items = 45 − 40 = 5.',
        ],
        'item_without_supplier_count' => [
            'explanation' => 'How many active items have no supplier set. It helps find the items that still need a supplier. Inactive and deleted items are not counted. It is the count right now, so it has no range.',
            'example' => 'Out of 45 active items, 42 are linked to suppliers and 3 have no supplier. The card shows 3.',
        ],
    ],

    'ranges' => [
        'all' => 'All time',
        'today' => 'Today',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'first_quarter' => 'First quarter',
        'second_quarter' => 'Second quarter',
        'third_quarter' => 'Third quarter',
        'fourth_quarter' => 'Fourth quarter',
        'this_year' => 'This year',
        'last_year' => 'Last year',
    ],
];
