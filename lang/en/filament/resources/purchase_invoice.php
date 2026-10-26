<?php

return [
    'label' => 'Purchase invoice',
    'plural_label' => 'Purchase invoices',
    'navigation_label' => 'Purchase invoices',

    'sections' => [
        'details' => 'Invoice details',
        'amounts' => 'Amounts',
        'costs' => 'Costs',
        'progress' => 'Status and confirmations',
        'attachments' => 'Images and notes',
        'line_specs' => 'Specs',
        'line_packing' => 'Packing',
        'line_cost' => 'Cost',
    ],

    'fields' => [
        'items' => 'Lines',
        'initial_payment' => 'Initial payment',
        'item' => 'Item',
        'unit_cost' => 'Cost per piece',
        'final_unit_cost' => 'Final cost per piece',
        'dozen_cost' => 'Cost per dozen',
        'total_iqd' => 'Total in dinars with transport',
        'final_dozen_cost' => 'Final cost per dozen',
        'unit_cost_usd' => 'Cost per piece in dollars',
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
        'power' => 'Power :value',
        'packets' => ':packets packets × :capacity',
        'per_carton' => ':value packets per carton',
        'carton_cbm' => ':value per carton',
        'usd' => ':value $',
        'final_parts' => ':cost + :transport transport',
        'per_dozen' => ':value $ per dozen',
    ],

    'exchange_rate_source_hint' => 'Source: :source',
    'dinar_rate_unit' => 'thousand IQD',
    'dinar_rate_hint' => 'As the market writes it: 100 $ = 170 thousand. So 1 $ = :value IQD',
    'groups_hint' => 'Split the line\'s packets into groups: each group is some packets at one power (or without one) with the same colors, and their packets must add up to the number of packets. The same power can repeat with other colors. Leave it empty for a line without a power and without colors, such as sunglasses.',
    'no_colors' => 'No colors',
    'colors_hint' => 'How many pieces of each color one packet holds; they must add up to the packet size (:pieces). Leave it empty for a packet without colors.',
    'cbm_price_hint' => 'Each line\'s transport = its CBM × this price; this is the price used, not the container\'s. It can be changed until the invoice enters a container; then it is locked for good.',
    'cbm_price_locked_hint' => 'Locked: the invoice has entered a container. Transport is calculated at this price, not the container\'s.',
    'groups_modal' => [
        'description' => 'Packet size :packet · :packets packets · :pieces pieces',
        'packets' => ':value packets',
        'pieces' => ':value pieces',
        'dozens' => ':value dozens',
        'per_packet' => ':value per packet',
    ],

    'costs_below' => [
        'rate' => '1 $ = :rate ¥',
        'shipping' => ':cbm CBM × :price $',
        'dinars' => ':amount + :transport transport',
    ],
    'lines_description' => 'The costs here are per dozen. Final cost of a dozen = its cost in dinars + its transport in dinars, and once both sides confirm the invoice it becomes the item\'s cost.',

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
        'groups_packets' => ':value of :total packets in groups',
        'packets_left_of' => ':value of :total packets left',
        'packets_left' => ':value packets left',
        'packets_over' => ':value packets too many',
        'packets_full' => 'All packets are in groups',
        'colors_left_of' => ':value of :total left',
        'colors_over' => ':value too many',
        'colors_full' => 'The packet is full',
        'dozen_cost' => 'Dozen :value',
        'available' => 'Available :value dozens',
        'incoming' => 'Incoming :value dozens',
        'sale_price' => 'Sells :value',
        'line_total' => 'Total :value',
        'final_dozen_cost_iqd' => 'Final dozen :value IQD',
        'cartons' => ':value cartons',
        'line_cbm' => ':value CBM',
        'transport_cost' => 'Transport :value $',
        'transport_cost_per_piece' => 'Per piece :value $',
        'previous_unit_cost' => 'Before :value',
        'supplier_debt' => 'Supplier owed now :value',
        'invoice_amount' => 'Invoice :value',
        'remaining_after_payment' => 'Left on it after payment :value',
        'supplier_debt_after' => 'Supplier owed after :value',
    ],

    'facts_hints' => [
        'groups_packets' => 'The packets of the groups together, out of the line\'s packets',
        'packets_left_of' => 'The line\'s packets left after this group, out of the line\'s packets',
        'packets_left' => 'The line\'s packets not in a group yet',
        'packets_over' => 'More packets than the line has',
        'packets_full' => 'The packets of the groups add up to the line\'s packets',
        'colors_left_of' => 'What is left of the packet without a color',
        'colors_over' => 'The colors pass the packet size',
        'colors_full' => 'The colors fill the packet exactly',
        'dozen_cost' => 'The cost of one dozen in yuan: the cost of one piece × 12',
        'available' => 'What is in the stock now, in dozens',
        'incoming' => 'Bought and confirmed by the importer, not arrived yet',
        'sale_price' => 'The sale price of one dozen',
        'line_total' => 'The line\'s total price: pieces × the cost of one piece',
        'final_dozen_cost_iqd' => 'The final cost of one dozen in dinars: the final cost of a piece × 12. This becomes the item\'s cost',
        'cartons' => 'Cartons on the line: packets ÷ packets per carton, a partly filled carton counted as a whole one',
        'line_cbm' => 'The line\'s volume: cartons × the volume of one carton',
        'transport_cost' => 'The line\'s transport cost in US dollars: its CBM × the invoice\'s price per CBM',
        'transport_cost_per_piece' => 'The transport cost of one piece in US dollars: the line\'s transport ÷ its pieces',
        'previous_unit_cost' => 'The item\'s current cost in dinars, from the last confirmed purchase. Red when this line\'s final cost per dozen is dearer, green when it is cheaper',
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
        'add_group' => 'Add group',
        'edit_group' => 'Edit group',
        'save_group' => 'Save',
        'add_color' => 'Add color',
        'show_groups' => 'Powers and colors',
        'close' => 'Close',
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
