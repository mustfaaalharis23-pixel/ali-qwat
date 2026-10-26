<?php

return [
    'title' => '销售单打印',

    'fields' => [
        'number' => '单号',
    ],

    'filters' => [
        'from' => '开始日期',
        'until' => '结束日期',
    ],

    'actions' => [
        'print' => '打印',
        'print_a4' => '整页销售单（A4）',
        'print_pos' => '小票（POS 打印机）',
    ],
];
