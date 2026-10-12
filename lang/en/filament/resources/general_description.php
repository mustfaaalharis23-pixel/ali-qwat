<?php

return [
    'label' => 'General description',
    'plural_label' => 'General descriptions',
    'navigation_label' => 'General descriptions',

    'fields' => [
        'name' => 'Name',
        'type' => 'Type',
        'is_purchases_description' => 'Purchases description',
        'is_capital_movement' => 'Capital movement',
    ],

    'sections' => [
        'details' => 'Description details',
    ],

    'hints' => [
        'is_capital_movement' => 'Money the partners put in or take out, such as an investment or a return of capital. It is neither a profit nor an expense, so the profit and loss report keeps it out of both sides.',
    ],

    'types' => [
        'input' => 'Input',
        'output' => 'Output',
    ],
];
