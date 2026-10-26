<?php

return [
    'label' => '通用单据',
    'plural_label' => '通用单据',
    'navigation_label' => '通用单据',

    'widgets' => [
        'expenses_chart' => [
            'heading' => '按科目统计的支出',
            'others' => '其他',
        ],
    ],

    'sections' => [
        'details' => '单据详情',
        'amounts' => '金额',
        'attachments' => '图片和备注',
    ],

    'fields' => [
        'items' => '明细',
    ],

    'money_transfer_notice' => [
        'heading' => '不能手动取款',
        'cannot_save' => '不能在此总科目下保存单据，因为它专用于取款。',
        'description' => '不能在通用单据中手动取款，也不能手动修改取款。提取现金的唯一方式是财务仪表盘中“收银员资金转账”卡片上的“转账”按钮；提取赊账款项的唯一方式是偿还客户的欠款。',
    ],
];
