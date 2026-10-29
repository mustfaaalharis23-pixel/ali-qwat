<?php

return [
    'label' => '客户',
    'plural_label' => '客户',
    'navigation_label' => '客户',

    'fields' => [
        'name' => '名称',
        'phone' => '电话',
        'email' => '邮箱',
        'contract' => '合同',
        'payment_type' => '付款方式',
        'is_invoice_by_invoice' => '按单结清',
        'is_invoice_by_invoice_hint' => '开启后，客户欠款清零前不能下新订单。',
    ],

    'sections' => [
        'details' => '客户信息',
    ],

    'actions' => [
        'pay_debt' => [
            'label' => '还款',
            'heading_order' => '支付订单 #:order · :customer',
            'pay_order_all' => '全部',
            'order_late' => '逾期：:amount',
            'order_weekly' => '每周：:amount',
            'order_next' => '下次：:date 付 :amount',
            'order_next_today' => '下次：今天付 :amount',
            'heading' => '偿还 :customer 的欠款',
            'debt' => '剩余欠款：:debt',
            'customer' => '客户',
            'amount' => '还款金额',
            'pay_all' => '全部还清',
            'date' => '还款日期',
            'image' => '收据或支票图片',
            'notes' => '备注',
            'submit' => '记录还款',
            'done' => '还款已记录。',
            'preview' => [
                'choose_customer' => '选择客户以查看其欠款。',
                'current_debt' => '当前欠款',
                'paying' => '本次还款',
                'after' => '本次还款后剩余',
                'spread' => '该客户有欠款的订单，按从早到晚排列。本次金额按此顺序偿还：',
                'order' => '订单 #:id · :date',
                'order_remaining' => '该单剩余：:amount',
                'full' => '全部还清',
                'partial' => '部分还款',
                'untouched' => '本次不还',
            ],
        ],
        'statement' => '对账单',
    ],

    'statement' => [
        'layouts' => [
            'orders' => '按订单',
            'dates' => '按日期',
        ],
        'tree' => [
            'order' => '订单 #:id · :date · :items 件商品',
            'total' => '金额',
            'paid' => '已付',
            'remaining' => '剩余',
            'settled' => '已全部付清',
            'open' => '剩余 :amount',
            'open_order' => '打开订单',
            'no_payments' => '尚未付款',
            'left_after' => '付款后剩余 :amount',
            'by' => '记录人 :user',
            'receipt' => '收据',
            'empty' => '此期间没有赊账订单。',
        ],
        'fields' => [
            'customer' => '客户',
            'customer_placeholder' => '选择客户',
            'from' => '开始日期',
            'until' => '结束日期',
        ],
        'summary' => [
            'orders' => '赊账订单',
            'payments' => '还款',
            'balance' => '欠款余额',
        ],
        'columns' => [
            'date' => '日期',
            'movement' => '流水',
            'owed' => '应付 (+)',
            'paid' => '已付 (−)',
            'balance' => '欠款余额',
        ],
        'types' => [
            'opening_balance' => '期初余额',
            'order' => '赊账订单',
            'payment' => '还款',
            'down_payment' => '首付款',
        ],
        'details' => [
            'opening_balance' => ':date 之前的全部流水',
            'order' => '订单 #:id · :items 件商品',
            'payment_by' => '记录人：:user',
        ],
        'actions' => [
            'open_order' => '订单',
            'open_receipt' => '收据',
        ],
        'empty' => '此期间没有赊账订单或还款。',
        'choose_customer' => '请在上方选择客户以查看其对账单。',
    ],

    'widgets' => [
        'days' => '天|天',
        'since_date' => ':unit · :date',
        'totals' => [
            'debt' => '欠款',
            'total_paid' => '累计还款',
            'last_payment' => '距上次还款',
            'total_sales' => '销售总额',
            'orders_count' => '订单数',
            'average_order' => '平均订单金额',
            'items_count' => '商品数',
            'last_order' => '距上次下单',
        ],
        'totals_info' => [
            'debt' => [
                'explanation' => '该客户目前仍欠的金额：该客户的每张赊账订单，用订单总额减去已还金额，再全部相加。现金订单在销售时已付款，因此没有欠款。已删除的订单不计入。',
                'example' => '该客户有两张赊账订单：500,000 IQD 已还 300,000 IQD，200,000 IQD 尚未还款。欠款 = 200,000 + 200,000 = 400,000 IQD。',
            ],
            'total_paid' => [
                'explanation' => '该客户到目前为止已还的全部金额：该客户所有还款金额之和。已删除的还款不计入。',
                'example' => '该客户先还了 300,000 IQD，后又还了 150,000 IQD。累计还款 = 450,000 IQD。',
            ],
            'last_payment' => [
                'explanation' => '距该客户上次还款已过去多少天，旁边显示那次还款的日期。可用来发现已停止还款的客户。已删除的还款不计入，没有任何还款的客户显示短横线。',
                'example' => '今天是 2026/05/20，该客户上次还款是在 2026/05/15。卡片显示 5 天 · 2026/05/15。',
            ],
            'total_sales' => [
                'explanation' => '卖给该客户的全部商品的总价值：该客户所有订单（现金或赊账）的总额之和。已删除的订单不计入。',
                'example' => '该客户有两张订单：250,000 IQD 和 150,000 IQD。销售总额 = 400,000 IQD。',
            ],
            'orders_count' => [
                'explanation' => '该客户下过的订单数量，现金或赊账均计入。已删除的订单不计入。',
                'example' => '该客户下了 5 张订单，其中一张已删除。卡片显示 4。',
            ],
            'average_order' => [
                'explanation' => '该客户平均每张订单花费多少：销售总额除以订单数。已删除的订单不计入。',
                'example' => '该客户有三张订单：100,000、200,000 和 300,000 IQD。平均订单金额 = 600,000 ÷ 3 = 200,000 IQD。',
            ],
            'items_count' => [
                'explanation' => '该客户买过多少种不同的商品。在多张订单中买过的同一商品只计一次。已删除订单中的商品不计入。',
                'example' => '该客户在两张订单中买过商品 A，在一张订单中买过商品 B。卡片显示 2。',
            ],
            'last_order' => [
                'explanation' => '距该客户上次下单已过去多少天，旁边显示那张订单的日期。可用来发现已停止下单的客户。已删除的订单不计入，没有任何订单的客户显示短横线。',
                'example' => '今天是 2026/05/20，该客户上次下单是在 2026/05/08。卡片显示 12 天 · 2026/05/08。',
            ],
        ],
    ],

    'payment_types' => [
        'cash' => '现金',
        'postpaid' => '赊账',
    ],
];
