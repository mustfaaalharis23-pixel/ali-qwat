<?php

return [
    'label' => 'فاتورة',
    'plural_label' => 'الفواتير',

    'fields' => [
        'name' => 'الاسم',
        'type' => 'النوع',
        'general_description_id' => 'الوصف العام',
        'supplier_id' => 'المورد',
        'invoice_category_id' => 'الفئة',
        'container_id' => 'الحاوية',
        'user_id' => 'المنشئ',
        'cashier_id' => 'الكاشير',
        'date' => 'التاريخ',
        'amount' => 'المبلغ',
        'amount_iqd' => 'المبلغ بالدينار',
        'total_iqd' => 'المجموع ويّا النقل',
        'dinar_rate' => 'سعر الدولار بالدينار',
        'transport_cost_iqd' => 'النقل بالدينار',
        'amount_usd' => 'المبلغ بالدولار',
        'exchange_rate' => 'سعر الصرف',
        'exchange_rate_source' => 'مصدر سعر الصرف',
        'paid_amount' => 'المبلغ المدفوع',
        'remaining' => 'الباقي',
        'cbm' => 'CBM الفاتورة',
        'cbm_price' => 'سعر المتر المكعب (CBM)',
        'transport_cost' => 'كلفة النقل',
        'status' => 'الحالة',
        'stage' => 'مرحلة الفاتورة',
        'importer_checker_id' => 'أكدها المستورد',
        'importer_confirmed_at' => 'تاريخ تأكيد المستورد',
        'receiver_checker_id' => 'أكدها المستلم',
        'receiver_confirmed_at' => 'تاريخ تأكيد المستلم',
        'awaited_confirmation' => 'بانتظار',
        'original_invoice_id' => 'الفاتورة الأصلية',
        'split_from_invoice_id' => 'مقسومة من',
        'profit_difference' => 'فرق الربحية',
        'images' => 'الصور',
        'notes' => 'الملاحظات',
    ],

    'exchange_rate_sources' => [
        'agricultural_bank_of_china' => 'البنك الزراعي الصيني',
        'china_merchants_bank' => 'بنك China Merchants',
        'manual' => 'إدخال يدوي',
    ],

    'stages' => [
        'in_production' => 'قيد التصنيع',
        'manufactured' => 'تم التصنيع',
        'received_at_warehouse' => 'تم الاستلام في المخزن',
        'on_the_way' => 'في الطريق',
        'received' => 'تم الاستلام في العراق',
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
