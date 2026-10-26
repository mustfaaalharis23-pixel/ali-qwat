<?php

return [
    'payment_types' => [
        'cash' => 'كاش',
        'postpaid' => 'آجل',
    ],

    'packages' => [
        'packet' => 'باكيت',
    ],

    'package_counts' => [
        'packet' => ':count باكيت',
    ],

    'dozens' => ':count درزن',

    'validation' => [
        'quantity_not_whole_packets' => 'البيع بالباكيت الكامل بس: كل باكيت :size قطعة.',
        'quantity_must_be_whole_pieces' => 'الكمية لازم تكون قطع كاملة، ما بيها نص قطعة.',
        'item_has_no_packet' => ':item ما إله تعبئة باكيت، فما ينباع.',
        'quantity_more_than_available' => 'الكمية المتوفرة من :item لا تكفي: الباقي :available فقط.',
        'quantity_more_than_left_of_power' => 'الباقي من :item بدرجة :power ما يكفي: بس :available.',
        'choose_a_power' => ':item ينباع بدرجة، فاختار الدرجة.',
        'quantity_more_than_left_of_packet_type' => 'الباقي من :item بهاي الدرجة والألوان ما يكفي: بس :available باكيت.',
        'choose_the_colors' => ':item ينباع بتشكيلات ألوان، فاختار ألوان الباكيت.',
        'quantity_must_be_positive' => 'الكمية لازم تكون أكبر من صفر.',
        'price_must_be_positive' => 'السعر لازم يكون أكبر من صفر.',
        'last_item_cannot_be_deleted' => 'آخر بند بالأوردر ما ينحذف.',
        'customer_required' => 'الأوردر لازم يكون لزبون موجود.',
        'payment_type_required' => 'الأوردر لازم تكون بيه طريقة دفع.',
        'item_without_sale_price' => 'المادة :item ما إلها سعر بيع.',
        'item_sale_price_below_cost_price' => 'المادة :item ما تنباع بأقل من سعر كلفتها.',
        'order_has_payments' => 'الأوردر اللي عليه تسديدات ما ينحذف. احذف التسديدات أولاً.',
        'payment_amount_must_be_positive' => 'المبلغ لازم يكون أكبر من صفر.',
        'payment_exceeds_customer_debt' => 'المبلغ ما يكدر يكون أكثر من الدين الباقي على الزبون (:debt).',
        'payment_description_missing' => 'ماكو وصف لتحويل أموال الآجل.',
        'payment_needs_postpaid_order' => 'التسديد ينضاف بس لأوردر آجل.',
        'payment_on_deleted_order' => 'الأوردر المحذوف ما ينضاف عليه تسديد.',
        'payment_order_cannot_change' => 'التسديد ما ينتقل لأوردر ثاني.',
        'payments_exceed_order_amount' => 'التسديدات ما تكدر تتجاوز مبلغ الأوردر.',
        'down_payment_postpaid_only' => 'الدفعة الأولية تنأخذ ويا البيع الآجل بس.',
        'down_payment_must_be_positive' => 'الدفعة الأولية لازم تكون أكبر من صفر.',
        'down_payment_exceeds_order_amount' => 'الدفعة الأولية ما تكدر تكون أكثر من مبلغ الأوردر.',
        'weekly_payment_postpaid_only' => 'الدفعة الأسبوعية تنحط ويا البيع الآجل بس.',
        'weekly_payment_must_be_positive' => 'الدفعة الأسبوعية لازم تكون أكبر من صفر.',
        'weekly_payment_exceeds_remaining' => 'الدفعة الأسبوعية ما تكدر تكون أكثر من الباقي بعد الدفعة الأولية.',
        'invoice_by_invoice_customer_has_debt' => 'هذا الزبون يشتغل فاتورة بفاتورة وبعده عليه دين؛ ما ياخذ أوردر جديد إلا لما يصير دينه صفر.',
    ],
];
