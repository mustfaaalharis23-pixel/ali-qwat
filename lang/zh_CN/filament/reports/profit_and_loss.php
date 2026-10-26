<?php

return [
    'title' => '损益报表',

    'picker' => [
        'label' => '月份',
        'last_month' => '上个月',
        'this_month' => '本月',
        'earlier_year' => '上一年',
        'later_year' => '下一年',
        'done' => '完成',
    ],

    'exclusions' => [
        'clear' => '全部恢复',
        'excluded' => '已排除',
        'after' => '排除后',
        'not_checked' => '未核对是否相符',
        'include' => '将 :name 计入合计',
    ],

    'sections' => [
        'revenue' => '收入',
        'expenses' => '费用',
        'summary' => '汇总',
    ],

    'columns' => [
        'section' => '部分',
        'detail' => '项目',
        'note' => '详情',
        'amount' => '金额',
    ],

    'expenses' => [
        'label' => '费用',
        'grouped' => '按科目',
        'detailed' => '逐项列出',
    ],

    'rows' => [
        'invoice' => '单据',
        'sales_profit' => '销售利润',
        'total_revenue' => '收入合计',
        'total_expenses' => '费用合计',
        'net_profit' => '净利润',
        'capital_movement' => '资本变动（进账 − 出账）',
        'value_at_start' => '期初公司价值',
        'computed_value' => '计算出的公司价值（期初 + 净利润 + 资本）',
        'value_at_end' => '期末实际公司价值',
        'matching' => '核对',
        'matched' => '✓ 相符',
        'not_matched' => '✗ 不相符',
        'difference' => '差额（实际 − 计算）',
    ],

    'value' => [
        'moment' => ':moment 结束时的公司价值',
        'stock' => '库存（按成本）',
        'unconfirmed_purchases' => '待确认的采购',
        'supplier_balance' => '欠供应商款',
        'accountant_box' => '会计钱箱',
        'cash_sales_box' => '现金销售钱箱',
        'postpaid_sales_box' => '赊账销售钱箱',
        'total' => '公司价值',
    ],

    'totals' => [
        'sales' => '销售合计',
        'purchases' => '采购合计',
        'sales_profit' => '销售利润',
        'sales_margin' => '销售利润率（%）',
    ],
];
