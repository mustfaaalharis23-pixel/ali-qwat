<?php

return [
    'label' => '单据',
    'plural_label' => '单据',

    'fields' => [
        'name' => '名称',
        'type' => '类型',
        'general_description_id' => '总科目',
        'supplier_id' => '供应商',
        'invoice_category_id' => '类别',
        'container_id' => '集装箱',
        'user_id' => '创建人',
        'cashier_id' => '收银员',
        'date' => '日期',
        'amount' => '金额',
        'amount_iqd' => '第纳尔金额',
        'total_iqd' => '含运费合计',
        'dinar_rate' => '美元兑第纳尔汇率',
        'transport_cost_iqd' => '第纳尔运费',
        'amount_usd' => '美元金额',
        'exchange_rate' => '汇率',
        'exchange_rate_source' => '汇率来源',
        'paid_amount' => '已付金额',
        'remaining' => '剩余',
        'cbm' => '单据 CBM',
        'cbm_price' => '每立方米价格（CBM）',
        'transport_cost' => '运费',
        'status' => '状态',
        'stage' => '阶段',
        'importer_checker_id' => '进口方确认人',
        'importer_confirmed_at' => '进口方确认时间',
        'receiver_checker_id' => '收货方确认人',
        'receiver_confirmed_at' => '收货方确认时间',
        'awaited_confirmation' => '等待',
        'original_invoice_id' => '原单',
        'split_from_invoice_id' => '拆分自',
        'profit_difference' => '利润差额',
        'images' => '图片',
        'notes' => '备注',
    ],

    'exchange_rate_sources' => [
        'agricultural_bank_of_china' => '中国农业银行',
        'china_merchants_bank' => '招商银行',
        'manual' => '手动输入',
    ],

    'stages' => [
        'in_production' => '生产中',
        'manufactured' => '已生产完成',
        'received_at_warehouse' => '已到仓库',
        'on_the_way' => '在途',
        'received' => '已在伊拉克收货',
    ],

    'statuses' => [
        'pending' => '待确认',
        'importer_confirmed' => '进口方已确认',
        'confirmed' => '已确认',
    ],

    'confirmations' => [
        'importer' => '进口方',
        'receiver' => '收货方',
    ],

];
