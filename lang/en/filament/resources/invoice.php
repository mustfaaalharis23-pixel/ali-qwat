<?php

return [
    'label' => 'Invoice',
    'plural_label' => 'Invoices',

    'fields' => [
        'name' => 'Name',
        'type' => 'Type',
        'general_description_id' => 'General description',
        'supplier_id' => 'Supplier',
        'user_id' => 'Created by',
        'cashier_id' => 'Cashier',
        'date' => 'Date',
        'amount' => 'Amount',
        'paid_amount' => 'Paid amount',
        'remaining' => 'Remaining',
        'status' => 'Status',
        'importer_checker_id' => 'Confirmed by the importer',
        'importer_confirmed_at' => 'Importer confirmed at',
        'receiver_checker_id' => 'Confirmed by the receiver',
        'receiver_confirmed_at' => 'Receiver confirmed at',
        'awaited_confirmation' => 'Waiting for',
        'original_invoice_id' => 'Original invoice',
        'profit_difference' => 'Profit difference',
        'images' => 'Images',
        'notes' => 'Notes',
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
