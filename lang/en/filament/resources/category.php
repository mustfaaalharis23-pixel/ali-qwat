<?php

return [
    'label' => 'Category',
    'plural_label' => 'Categories',
    'navigation_label' => 'Categories',
    'fields' => [
        'name' => 'Name',
        'items_count' => 'Items',
        'invoices_count' => 'Purchase invoices',
    ],
    'validation' => [
        'has_items' => 'This category has linked items and cannot be deleted.',
    ],
];
