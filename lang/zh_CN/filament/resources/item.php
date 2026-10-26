<?php

return [
    'packet_size' => ':pieces 副',
    'label' => '商品',
    'plural_label' => '商品',
    'navigation_label' => '商品',

    'fields' => [
        'pieces_per_packet' => '每包副数',
        'sold_quantity' => '已售数量',
        'last_purchase_price' => '最近采购价',
        'average_purchase_price' => '平均采购价',
        'highest_purchase_price' => '最高采购价',
        'lowest_purchase_price' => '最低采购价',
        'available_quantity' => '现有（打）',
        'incoming_quantity' => '在途（打）',
        'last_cost_price' => '之前的成本价',
        'name' => '名称',
        'image' => '图片',
        'description' => '描述',
        'is_active' => '启用中',
        'supplier_id' => '供应商',
        'sale_price' => '每打售价',
        'cost_price' => '每打成本',
        'profit_margin' => '利润',
        'profit_margin_percentage' => '利润率 %',
    ],

    'widgets' => [
        'profit_margin' => '利润',
        'available_quantity' => '现有（打）',
        'incoming_quantity' => '在途（打）',
        'sold_quantity' => '已售数量',
        'last_purchase_price' => '最近采购价',
        'average_purchase_price' => '平均采购价',
        'highest_purchase_price' => '最高采购价',
        'lowest_purchase_price' => '最低采购价',
    ],

    'widgets_info' => [
        'profit_margin' => [
            'explanation' => '工厂在该商品每一打上赚取的金额：售价减去成本，成本即最近一次采购时每打的成本。它随成本变动，因此每当有新的采购经双方确认，它就会变化。',
            'example' => '该商品售价为 25,000，成本价为 12,000。卡片显示 13,000。',
        ],
        'available_quantity' => [
            'explanation' => '工厂当前拥有的该商品数量：经双方确认的采购单入库的全部数量，减去该商品已售出的全部数量。仅由进口方确认的数量不计入此处，因为尚未到货。已删除的采购单和已删除的订单不计入。',
            'example' => '已确认的采购单入库 120 打，已售出 12 打。卡片显示 108 打。',
        ],
        'incoming_quantity' => [
            'explanation' => '该商品已采购但尚未到货的数量：进口方已确认、收货方尚未确认的采购单上的数量。仅供参考：它不在库存中，不能销售；收货方确认采购单后，它才计入现有数量。',
            'example' => '一张 60 打的采购单已由进口方确认，仍在等待收货方确认。卡片显示 60，现有数量不变。',
        ],
        'sold_quantity' => [
            'explanation' => '该商品自开始以来售出的全部数量：所有订单中该商品明细行的数量之和。已删除的订单和已删除的明细行不计入。',
            'example' => '该商品在三个订单中售出：5 打、4 打和 3 打。卡片显示 12 打。',
        ],
        'last_purchase_price' => [
            'explanation' => '最近一次采购时该商品一打的第纳尔成本：在经双方确认的采购单入库的最近一个批次中，一副的最终成本（价格 + 运费）× 12。利润就是按这个成本价计算的。',
            'example' => '在最近一张采购单上，一副的价格为 850 第纳尔，运费为 425 第纳尔，每副共 1,275。卡片显示每打 15,300。',
        ],
        'average_purchase_price' => [
            'explanation' => '该商品在所有采购中一打的平均成本：为它支付的全部金额 ÷ 采购的全部副数 × 12，因此大批采购的权重高于小批采购。只计入经双方确认的采购单。',
            'example' => '以每打 100 采购了 10 打，又以每打 300 采购了 10 打。卡片显示 200。',
        ],
        'highest_purchase_price' => [
            'explanation' => '在经双方确认的采购单上的所有采购中，该商品一打曾经的最高采购价。',
            'example' => '该商品先以 100 采购，再以 300 采购，后以 250 采购。卡片显示 300。',
        ],
        'lowest_purchase_price' => [
            'explanation' => '在经双方确认的采购单上的所有采购中，该商品一打曾经的最低采购价。',
            'example' => '该商品先以 100 采购，再以 300 采购，后以 250 采购。卡片显示 100。',
        ],
    ],

    'sections' => [
        'details' => '商品详情',
        'prices' => '价格',
        'stock' => '库存',
        'purchase_prices' => '采购价',
        'by_power' => '按度数',
        'by_power_description' => '每个度数已到货多少、在途多少，以及销售后还剩多少。销售只从其对应的度数中扣减。',
        'by_color' => '按颜色',
        'by_color_description' => '以副计：每个度数下每种颜色已到货多少、在途多少，以及销售后还剩多少。每售出一包，都带着该包自身的颜色出库，且只从其对应的度数中扣减。',
    ],

    'hints' => [
        'pieces_per_packet' => '该商品一包有多少副：20 或 24。该商品只能按整包销售，其每包副数永不改变。',
        'piece_price' => '每副 :value',
        'cost_price' => '来源：最近一张经双方确认的采购单上，一副的第纳尔最终成本（价格 + 运费）× 12。由系统填写，从不手动输入。',
    ],

    'by_power' => [
        'power' => '度数',
        'arrived' => '已到货',
        'incoming' => '在途',
        'remaining' => '剩余',
        'packets_and_dozens' => ':packets 包（:dozens 打）',
        'dozens' => ':dozens 打',
    ],

    'by_color' => [
        'power' => '度数',
        'color' => '颜色',
        'arrived' => '已到货',
        'incoming' => '在途',
        'remaining' => '剩余',
        'pieces' => ':pieces 副',
    ],

    'filters' => [
        'status' => [
            'label' => '状态',
            'all' => '全部商品',
            'active' => '启用中的商品',
            'inactive' => '已停用的商品',
        ],
    ],

    'actions' => [
        'activate' => [
            'label' => '启用',
            'heading' => '启用 :item',
            'description' => '该商品将重新回到系统中：会显示在列表中，其数量可以再次销售。',
            'saved' => '商品已启用',
        ],

        'set_sale_price' => [
            'label' => '设定价格',
            'heading' => ':item 的价格',
            'cost_hint' => '最近一次采购时一打的成本。只能由系统填写。',
            'sale_price_hint' => '该商品的销售价格。填写后会自动算出利润。',
            'profit_margin_hint' => '一打赚取的金额：售价减去成本。填写后会自动算出售价。',
            'profit_margin_percentage_hint' => '利润占售价的比例。填写后会根据成本自动算出售价。',
            'saved' => '售价已保存',
        ],

        'recalculate_stock' => [
            'label' => '重新计算数量',
            'heading' => ':item 的数量',
            'description' => '现有数量、在途数量和成本价将直接从采购单和订单重新读取。商品的其他内容都不会改变。',
            'saved' => '数量已重新计算',
        ],

        'set_supplier' => [
            'label' => '设置供应商',
            'saved' => '供应商已设置',
        ],
    ],
];
