<?php

return [
    'label' => 'بند فاتورة',
    'plural_label' => 'بنود الفاتورة',

    'fields' => [
        'invoice_id' => 'الفاتورة',
        'specific_description_id' => 'الوصف الخاص',
        'item_id' => 'المنتج',
        'lens_power_id' => 'الدرجة',
        'groups' => 'مجموعات الباكيتات',
        'colors' => 'ألوان الباكيت',
        'color_id' => 'اللون',
        'pieces' => 'القطع بالباكيت',
        'price' => 'السعر',
        'quantity' => 'الكمية (قطعة)',
        'sets' => 'عدد الدرازن',
        'boxes_count' => 'عدد الباكيتات',
        'pieces_per_packet' => 'تعبئة الباكيت',
        'boxes_per_carton' => 'تعبئة الكارتونة',
        'cartons_count' => 'عدد الكراتين',
        'carton_cbm' => 'حجم الكارتونة (CBM)',
        'cbm' => 'CBM البند',
        'transport_cost_per_piece' => 'نقل القطعة',
        'produced_at' => 'تاريخ الإنتاج',
        'profit_difference' => 'فرق الربحية',
    ],

    'group' => [
        'power' => 'درجة :value',
        'packets' => ':packets باكيت',
        'color' => ':color :pieces قطعة',
        'separator' => '، ',
    ],
];
