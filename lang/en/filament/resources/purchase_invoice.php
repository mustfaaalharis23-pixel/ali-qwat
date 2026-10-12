<?php

return [
    'label' => 'Purchase invoice',
    'plural_label' => 'Purchase invoices',
    'navigation_label' => 'Purchase invoices',

    'sections' => [
        'details' => 'Invoice details',
        'amounts' => 'Amounts',
        'dollars' => 'In US dollars',
        'dinars' => 'In Iraqi dinars',
        'shipping' => 'Shipping',
        'progress' => 'Status and confirmations',
        'attachments' => 'Images and notes',
    ],

    'fields' => [
        'items' => 'Lines',
        'initial_payment' => 'Initial payment',
        'item' => 'Item',
        'unit_cost' => 'Unit cost',
        'final_unit_cost' => 'Final unit cost',
        'unit_cost_usd' => 'Unit cost in dollars',
        'price_usd' => 'Price in dollars',
        'line_total' => 'Line total',
    ],

    'split' => [
        'part' => 'Split from #:number',
        'part_hint' => 'A part split off invoice #:number when some of it was shipped in a container',
        'original' => 'Original · split into :numbers',
        'original_hint' => 'The original invoice; parts of it were split off and shipped in containers',
    ],

    'lines_below' => [
        'packets' => ':packets packets × :capacity',
        'per_carton' => ':value packets per carton',
        'carton_cbm' => ':value per carton',
        'usd' => ':value $',
        'final_parts' => ':cost + :transport transport',
        'per_piece' => ':value $ per unit',
    ],

    'exchange_rate_source_hint' => 'Source: :source',
    'dinar_rate_unit' => 'thousand IQD',
    'dinar_rate_hint' => 'As the market writes it: 100 $ = 170 thousand. So 1 $ = :value IQD',
    'cbm_price_hint' => 'Can be changed until the invoice enters a container; then it is locked for good.',
    'cbm_price_locked_hint' => 'Locked: the invoice has entered a container.',

    'notifications' => [
        'exchange_rate_unavailable' => [
            'title' => 'The exchange rate could not be fetched',
            'body' => 'Neither the Agricultural Bank of China nor China Merchants Bank answered. Type the exchange rate by hand: the yuan in one US dollar.',
        ],
    ],

    'validation' => [
        'initial_payment_above_amount' => 'The initial payment is more than the invoice amount.',
    ],

    'initial_payment_note' => [
        'heading' => 'Optional: what you paid the supplier at purchase',
        'description' => 'Saved automatically as the invoice\'s first payoff, on its date, and taken off what is owed to the supplier. It cannot be more than the invoice amount; further payoffs are added from the invoice page later.',
    ],

    'facts' => [
        'quantity' => ':value units',
        'available' => 'Available :value',
        'incoming' => 'Incoming :value',
        'sale_price' => 'Sells :value',
        'line_total' => 'Total :value',
        'unit_cost_usd' => 'Unit :value $',
        'line_total_usd' => 'Total :value $',
        'unit_cost_iqd' => 'Unit :value IQD',
        'line_total_iqd' => 'Total :value IQD',
        'final_unit_cost_iqd' => 'Final :value IQD',
        'cartons' => ':value cartons',
        'line_cbm' => ':value CBM',
        'transport_cost' => 'Transport :value $',
        'transport_cost_per_piece' => 'Per unit :value $',
        'previous_unit_cost' => 'Before :value',
        'supplier_debt' => 'Supplier owed now :value',
        'invoice_amount' => 'Invoice :value',
        'remaining_after_payment' => 'Left on it after payment :value',
        'supplier_debt_after' => 'Supplier owed after :value',
    ],

    'facts_hints' => [
        'quantity' => 'Units on the line: packets × packet capacity',
        'available' => 'What is in the stock now',
        'incoming' => 'Bought and confirmed by the importer, not arrived yet',
        'sale_price' => 'The sale price of one unit',
        'line_total' => 'The line\'s total price: units × unit cost',
        'unit_cost_usd' => 'The unit cost in US dollars: unit cost ÷ the invoice\'s exchange rate',
        'line_total_usd' => 'The line\'s total price in US dollars: line total ÷ the invoice\'s exchange rate',
        'unit_cost_iqd' => 'The unit cost in dinars: its cost in dollars × the dollar rate in dinars',
        'line_total_iqd' => 'The line\'s total price in dinars: its total in dollars × the dollar rate in dinars',
        'final_unit_cost_iqd' => 'The final cost of one unit in dinars: its cost + its transport. This becomes the item\'s cost',
        'cartons' => 'Cartons on the line: packets ÷ packets per carton, a partly filled carton counted as a whole one',
        'line_cbm' => 'The line\'s volume: cartons × the volume of one carton',
        'transport_cost' => 'The line\'s transport cost in US dollars: its CBM × the invoice\'s price per CBM',
        'transport_cost_per_piece' => 'The transport cost of one unit in US dollars: the line\'s transport ÷ its units',
        'previous_unit_cost' => 'The item\'s current cost in dinars, from the last confirmed purchase. Red when this line\'s final unit cost is dearer, green when it is cheaper',
        'supplier_debt' => 'Everything still owed to the supplier on its invoices before this one',
        'invoice_amount' => 'The sum of the line totals',
        'remaining_after_payment' => 'The invoice amount minus the initial payment',
        'supplier_debt_after' => 'What the supplier is owed now plus what stays owed on this invoice',
    ],

    'actions' => [
        'print_label' => 'Print label',
        'confirm' => [
            'label' => 'Confirm as the :side',
            'heading' => 'Confirm as the :side',
            'description' => 'The invoice amount is :amount. Write it again to confirm.',
            'amount' => 'Amount',
            'submit' => 'Confirm',
            'done' => 'Confirmed as the :side',
        ],
        'advance_stage' => [
            'label' => 'Move to: :stage',
            'description' => 'The stage only moves forward; it cannot be moved back.',
            'done' => 'The invoice is now: :stage',
        ],
        'revert_confirmation' => [
            'label' => 'Take back the last confirmation',
            'description' => 'The invoice goes back to the stage before its last confirmation.',
            'done' => 'The last confirmation was taken back',
        ],
        'print' => 'Print purchase invoice',
        'add_line' => 'Add line',
        'more' => 'More',
    ],

    'widgets' => [
        'importer_confirmed_count' => 'Waiting for the receiver',
        'importer_confirmed_count_info' => [
            'explanation' => 'How many purchase invoices the importer confirmed and the receiver has not confirmed yet. Their goods are bought and on their way, so their quantity is the incoming quantity of the items, not the available one. Once the receiver confirms an invoice, it leaves this count and its quantity enters the stock. It is the count right now, so it has no range. Deleted invoices are not counted.',
            'example' => 'There are 6 purchase invoices: 2 still pending, 1 the importer confirmed, and 3 confirmed by both sides. The card shows 1.',
        ],
        'pending_count' => 'Pending invoices',
        'pending_count_info' => [
            'explanation' => 'How many purchase invoices are still pending, meaning they have not been confirmed yet. Once an invoice is confirmed, it leaves this count. It is the count right now, so it has no range. Deleted invoices are not counted.',
            'example' => 'There are 10 purchase invoices: 7 confirmed and 3 still pending. The card shows 3.',
        ],
        'purchases_chart' => [
            'heading' => 'Suppliers we buy from the most',
        ],
    ],
];
