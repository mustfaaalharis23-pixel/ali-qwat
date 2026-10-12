<?php

return [
    'title' => 'تقرير الأرباح والخسائر',

    'picker' => [
        'label' => 'الشهر',
        'last_month' => 'الشهر السابق',
        'this_month' => 'الشهر الحالي',
        'earlier_year' => 'السنة السابقة',
        'later_year' => 'السنة اللاحقة',
        'done' => 'تم',
    ],

    'exclusions' => [
        'clear' => 'إلغاء كل الاستثناءات',
        'excluded' => 'مستثنى',
        'after' => 'بعد الاستثناء',
        'not_checked' => 'المطابقة غير محسوبة',
        'include' => 'احسب :name بالمجاميع',
    ],

    'sections' => [
        'revenue' => 'الإيرادات',
        'expenses' => 'المصاريف',
        'summary' => 'الملخّص',
    ],

    'columns' => [
        'section' => 'القسم',
        'detail' => 'البيان',
        'note' => 'التفاصيل',
        'amount' => 'المبلغ',
    ],

    'expenses' => [
        'label' => 'المصاريف',
        'grouped' => 'حسب الوصف',
        'detailed' => 'بند بند',
    ],

    'rows' => [
        'invoice' => 'فاتورة',
        'sales_profit' => 'أرباح المبيعات',
        'total_revenue' => 'إجمالي الإيرادات',
        'total_expenses' => 'إجمالي المصاريف',
        'net_profit' => 'صافي الربح',
        'capital_movement' => 'حركة رأس المال (داخل − خارج)',
        'value_at_start' => 'قيمة الشركة بداية الفترة',
        'computed_value' => 'قيمة الشركة المحسوبة (البداية + صافي الربح + رأس المال)',
        'value_at_end' => 'قيمة الشركة الفعلية نهاية الفترة',
        'matching' => 'حالة المطابقة',
        'matched' => '✓ مطابق',
        'not_matched' => '✗ غير مطابق',
        'difference' => 'الفرق (الفعلي − المحسوب)',
    ],

    'value' => [
        'moment' => 'قيمة الشركة بنهاية يوم :moment',
        'stock' => 'المخزون بالكلفة',
        'unconfirmed_purchases' => 'مشتريات قيد التأكيد',
        'supplier_balance' => 'المستحق للموردين',
        'accountant_box' => 'صندوق المحاسب',
        'cash_sales_box' => 'صندوق مبيعات الكاش',
        'postpaid_sales_box' => 'صندوق مبيعات الآجل',
        'total' => 'قيمة الشركة',
    ],

    'totals' => [
        'sales' => 'المبيعات الكلية',
        'purchases' => 'المشتريات الكلية',
        'sales_profit' => 'ربح المبيعات',
        'sales_margin' => 'هامش الربح من المبيعات (٪)',
    ],
];
