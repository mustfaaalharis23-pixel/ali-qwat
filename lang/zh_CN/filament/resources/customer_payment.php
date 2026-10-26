<?php

return [
    'label' => '客户还款',
    'plural_label' => '客户还款',
    'navigation_label' => '客户还款',

    'fields' => [
        'customer_id' => '客户',
        'amount' => '金额',
        'is_down_payment' => '首付款',
        'date' => '日期',
        'image' => '收据图片',
        'notes' => '备注',
        'user_id' => '记录人',
        'invoice_id' => '取款单据',
        'allocations' => '已还款订单',
        'order' => '订单',
        'order_date' => '订单日期',
        'order_total' => '订单合计',
        'allocation_amount' => '该订单还款额',
    ],

    'sections' => [
        'details' => '还款详情',
        'receipt' => '收据',
    ],

    'actions' => [
        'print_receipt' => '打印收款单',
    ],

    'filters' => [
        'from' => '从',
        'until' => '至',
    ],
];
