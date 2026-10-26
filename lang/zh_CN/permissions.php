<?php

return [
    'section' => '权限',

    'abilities' => [
        'view' => '查看',
        'create' => '新建',
        'update' => '编辑',
        'delete' => '删除',
        'restore' => '恢复',
        'transfer_cashier_money' => '收银员资金转账',
    ],

    'app' => [
        'group' => '销售应用',
        'orders' => '订单页面',
        'checkout' => '销售（结账）',
        'receiving' => '收货（扫描条码）',
    ],

    'copy' => [
        'label' => '从用户复制',
        'user' => '用户',
        'copied' => '权限已复制。请点击“保存”以应用。',
    ],
];
