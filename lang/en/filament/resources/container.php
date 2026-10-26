<?php

return [
    'label' => 'Container',
    'plural_label' => 'Containers',
    'navigation_label' => 'Containers',

    'fields' => [
        'id' => 'System number',
        'international_number' => 'International number',
        'cbm_price' => 'Price per cubic meter (CBM)',
        'loading_date' => 'Loading date',
        'expected_arrival_date' => 'Expected arrival date',
        'notes' => 'Notes',
        'total_cbm' => 'Total CBM',
        'transport_cost' => 'Transport cost',
    ],

    'hints' => [
        'international_number' => 'Four letters then seven digits, as written on the container.',
        'cbm_price' => 'The container\'s price, a note only: it is not used in any calculation. Each invoice in the container is costed at its own price per CBM.',
        'expected_arrival_date' => 'Filled automatically :days days after the loading date. You can change it.',
    ],

    'how_it_works' => [
        'heading' => 'How the container is calculated',
        'description' => 'The container\'s CBM = the sum of its invoices\' CBM. Its transport cost = the sum of its invoices\' transport, each invoice at its CBM × its own price per CBM, not the container\'s price. Once an invoice first enters a container, its price per CBM is locked for good.',
    ],

    'invoices_description' => 'Each invoice\'s transport = its CBM × its own price per CBM (the column). The container\'s price per CBM is a note only.',

    'actions' => [
        'add_invoices' => 'Add invoices',
        'take_invoice_out' => 'Take out of the container',
        'take_invoices_out' => 'Take out of the container',
        'add_part_of_invoice' => [
            'label' => 'Add part of an invoice',
            'description' => 'Choose a purchase invoice and write how many cartons of each line go into this container. They become a new invoice in the container, and the original keeps the rest.',
            'invoice' => 'Purchase invoice',
            'cartons' => 'Cartons going into this container',
            'cartons_hint' => 'Out of :count cartons',
            'done' => 'The part was added to the container as invoice :name.',
            'line' => [
                'cartons' => ':value cartons',
                'packets_per_carton' => ':value packets per carton',
                'pieces_per_packet' => ':value pieces per packet',
                'pieces' => ':value pieces on the line',
            ],
            'preview' => [
                'nothing' => 'Nothing goes in; the whole line stays on the original',
                'goes' => 'Into the container: :cartons cartons · :packets packets · :pieces pieces',
                'stays' => 'Stays on the original: :cartons cartons · :packets packets · :pieces pieces',
                'all' => 'The whole line goes into the container: :cartons cartons · :packets packets · :pieces pieces; nothing stays on the original',
                'too_many' => 'More than the line has; it has only :count cartons',
            ],
        ],
    ],

    'widgets' => [
        'totals' => [
            'total_cbm' => 'Total CBM',
            'transport_cost' => 'Transport cost',
        ],
        'totals_info' => [
            'total_cbm' => [
                'explanation' => 'The space the goods take in this container: the sum of the CBM of the purchase invoices in it. An invoice\'s CBM is the sum of its lines, and a line\'s CBM is its number of cartons × the CBM of one carton. Deleted invoices are not counted.',
                'example' => 'The container has two invoices: one of 0.5 CBM and one of 1.5 CBM. Total CBM = 0.5 + 1.5 = 2.',
            ],
            'transport_cost' => [
                'explanation' => 'What shipping this container costs, in US dollars: the sum of the transport costs of its invoices. Each invoice is costed at its own price per CBM: its CBM × its price. The container\'s price per CBM is a note only and is not used. Each piece\'s share is its line\'s share ÷ the line\'s pieces.',
                'example' => 'The container has two invoices: one of 0.5 CBM at $120 and one of 1.5 CBM at $100. Transport cost = 0.5 × 120 + 1.5 × 100 = 60 + 150 = $210. If the first invoice is 240 pieces, each piece costs 60 ÷ 240 = $0.25.',
            ],
        ],
    ],
];
