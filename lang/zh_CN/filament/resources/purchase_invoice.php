<?php

return [
    'label' => '采购单',
    'plural_label' => '采购单',
    'navigation_label' => '采购单',

    'sections' => [
        'details' => '采购单详情',
        'amounts' => '金额',
        'costs' => '成本',
        'progress' => '状态与确认',
        'attachments' => '图片与备注',
        'line_specs' => '规格',
        'line_packing' => '包装',
        'line_cost' => '成本',
    ],

    'fields' => [
        'items' => '明细',
        'initial_payment' => '首次付款',
        'item' => '商品',
        'unit_cost' => '每副成本',
        'final_unit_cost' => '每副最终成本',
        'dozen_cost' => '每打成本',
        'total_iqd' => '含运费的第纳尔合计',
        'final_dozen_cost' => '每打最终成本',
        'unit_cost_usd' => '每副美元成本',
        'price_usd' => '美元价格',
        'line_total' => '明细行合计',
    ],

    'split' => [
        'part' => '拆分自 #:number',
        'part_hint' => '采购单 #:number 的部分货物装入集装箱发运时，从中拆分出的拆分部分',
        'original' => '原单 · 已拆分为 :numbers',
        'original_hint' => '这是原单；其中部分已拆分出来并装入集装箱发运',
    ],

    'lines_below' => [
        'power' => '度数 :value',
        'packets' => ':packets 包 × :capacity',
        'per_carton' => '每箱 :value 包',
        'carton_cbm' => '每箱 :value',
        'usd' => ':value $',
        'final_parts' => ':cost + 运费 :transport',
        'per_dozen' => '每打 :value $',
    ],

    'exchange_rate_source_hint' => '来源：:source',
    'dinar_rate_unit' => '千 IQD',
    'dinar_rate_hint' => '按市场的写法：100 $ = 170 千（即 17 万）。所以 1 $ = :value IQD',
    'groups_hint' => '将该明细行的包分成若干组：每组由若干包组成，这些包为同一度数（或无度数）且颜色相同，各组包数之和必须等于包数。同一度数可以搭配其他颜色重复出现。若明细行既无度数也无颜色（如太阳镜），请留空。',
    'no_colors' => '无颜色',
    'colors_hint' => '一包中每种颜色各有多少副；合计必须等于每包副数（:pieces）。若包无颜色，请留空。',
    'cbm_price_hint' => '每个明细行的运费 = 其 CBM × 此价格；计算使用的是此价格，而不是集装箱的价格。采购单进入集装箱之前可以修改，之后将永久锁定。',
    'cbm_price_locked_hint' => '已锁定：采购单已进入集装箱。运费按此价格计算，而不是按集装箱的价格。',
    'groups_modal' => [
        'description' => '每包副数 :packet · :packets 包 · :pieces 副',
        'packets' => ':value 包',
        'pieces' => ':value 副',
        'dozens' => ':value 打',
        'per_packet' => '每包 :value',
    ],

    'costs_below' => [
        'rate' => '1 $ = :rate ¥',
        'shipping' => ':cbm CBM × :price $',
        'dinars' => ':amount + 运费 :transport',
    ],
    'lines_description' => '此处的成本均按打计算。每打最终成本 = 其第纳尔成本 + 其第纳尔运费；采购单经双方确认后，该成本即成为商品的成本。',

    'notifications' => [
        'exchange_rate_unavailable' => [
            'title' => '无法获取汇率',
            'body' => '中国农业银行和招商银行均未响应。请手动输入汇率：1 美元可兑换多少人民币。',
        ],
        'draft_restored' => [
            'title' => '已恢复您之前输入的内容',
            'body' => '这是您离开页面前在此浏览器中保存的最后一份内容。图片不会被保存，请重新上传。',
        ],
    ],

    'validation' => [
        'initial_payment_above_amount' => '首次付款金额超过了采购单金额。',
    ],

    'initial_payment_note' => [
        'heading' => '可选：采购时已付给供应商的金额',
        'description' => '将自动保存为该采购单的第一笔供应商付款，日期为采购单日期，并从欠供应商的款项中扣除。金额不能超过采购单金额；之后的付款可稍后在采购单页面添加。',
    ],

    'facts' => [
        'groups_packets' => '已分组 :value / :total 包',
        'packets_left_of' => '剩余 :value / :total 包',
        'packets_left' => '剩余 :value 包',
        'packets_over' => '多出 :value 包',
        'packets_full' => '所有包均已分组',
        'colors_left_of' => '剩余 :value / :total',
        'colors_over' => '多出 :value',
        'colors_full' => '该包已满',
        'dozen_cost' => '每打 :value',
        'available' => '现有 :value 打',
        'incoming' => '在途 :value 打',
        'sale_price' => '售价 :value',
        'line_total' => '合计 :value',
        'final_dozen_cost_iqd' => '每打最终 :value IQD',
        'cartons' => ':value 箱',
        'line_cbm' => ':value CBM',
        'transport_cost' => '运费 :value $',
        'transport_cost_per_piece' => '每副 :value $',
        'previous_unit_cost' => '之前 :value',
        'supplier_debt' => '当前欠供应商 :value',
        'invoice_amount' => '采购单金额 :value',
        'remaining_after_payment' => '付款后本单剩余 :value',
        'supplier_debt_after' => '之后欠供应商 :value',
    ],

    'facts_hints' => [
        'groups_packets' => '各组的包数之和，与该明细行的包数相比',
        'packets_left_of' => '本组之后该明细行剩余的包数，与该明细行的包数相比',
        'packets_left' => '该明细行尚未分组的包数',
        'packets_over' => '包数超过了该明细行的包数',
        'packets_full' => '各组的包数之和等于该明细行的包数',
        'colors_left_of' => '该包中尚未分配颜色的剩余副数',
        'colors_over' => '各颜色副数超过了每包副数',
        'colors_full' => '各颜色正好装满该包',
        'dozen_cost' => '一打的人民币成本：一副的成本 × 12',
        'available' => '当前库存中的数量，以打计',
        'incoming' => '已采购并经进口方确认，但尚未到货',
        'sale_price' => '一打的售价',
        'line_total' => '该明细行的总价：副数 × 一副的成本',
        'final_dozen_cost_iqd' => '一打的第纳尔最终成本：一副的最终成本 × 12。此成本即成为商品的成本',
        'cartons' => '该明细行的箱数：包数 ÷ 每箱包数，未装满的箱按整箱计算',
        'line_cbm' => '该明细行的体积：箱数 × 一箱的体积',
        'transport_cost' => '该明细行的美元运费：其 CBM × 采购单的每 CBM 价格',
        'transport_cost_per_piece' => '一副的美元运费：该明细行的运费 ÷ 其副数',
        'previous_unit_cost' => '商品当前的第纳尔成本，来自最近一张已确认的采购单。若本明细行的每打最终成本更高则显示红色，更低则显示绿色',
        'supplier_debt' => '在本采购单之前，该供应商各采购单上仍欠的全部款项',
        'invoice_amount' => '各明细行合计之和',
        'remaining_after_payment' => '采购单金额减去首次付款',
        'supplier_debt_after' => '当前欠供应商的款项加上本采购单仍欠的款项',
    ],

    'actions' => [
        'print_label' => '打印标签',
        'confirm' => [
            'label' => '以 :side 身份确认',
            'heading' => '以 :side 身份确认',
            'description' => '采购单金额为 :amount。请再次输入该金额以确认。',
            'amount' => '金额',
            'submit' => '确认',
            'done' => '已以 :side 身份确认',
        ],
        'advance_stage' => [
            'label' => '推进到：:stage',
            'description' => '阶段只能向前推进，不能退回。',
            'done' => '采购单当前阶段：:stage',
        ],
        'revert_confirmation' => [
            'label' => '撤回最近一次确认',
            'description' => '采购单将退回到最近一次确认之前的阶段。',
            'done' => '已撤回最近一次确认',
        ],
        'print' => '打印采购单',
        'add_line' => '添加明细行',
        'add_group' => '添加组',
        'edit_group' => '编辑组',
        'save_group' => '保存',
        'add_color' => '添加颜色',
        'show_groups' => '度数和颜色',
        'close' => '关闭',
        'more' => '更多',
        'discard_draft' => [
            'label' => '清除已保存的内容',
            'heading' => '清除已保存的内容',
            'description' => '已恢复的内容将被清除，发票重新开始填写。',
            'submit' => '清除',
        ],
    ],

    'widgets' => [
        'importer_confirmed_count' => '等待收货方确认',
        'importer_confirmed_count_info' => [
            'explanation' => '进口方已确认、但收货方尚未确认的采购单数量。这些采购单的货物已采购并在运输途中，因此其数量计为商品的在途数量，而不是现有数量。收货方确认采购单后，该单即不再计入此数，其数量进入库存。这是当前的数量，因此没有时间范围。已删除的采购单不计入。',
            'example' => '共有 6 张采购单：2 张仍待确认，1 张已由进口方确认，3 张已双方确认。卡片显示 1。',
        ],
        'pending_count' => '待确认采购单',
        'pending_count_info' => [
            'explanation' => '仍处于待确认状态（即尚未确认）的采购单数量。采购单确认后即不再计入此数。这是当前的数量，因此没有时间范围。已删除的采购单不计入。',
            'example' => '共有 10 张采购单：7 张已确认，3 张仍待确认。卡片显示 3。',
        ],
        'purchases_chart' => [
            'heading' => '采购最多的供应商',
        ],
    ],
];
