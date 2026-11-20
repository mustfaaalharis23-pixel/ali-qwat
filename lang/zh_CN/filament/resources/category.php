<?php

return [
    'label' => '类别',
    'plural_label' => '类别',
    'navigation_label' => '类别',
    'fields' => [
        'name' => '名称',
        'items_count' => '商品数量',
        'invoices_count' => '采购单数量',
    ],
    'validation' => [
        'has_items' => '此类别关联了商品，因此不能删除。',
    ],
];
