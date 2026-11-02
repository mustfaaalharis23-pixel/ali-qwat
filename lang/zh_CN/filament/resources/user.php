<?php

return [
    'label' => '用户',
    'plural_label' => '用户',
    'navigation_label' => '用户',

    'fields' => [
        'name' => '姓名',
        'email' => '邮箱',
        'password' => '密码',
        'is_active' => '启用',
        'is_super' => '超级账号',
        'is_cashier' => '收银员',
    ],

    'hints' => [
        'is_super' => '拥有全部权限',
    ],

    'sections' => [
        'account' => '账号',
        'role' => '状态与角色',
    ],
];
