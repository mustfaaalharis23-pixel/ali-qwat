<?php

return [
    'label' => 'Audit',
    'plural_label' => 'Audits',
    'navigation_label' => 'Audits',

    'system' => 'System',

    'fields' => [
        'event' => 'Event',
        'subject_type' => 'Record type',
        'subject_id' => 'Record number',
        'causer' => 'User',
        'created_at' => 'Date',
        'created_from' => 'From',
        'created_until' => 'Until',
        'attribute' => 'Field',
        'old' => 'Before',
        'new' => 'After',
    ],

    'sections' => [
        'details' => 'Details',
        'changes' => 'Changes',
    ],

    'events' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
        'forceDeleted' => 'Force deleted',
    ],

    'attributes' => [
        'id' => 'Number',
        'is_active' => 'Active',
        'email_verified_at' => 'Email verified at',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'deleted_at' => 'Deleted at',
    ],
];
