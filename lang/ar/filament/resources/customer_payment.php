<?php

return [
    'label' => 'تسديد زبون',
    'plural_label' => 'تسديد الزبائن',
    'navigation_label' => 'تسديد الزبائن',

    'fields' => [
        'customer_id' => 'الزبون',
        'amount' => 'المبلغ',
        'is_down_payment' => 'دفعة أولية',
        'date' => 'التاريخ',
        'image' => 'صورة الوصل',
        'notes' => 'الملاحظات',
        'user_id' => 'سجّله',
        'invoice_id' => 'فاتورة السحب',
        'allocations' => 'الأوردرات المسددة',
        'order' => 'الأوردر',
        'order_date' => 'تاريخ الأوردر',
        'order_total' => 'مبلغ الأوردر',
        'allocation_amount' => 'المسدد منه',
    ],

    'sections' => [
        'details' => 'تفاصيل التسديد',
        'receipt' => 'الوصل',
    ],

    'filters' => [
        'from' => 'من',
        'until' => 'إلى',
    ],
];
