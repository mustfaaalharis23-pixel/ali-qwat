<?php

return [
    'label' => 'وصف عام',
    'plural_label' => 'الوصوف العامة',
    'navigation_label' => 'الوصوف العامة',

    'fields' => [
        'name' => 'الاسم',
        'type' => 'النوع',
        'is_purchases_description' => 'وصف مشتريات',
        'is_capital_movement' => 'حركة رأس مال',
    ],

    'sections' => [
        'details' => 'تفاصيل الوصف',
    ],

    'hints' => [
        'is_capital_movement' => 'أموال الشركاء الداخلة أو الخارجة، مثل الاستثمار وإرجاع رأس المال. لا ربح ولا مصروف، فتقرير الأرباح والخسائر يخليها خارج الجانبين.',
    ],

    'types' => [
        'input' => 'داخل',
        'output' => 'خارج',
    ],
];
