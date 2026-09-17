<?php

return [
    'label' => 'فاتورة',
    'plural_label' => 'الفواتير',

    'fields' => [
        'name' => 'الاسم',
        'type' => 'النوع',
        'general_description_id' => 'الوصف العام',
        'supplier_id' => 'المورد',
        'user_id' => 'المنشئ',
        'cashier_id' => 'الكاشير',
        'date' => 'التاريخ',
        'amount' => 'المبلغ',
        'paid_amount' => 'المبلغ المدفوع',
        'remaining' => 'الباقي',
        'status' => 'الحالة',
        'importer_checker_id' => 'أكدها المستورد',
        'importer_confirmed_at' => 'تاريخ تأكيد المستورد',
        'receiver_checker_id' => 'أكدها المستلم',
        'receiver_confirmed_at' => 'تاريخ تأكيد المستلم',
        'awaited_confirmation' => 'بانتظار',
        'original_invoice_id' => 'الفاتورة الأصلية',
        'profit_difference' => 'فرق الربحية',
        'images' => 'الصور',
        'notes' => 'الملاحظات',
    ],

    'statuses' => [
        'pending' => 'معلّقة',
        'importer_confirmed' => 'مؤكدة من المستورد',
        'confirmed' => 'مؤكدة',
    ],

    'confirmations' => [
        'importer' => 'المستورد',
        'receiver' => 'المستلم',
    ],

];
