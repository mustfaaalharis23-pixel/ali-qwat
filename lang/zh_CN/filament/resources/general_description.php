<?php

return [
    'label' => '总科目',
    'plural_label' => '总科目',
    'navigation_label' => '总科目',

    'fields' => [
        'name' => '名称',
        'type' => '类型',
        'is_purchases_description' => '采购科目',
        'is_capital_movement' => '资本变动',
    ],

    'sections' => [
        'details' => '科目详情',
    ],

    'hints' => [
        'is_capital_movement' => '合伙人投入或取出的资金，例如投资或退还资本。它既不是利润也不是费用，因此损益报表不把它计入任何一方。',
    ],

    'types' => [
        'input' => '进账',
        'output' => '出账',
    ],
];
