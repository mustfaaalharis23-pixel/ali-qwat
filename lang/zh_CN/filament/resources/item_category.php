<?php

return [
    'label' => '商品类别',
    'plural_label' => '商品类别',
    'navigation_label' => '商品类别',

    'fields' => [
        'name' => '名称',
        'items_count' => '商品数量',
    ],

    'validation' => [
        'has_items' => '此类别下有商品，因此不能删除。',
    ],
];
