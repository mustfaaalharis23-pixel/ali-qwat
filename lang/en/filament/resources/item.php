<?php

return [
    'packet_size' => ':pieces pieces',
    'label' => 'Item',
    'plural_label' => 'Items',
    'navigation_label' => 'Items',

    'fields' => [
        'pieces_per_packet' => 'Packet size',
        'sold_quantity' => 'Sold quantity',
        'last_purchase_price' => 'Last purchase price',
        'average_purchase_price' => 'Average purchase price',
        'highest_purchase_price' => 'Highest purchase price',
        'lowest_purchase_price' => 'Lowest purchase price',
        'available_quantity' => 'Available (dozens)',
        'incoming_quantity' => 'Incoming (dozens)',
        'last_cost_price' => 'Previous cost price',
        'name' => 'Name',
        'image' => 'Image',
        'description' => 'Description',
        'is_active' => 'Active',
        'supplier_id' => 'Supplier',
        'sale_price' => 'Sale price of a dozen',
        'cost_price' => 'Cost of a dozen',
        'profit_margin' => 'Profit margin',
        'profit_margin_percentage' => 'Profit margin %',
    ],

    'widgets' => [
        'profit_margin' => 'Profit margin',
        'available_quantity' => 'Available (dozens)',
        'incoming_quantity' => 'Incoming (dozens)',
        'sold_quantity' => 'Sold quantity',
        'last_purchase_price' => 'Last purchase price',
        'average_purchase_price' => 'Average purchase price',
        'highest_purchase_price' => 'Highest purchase price',
        'lowest_purchase_price' => 'Lowest purchase price',
    ],

    'widgets_info' => [
        'profit_margin' => [
            'explanation' => 'What the factory earns on one dozen of this item: its sale price minus its cost, which is what the last purchase cost per dozen. It follows the cost, so it changes whenever a new purchase is confirmed by both sides.',
            'example' => 'The item sells for 25,000 and its cost price is 12,000. The card shows 13,000.',
        ],
        'available_quantity' => [
            'explanation' => 'What the factory has of this item right now: everything that came in on the purchase invoices both sides confirmed, minus everything sold of it. A quantity only the importer confirmed is not counted here, because it did not arrive yet. Deleted invoices and deleted orders are not counted.',
            'example' => '120 dozens came in on confirmed invoices and 12 were sold. The card shows 108 dozens.',
        ],
        'incoming_quantity' => [
            'explanation' => 'What was bought of this item and did not arrive yet: the quantity of the purchase invoices the importer confirmed and the receiver did not. It is information only: it is not in the stock, it cannot be sold, and it enters the available quantity when the receiver confirms the invoice.',
            'example' => 'An invoice of 60 dozens was confirmed by the importer and is still waiting for the receiver. The card shows 60, and the available quantity does not change.',
        ],
        'sold_quantity' => [
            'explanation' => 'Everything sold of this item since the beginning: the quantity of its lines on all the orders. Deleted orders and deleted lines are not counted.',
            'example' => 'The item was sold on three orders: 5, 4, and 3 dozens. The card shows 12 dozens.',
        ],
        'last_purchase_price' => [
            'explanation' => 'What one dozen of this item cost in dinars on the last purchase: the final cost of one piece (its price + its transport) × 12 in the last batch that came in on an invoice both sides confirmed. This is the cost price the profit margin is worked out from.',
            'example' => 'On the last invoice a piece cost 850 dinars and its transport 425 dinars, 1,275 a piece. The card shows 15,300 a dozen.',
        ],
        'average_purchase_price' => [
            'explanation' => 'What one dozen of this item cost on average across every purchase: all the money paid for it over all the pieces bought × 12, so a big purchase weighs more than a small one. Only the invoices both sides confirmed are counted.',
            'example' => '10 dozens were bought at 100 a dozen and 10 dozens at 300. The card shows 200.',
        ],
        'highest_purchase_price' => [
            'explanation' => 'The most one dozen of this item was ever bought for, across every purchase on an invoice both sides confirmed.',
            'example' => 'The item was bought at 100, then at 300, then at 250. The card shows 300.',
        ],
        'lowest_purchase_price' => [
            'explanation' => 'The least one dozen of this item was ever bought for, across every purchase on an invoice both sides confirmed.',
            'example' => 'The item was bought at 100, then at 300, then at 250. The card shows 100.',
        ],
    ],

    'sections' => [
        'details' => 'Item details',
        'prices' => 'Prices',
        'stock' => 'Stock',
        'purchase_prices' => 'Purchase prices',
        'by_power' => 'By power',
        'by_power_description' => 'What arrived of each power, what is on its way, and what is left of it after sales. A sale takes only from its own power.',
        'by_color' => 'By color',
        'by_color_description' => 'In pieces: what arrived of each color at each power, what is on its way, and what is left after sales. Every packet sold leaves with its own colors, from its own power only.',
    ],

    'hints' => [
        'pieces_per_packet' => 'How many pieces one packet of this item holds: 20 or 24. The item is sold by the whole packet only, and its packet never changes.',
        'piece_price' => 'A piece :value',
        'cost_price' => 'Where it comes from: the final cost of one piece in dinars (its price + its transport) × 12 on the last purchase invoice both sides confirmed. The system writes it; it is never typed.',
    ],

    'by_power' => [
        'power' => 'Power',
        'arrived' => 'Arrived',
        'incoming' => 'On the way',
        'remaining' => 'Left',
        'packets_and_dozens' => ':packets packets (:dozens dozens)',
        'dozens' => ':dozens dozens',
    ],

    'by_color' => [
        'power' => 'Power',
        'color' => 'Color',
        'arrived' => 'Arrived',
        'incoming' => 'On the way',
        'remaining' => 'Left',
        'pieces' => ':pieces pcs',
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'all' => 'All items',
            'active' => 'Active items',
            'inactive' => 'Inactive items',
        ],
    ],

    'actions' => [
        'activate' => [
            'label' => 'Activate',
            'heading' => 'Activate :item',
            'description' => 'The item goes back into the system: it shows in the lists and its quantity can be sold again.',
            'saved' => 'Item activated',
        ],

        'set_sale_price' => [
            'label' => 'Fix the price',
            'heading' => 'The price of :item',
            'cost_hint' => 'What one dozen cost on the last purchase. Only the system writes it.',
            'sale_price_hint' => 'The price the item is sold at. Writing it works out the profit margin.',
            'profit_margin_hint' => 'What one dozen earns: the sale price minus the cost. Writing it works out the sale price.',
            'profit_margin_percentage_hint' => 'What the margin is worth out of the sale price. Writing it works out the sale price from the cost.',
            'saved' => 'Sale price saved',
        ],

        'recalculate_stock' => [
            'label' => 'Work the quantity out again',
            'heading' => 'The quantity of :item',
            'description' => 'The available quantity, the incoming quantity, and the cost price are read again from the purchase invoices and the orders themselves. Nothing else on the item changes.',
            'saved' => 'Quantity worked out again',
        ],

        'set_supplier' => [
            'label' => 'Set supplier',
            'saved' => 'Supplier set',
        ],
    ],
];
