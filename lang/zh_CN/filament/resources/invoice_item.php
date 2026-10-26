<?php

return [
    'label' => '单据明细行',
    'plural_label' => '单据明细',

    'fields' => [
        'invoice_id' => '单据',
        'specific_description_id' => '明细科目',
        'item_id' => '商品',
        'lens_power_id' => '度数',
        'groups' => '包组',
        'colors' => '每包颜色',
        'color_id' => '颜色',
        'pieces' => '包内副数',
        'price' => '价格',
        'quantity' => '数量（副）',
        'sets' => '套数',
        'boxes_count' => '包数',
        'pieces_per_packet' => '每包副数',
        'boxes_per_carton' => '每箱包数',
        'cartons_count' => '箱数',
        'carton_cbm' => '每箱体积（CBM）',
        'cbm' => '明细行 CBM',
        'transport_cost_per_piece' => '每副运费',
        'produced_at' => '生产日期',
        'profit_difference' => '利润差额',
    ],

    'group' => [
        'power' => '度数 :value',
        'packets' => ':packets 包',
        'color' => ':color :pieces 副',
        'separator' => '、',
    ],
];
