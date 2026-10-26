<?php

return [
    'label' => 'Invoice line',
    'plural_label' => 'Invoice lines',

    'fields' => [
        'invoice_id' => 'Invoice',
        'specific_description_id' => 'Specific description',
        'item_id' => 'Item',
        'lens_power_id' => 'Power',
        'groups' => 'Packet groups',
        'colors' => 'Colors in a packet',
        'color_id' => 'Color',
        'pieces' => 'Pieces in a packet',
        'price' => 'Price',
        'quantity' => 'Quantity (pieces)',
        'sets' => 'Dozens',
        'boxes_count' => 'Number of packets',
        'pieces_per_packet' => 'Packet size',
        'boxes_per_carton' => 'Packets per carton',
        'cartons_count' => 'Number of cartons',
        'carton_cbm' => 'Carton volume (CBM)',
        'cbm' => 'Line CBM',
        'transport_cost_per_piece' => 'Transport per piece',
        'produced_at' => 'Production date',
        'profit_difference' => 'Profit difference',
    ],

    'group' => [
        'power' => 'Power :value',
        'packets' => ':packets packets',
        'color' => ':color :pieces pcs',
        'separator' => ', ',
    ],
];
