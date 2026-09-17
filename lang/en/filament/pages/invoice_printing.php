<?php

return [
    'title' => 'Invoice printing',

    'fields' => [
        'number' => 'Invoice number',
    ],

    'filters' => [
        'from' => 'From date',
        'until' => 'Until date',
    ],

    'actions' => [
        'print' => 'Print',
        'print_a4' => 'Full page invoice (A4)',
        'print_pos' => 'Receipt (POS printer)',
    ],
];
