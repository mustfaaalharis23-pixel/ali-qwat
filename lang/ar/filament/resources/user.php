<?php

return [
    'label' => 'مستخدم',
    'plural_label' => 'المستخدمون',
    'navigation_label' => 'المستخدمون',

    'fields' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'is_active' => 'فعّال',
        'is_super' => 'حساب سوبر',
        'is_cashier' => 'كاشير',
    ],

    'hints' => [
        'is_super' => 'ياخذ جميع الصلاحيات',
    ],

    'sections' => [
        'account' => 'الحساب',
        'role' => 'الحالة والدور',
    ],
];
