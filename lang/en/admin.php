<?php

return [
    'actions' => [
        'close' => 'Close', 'cancel' => 'Cancel', 'confirm' => 'Confirm', 'info' => 'More info', 'save' => 'Save', 'save_changes' => 'Save changes',
        'create' => 'Create', 'new' => 'New', 'edit' => 'Edit', 'view' => 'View', 'delete' => 'Delete', 'restore' => 'Restore',
        'force_delete' => 'Delete forever', 'back' => 'Back', 'print' => 'Print', 'download' => 'Download', 'add' => 'Add', 'remove' => 'Remove',
        'search' => 'Search', 'apply' => 'Apply', 'reset' => 'Reset', 'export' => 'Export', 'saved' => 'Saved', 'deleted' => 'Deleted',
        'restored' => 'Restored', 'created' => 'Created', 'updated' => 'Updated', 'yes' => 'Yes', 'no' => 'No', 'more' => 'More',
    ],
    'confirm' => [
        'are_you_sure' => 'Are you sure you want to do this?',
        'delete' => 'Delete this record?',
        'delete_description' => 'It can be restored later from the deleted records.',
        'force_delete_description' => 'This cannot be undone.',
    ],
    'table' => [
        'search' => 'Search…', 'empty' => 'Nothing here yet', 'empty_hint' => 'Try another search or clear the filters.', 'actions' => 'Actions',
        'showing' => ':from–:to of :total', 'per_page' => 'Per page', 'previous' => 'Previous page', 'next' => 'Next page',
        'clear_filters' => 'Clear filters', 'trashed_without' => 'Without deleted', 'trashed_with' => 'With deleted', 'trashed_only' => 'Only deleted',
        'selected' => ':count selected', 'all' => 'All', 'columns' => 'Columns', 'total' => 'Total',
    ],
    'form' => ['choose' => 'Choose…', 'optional' => 'Optional', 'required' => 'Required', 'none' => 'None', 'from' => 'From', 'until' => 'Until'],
    'metrics' => ['example' => 'Example'],
    'layout' => ['menu' => 'Menu', 'theme' => 'Switch light or dark', 'logout' => 'Sign out', 'search' => 'Search…', 'command_placeholder' => 'Go to a page…', 'subtitle' => 'Admin', 'theme_light' => 'Light', 'theme_dark' => 'Dark', 'theme_system' => 'System'],
    'login' => [
        'title' => 'Sign in', 'subtitle' => 'Welcome back. Sign in to continue.', 'email' => 'Email', 'password' => 'Password',
        'remember' => 'Keep me signed in', 'submit' => 'Sign in',
    ],
    'errors' => [
        'not_found' => 'This page could not be found', 'forbidden' => 'You do not have permission to open this', 'failed' => 'Something went wrong',
        'go_home' => 'Back to the home page',
    ],
    'home' => [
        'action_new_order' => 'New order',
        'action_new_order_hint' => 'Sell from the stock',
        'action_new_purchase' => 'New purchase invoice',
        'action_new_purchase_hint' => 'Buy from a supplier',
        'action_payment' => 'Record a payment',
        'action_payment_hint' => 'A customer pays a debt',
        'action_financial_hint' => 'Boxes, expenses and debts',
        'greeting' => 'Hello, :name', 'subtitle' => 'Here is what needs your attention today.',
        'reminders_all' => 'All', 'reminders_today' => 'Due today', 'reminders_late' => 'Late',
        'late_since' => 'Late since :date', 'next_due' => 'Due :date', 'remaining_weeks' => ':weeks weeks left',
    ],
    'monitoring' => ['open_list' => 'Open the list'],
];
