<?php

return [
    'label' => 'Invoice',
    'plural_label' => 'Invoices',

    'fields' => [
        'name' => 'Name',
        'type' => 'Type',
        'general_description_id' => 'General description',
        'supplier_id' => 'Supplier',
        'invoice_category_id' => 'Category',
        'container_id' => 'Container',
        'user_id' => 'Created by',
        'cashier_id' => 'Cashier',
        'date' => 'Date',
        'amount' => 'Amount',
        'amount_iqd' => 'Amount in dinars',
        'total_iqd' => 'Total with transport',
        'dinar_rate' => 'Dollar rate in dinars',
        'transport_cost_iqd' => 'Transport in dinars',
        'amount_usd' => 'Amount in dollars',
        'exchange_rate' => 'Exchange rate',
        'exchange_rate_source' => 'Exchange rate source',
        'paid_amount' => 'Paid amount',
        'remaining' => 'Remaining',
        'cbm' => 'Invoice CBM',
        'cbm_price' => 'Price per cubic meter (CBM)',
        'transport_cost' => 'Transport cost',
        'status' => 'Status',
        'stage' => 'Stage',
        'importer_checker_id' => 'Confirmed by the importer',
        'importer_confirmed_at' => 'Importer confirmed at',
        'receiver_checker_id' => 'Confirmed by the receiver',
        'receiver_confirmed_at' => 'Receiver confirmed at',
        'awaited_confirmation' => 'Waiting for',
        'original_invoice_id' => 'Original invoice',
        'split_from_invoice_id' => 'Split from',
        'profit_difference' => 'Profit difference',
        'images' => 'Images',
        'notes' => 'Notes',
    ],

    'exchange_rate_sources' => [
        'agricultural_bank_of_china' => 'Agricultural Bank of China',
        'china_merchants_bank' => 'China Merchants Bank',
        'manual' => 'Typed by hand',
    ],

    'stages' => [
        'in_production' => 'In production',
        'manufactured' => 'Manufactured',
        'received_at_warehouse' => 'Received at the warehouse',
        'on_the_way' => 'On the way',
        'received' => 'Received',
    ],

    'statuses' => [
        'pending' => 'Pending',
        'importer_confirmed' => 'Confirmed by the importer',
        'confirmed' => 'Confirmed',
    ],

    'confirmations' => [
        'importer' => 'Importer',
        'receiver' => 'Receiver',
    ],

];
