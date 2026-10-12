<?php

return [
    'label' => 'Invoice line',
    'plural_label' => 'Invoice lines',

    'fields' => [
        'invoice_id' => 'Invoice',
        'specific_description_id' => 'Specific description',
        'item_id' => 'Item',
        'price' => 'Price',
        'quantity' => 'Quantity',
        'boxes_count' => 'Number of packets',
        'box_quantity' => 'Packet capacity',
        'boxes_per_carton' => 'Packets per carton',
        'cartons_count' => 'Number of cartons',
        'carton_cbm' => 'Carton volume (CBM)',
        'cbm' => 'Line CBM',
        'transport_cost_per_piece' => 'Transport per piece',
        'produced_at' => 'Production date',
        'profit_difference' => 'Profit difference',
    ],
];
