<?php

return [
    'title' => '客户还款',

    'tabs' => [
        'payments' => '还款记录',
        'statement' => '对账单',
        'due_today' => '今日应付',
        'late' => '逾期付款',
    ],

    'reminders' => [
        'customer' => '客户',
        'phone' => '电话',
        'due_amount' => '今日应付',
        'late_amount' => '逾期金额',
        'due_since' => '应付日期',
        'days_late' => '逾期天数',
        'days' => ':days 天',
        'empty_due' => '今天没有应付款项',
        'empty_late' => '没有逾期付款',
    ],

    'order_reminders' => [
        'due_today_value' => '今日应付 :amount',
        'late_value' => '逾期 :amount',
        'remaining_value' => '剩余 :amount',
        'tab_today' => '今日到期',
        'tab_late' => '逾期',
        'tab_all' => '全部',
        'due_today' => '今日应付',
        'empty_today' => '今天没有到期的付款',
        'empty_late' => '没有逾期付款',
        'heading' => '每周付款提醒',
        'description' => '每个有每周付款的赊账订单，直到全部付清。逾期的客户排在前面。',
        'remaining' => '剩余',
        'orders' => '订单',
        'order' => '订单 #:number',
        'weekly' => '每周 :amount',
        'not_late' => '未逾期',
        'late_for' => ':days 天',
        'late' => '逾期 :amount · :days 天',
        'next' => '下次 :amount · :date',
        'next_today' => '下次 :amount · 今天',
        'no_next' => '所有付款均已到期',
        'left' => '剩余 :amount · 剩余期数 :weeks',
        'empty' => '没有剩余每周付款的订单',
    ],
];
