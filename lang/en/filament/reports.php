<?php

return [
    'fields' => [
        'mode' => 'Period',
        'date' => 'Date',
        'from' => 'From',
        'until' => 'Until',
    ],
    'modes' => [
        'day' => 'Single day',
        'range' => 'From - until',
    ],
    'actions' => [
        'fetch' => 'Fetch data',
        'download_excel' => 'Download Excel',
    ],
    'empty' => [
        'not_fetched' => 'Choose a period and press "Fetch data".',
        'no_data' => 'There is no data in this period.',
    ],
];
