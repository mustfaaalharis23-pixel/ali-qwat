<?php

return [
    'label' => 'Item category',
    'plural_label' => 'Item categories',
    'navigation_label' => 'Item categories',

    'fields' => [
        'name' => 'Name',
        'items_count' => 'Items',
    ],

    'validation' => [
        'has_items' => 'This category has items, so it cannot be deleted.',
    ],
];
