<?php

return [
    'payment_types' => [
        'cash' => '现金',
        'postpaid' => '赊账',
    ],

    'packages' => [
        'packet' => '包',
    ],

    'package_counts' => [
        'packet' => ':count 包|:count 包',
    ],

    'dozens' => ':count 打',

    'validation' => [
        'quantity_not_whole_packets' => '商品只能按整包销售：一包为 :size 副。',
        'quantity_must_be_whole_pieces' => '数量必须是整副：没有半副。',
        'item_has_no_packet' => ':item 没有设置每包副数，因此无法销售。',
        'quantity_more_than_available' => ':item 数量不足：仅剩 :available。',
        'quantity_more_than_left_of_power' => ':item 在度数 :power 下数量不足：仅剩 :available。',
        'choose_a_power' => ':item 按度数销售，请选择度数。',
        'quantity_more_than_left_of_packet_type' => ':item 在此度数和这些颜色下数量不足：仅剩 :available 包。',
        'choose_the_colors' => ':item 按颜色组合销售，请选择各包的颜色。',
        'quantity_must_be_positive' => '数量必须大于零。',
        'price_must_be_positive' => '价格必须大于零。',
        'discount_must_not_be_negative' => '折扣不能小于零。',
        'discount_more_than_line' => ':item 的折扣必须小于该行金额。',
        'discount_below_cost' => ':item 的折扣使该行低于成本（:cost）。',
        'last_item_cannot_be_deleted' => '订单中的最后一个商品不能删除。',
        'customer_required' => '订单必须有一个已存在的客户。',
        'payment_type_required' => '订单必须有付款方式。',
        'item_without_sale_price' => '商品 :item 没有售价。',
        'item_sale_price_below_cost_price' => '商品 :item 的售价不能低于其成本价。',
        'order_has_payments' => '已有还款的订单不能删除。请先删除其还款。',
        'payment_amount_must_be_positive' => '金额必须大于零。',
        'payment_exceeds_customer_debt' => '金额不能超过客户的剩余欠款（:debt）。',
        'payment_description_missing' => '没有用于赊账转账的科目。',
        'payment_needs_postpaid_order' => '还款只能添加到赊账订单。',
        'payment_on_deleted_order' => '已删除的订单不能添加还款。',
        'payment_order_cannot_change' => '还款不能转移到其他订单。',
        'payments_exceed_order_amount' => '还款总额不能超过订单金额。',
        'down_payment_postpaid_only' => '首付款只能在赊账销售时收取。',
        'down_payment_must_be_positive' => '首付款必须大于零。',
        'down_payment_exceeds_order_amount' => '首付款不能超过订单金额。',
        'weekly_payment_postpaid_only' => '每周付款只能在赊账销售时设置。',
        'weekly_payment_must_be_positive' => '每周付款必须大于零。',
        'weekly_payment_exceeds_remaining' => '每周付款不能超过扣除首付款后的剩余金额。',
        'invoice_by_invoice_customer_has_debt' => '该客户按单结清且仍有欠款；欠款清零前不能下新订单。',
        'order_needs_items' => '订单至少需要一个商品。',
        'paid_order_keeps_customer' => '该订单已有还款，不能更改客户。',
        'paid_order_keeps_payment_type' => '该订单已有还款，不能更改付款方式。',
        'order_below_paid_amount' => '订单金额不能低于已付金额（:paid）。',
    ],
];
