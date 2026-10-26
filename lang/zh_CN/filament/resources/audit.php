<?php

return [
    'label' => '审计日志',
    'plural_label' => '审计日志',
    'navigation_label' => '审计日志',

    'system' => '系统',

    'fields' => [
        'event' => '事件',
        'subject_type' => '记录类型',
        'subject_id' => '记录编号',
        'causer' => '用户',
        'created_at' => '日期',
        'created_from' => '从',
        'created_until' => '至',
        'attribute' => '字段',
        'old' => '修改前',
        'new' => '修改后',
    ],

    'sections' => [
        'details' => '详情',
        'changes' => '变更',
    ],

    'events' => [
        'created' => '创建',
        'updated' => '修改',
        'deleted' => '删除',
        'restored' => '恢复',
        'forceDeleted' => '永久删除',
    ],

    'attributes' => [
        'id' => '编号',
        'is_active' => '启用',
        'email_verified_at' => '邮箱验证时间',
        'created_at' => '创建时间',
        'updated_at' => '修改时间',
        'deleted_at' => '删除时间',
    ],
];
