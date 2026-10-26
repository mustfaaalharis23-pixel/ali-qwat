<?php

return [
    'label' => '集装箱',
    'plural_label' => '集装箱',
    'navigation_label' => '集装箱',

    'fields' => [
        'id' => '系统编号',
        'international_number' => '国际箱号',
        'cbm_price' => '每立方米价格（CBM）',
        'loading_date' => '装柜日期',
        'expected_arrival_date' => '预计到达日期',
        'notes' => '备注',
        'total_cbm' => '总 CBM',
        'transport_cost' => '运费',
    ],

    'hints' => [
        'international_number' => '四个字母加七位数字，与集装箱上写的一致。',
        'cbm_price' => '这是集装箱的价格，仅作备注，不参与任何计算。集装箱中的每张单据按其自己的每 CBM 价格计算成本。',
        'expected_arrival_date' => '自动填为装柜日期后 :days 天，可以修改。',
    ],

    'how_it_works' => [
        'heading' => '集装箱如何计算',
        'description' => '集装箱的 CBM = 其所有单据的 CBM 之和。其运费 = 其所有单据的运费之和，每张单据按其 CBM × 其自己的每 CBM 价格计算，而不是集装箱的价格。单据第一次进入集装箱后，其每 CBM 价格即永久锁定。',
    ],

    'invoices_description' => '每张单据的运费 = 其 CBM × 其自己的每 CBM 价格（该列）。集装箱的每 CBM 价格仅作备注。',

    'actions' => [
        'add_invoices' => '添加单据',
        'take_invoice_out' => '移出集装箱',
        'take_invoices_out' => '移出集装箱',
        'add_part_of_invoice' => [
            'label' => '添加单据的一部分',
            'description' => '选择一张采购单，并填写每条明细行有多少箱装入此集装箱。这些会成为集装箱中的一张新单据，原单保留其余部分。',
            'invoice' => '采购单',
            'cartons' => '装入此集装箱的箱数',
            'cartons_hint' => '共 :count 箱',
            'done' => '该部分已作为单据 :name 加入集装箱。',
            'line' => [
                'cartons' => ':value 箱',
                'packets_per_carton' => '每箱 :value 包',
                'pieces_per_packet' => '每包 :value 副',
                'pieces' => '明细行共 :value 副',
            ],
            'preview' => [
                'nothing' => '不装入任何货物，整条明细行留在原单上',
                'goes' => '装入集装箱：:cartons 箱 · :packets 包 · :pieces 副',
                'stays' => '留在原单上：:cartons 箱 · :packets 包 · :pieces 副',
                'all' => '整条明细行装入集装箱：:cartons 箱 · :packets 包 · :pieces 副；原单上不留任何货物',
                'too_many' => '超过该明细行的数量；它只有 :count 箱',
            ],
        ],
    ],

    'widgets' => [
        'totals' => [
            'total_cbm' => '总 CBM',
            'transport_cost' => '运费',
        ],
        'totals_info' => [
            'total_cbm' => [
                'explanation' => '货物在此集装箱中占用的空间：其中所有采购单的 CBM 之和。一张单据的 CBM 是其各明细行之和，一条明细行的 CBM 是其箱数 × 一箱的 CBM。已删除的单据不计入。',
                'example' => '集装箱中有两张单据：一张 0.5 CBM，一张 1.5 CBM。总 CBM = 0.5 + 1.5 = 2。',
            ],
            'transport_cost' => [
                'explanation' => '运输此集装箱的费用，以美元计：其所有单据运费之和。每张单据按其自己的每 CBM 价格计算成本：其 CBM × 其价格。集装箱的每 CBM 价格仅作备注，不参与计算。每副分摊的费用 = 其明细行分摊的费用 ÷ 该明细行的副数。',
                'example' => '集装箱中有两张单据：一张 0.5 CBM，单价 $120；一张 1.5 CBM，单价 $100。运费 = 0.5 × 120 + 1.5 × 100 = 60 + 150 = $210。如果第一张单据有 240 副，每副运费为 60 ÷ 240 = $0.25。',
            ],
        ],
    ],
];
