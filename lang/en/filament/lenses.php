<?php

return [
    'info' => [
        'what_to_do' => 'What to do',
    ],

    'inactive_items_with_stock' => [
        'label' => 'Inactive items with stock',
        'explanation' => 'The inactive items that still have a quantity in the stock. An inactive item is hidden everywhere in the system and cannot be sold, so a quantity left on it is money standing still. Deleted items are not shown.',
        'guidance' => 'Open the lens and look at the quantity of each item. Use "Activate" on the item that should be sold again, and it leaves the lens. When no inactive item has a quantity left, the card shows 0 and stops blinking.',
    ],

    'items_with_a_negative_quantity' => [
        'label' => 'Items with a negative quantity',
        'explanation' => 'The active items whose available quantity is under zero: more was sold of them than ever came in on the purchase invoices both sides confirmed. The quantity is never written by hand, so it means a purchase invoice is missing or was deleted after the selling. Deleted items are not shown.',
        'guidance' => 'Open the lens and use "Work the quantity out again" on the item: the quantity and the cost are read again from the purchases and the orders themselves. If the number stays under zero, the purchase invoice of the item is what is missing — enter it or confirm it. When no item is under zero, the card shows 0 and stops blinking.',
    ],

    'items_with_a_low_margin' => [
        'label' => 'Items with a margin under :percentage%',
        'explanation' => 'The active items that earn something on a sale, but less than :percentage% of their sale price: the sale price minus the cost price, out of the sale price. The prices are for a dozen, and the cost price is what one dozen cost on the last purchase both sides confirmed. An item sold at a loss is not here, it has its own lens, and an item without a sale price is not priced yet. Deleted items are not shown.',
        'guidance' => 'Open the lens and use "Fix the price" on each item: write the new sale price, the profit margin you want on one dozen, or the margin as a percentage, and the rest is worked out from the cost. When every item earns :percentage% or more, the card shows 0 and stops blinking.',
    ],

    'items_sold_at_a_loss' => [
        'label' => 'Items sold at a loss',
        'explanation' => 'The active items whose sale price is under their cost price, which is what one dozen of them cost on the last purchase both sides confirmed. Every dozen sold of them loses the difference. Deleted items are not shown.',
        'guidance' => 'Open the lens and use "Fix the price" on each item: write the new sale price, or write the profit margin you want and the sale price is worked out from the cost. When no item sells under its cost, the card shows 0 and stops blinking.',
    ],

    'items_without_supplier' => [
        'label' => 'Items without a supplier',
        'explanation' => 'The active items that have no supplier (company) set. Inactive and deleted items are not shown.',
        'guidance' => 'Open the lens, select the items, and use "Set supplier" to choose the company each item belongs to. When every item has a supplier, the card shows 0 and stops blinking.',
    ],

    'items_without_sales' => [
        'label' => 'Items without any sales',
        'explanation' => 'The active items that were never sold: not one packet of them appears on any order. An item that was never bought is here too, because nothing was sold of it, and an item whose only order was deleted comes back here. Deleted items are not shown.',
        'guidance' => 'Open the lens and look at each item: give it a sale price if it has none, ask why it does not move, or deactivate it if it is out of the range. When every item has at least one sale, the card shows 0 and stops blinking.',
    ],
];
