<?php

return [
    'payment_types' => [
        'cash' => 'كاش',
        'postpaid' => 'آجل',
    ],

    'validation' => [
        'quantity_more_than_available' => 'الكمية المتوفرة من :item لا تكفي: الباقي :available فقط.',
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
    ],
];
