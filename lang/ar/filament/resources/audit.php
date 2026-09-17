<?php

return [
    'label' => 'سجل تدقيق',
    'plural_label' => 'سجلات التدقيق',
    'navigation_label' => 'سجلات التدقيق',

    'system' => 'النظام',

    'fields' => [
        'event' => 'الحدث',
        'subject_type' => 'نوع السجل',
        'subject_id' => 'رقم السجل',
        'causer' => 'المستخدم',
        'created_at' => 'التاريخ',
        'created_from' => 'من',
        'created_until' => 'إلى',
        'attribute' => 'الحقل',
        'old' => 'قبل',
        'new' => 'بعد',
    ],

    'sections' => [
        'details' => 'التفاصيل',
        'changes' => 'التغييرات',
    ],

    'events' => [
        'created' => 'إنشاء',
        'updated' => 'تعديل',
        'deleted' => 'حذف',
        'restored' => 'استرجاع',
        'forceDeleted' => 'حذف نهائي',
    ],

    'attributes' => [
        'id' => 'الرقم',
        'is_active' => 'فعال',
        'email_verified_at' => 'تاريخ تأكيد البريد',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التعديل',
        'deleted_at' => 'تاريخ الحذف',
    ],
];
