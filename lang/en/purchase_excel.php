<?php

return [
    'order_tab' => 'Order',
    'order_title' => 'AKA Purchase Order',
    'start_row' => 'Fill from row 4',
    'guide_tab' => 'Instructions',
    'guide_title' => 'How to fill the AKA purchase order',
    'columns' => [
        'A' => 'Item code',
        'B' => 'Packets ordered',
        'C' => 'Pieces per packet',
        'D' => 'Packets per carton',
        'E' => 'Piece price (CNY)',
        'F' => 'Carton volume (m³)',
        'G' => 'Colors',
        'H' => 'Lens powers',
        'I' => 'Cartons (optional)',
        'J' => 'Total pieces (optional)',
        'K' => 'Line amount (CNY, optional)',
    ],
    'instructions' => [
        'Fill the Order sheet from row 4. Use one row per item and price. Keep the workbook title and column headings.',
        'Use the exact item code, including leading zeroes (for example 001). Keep the item code, colors and lens powers columns formatted as Text when adding rows.',
        'Enter packets ordered, pieces per packet, packets per carton, price in CNY per piece, and carton volume in cubic meters. Leave unknown values blank.',
        'If applicable, enter colors as name:pieces per packet. Example: Black:12 / Brown:12. Use the names agreed with AKA.',
        'If applicable, enter lens powers as decimal power:packets ordered. Example: +0.50:10 / +1.00:15. The quantities should add up to the packets ordered.',
        'Cartons, total pieces and line amount are optional. AKA calculates them and compares them with any values you enter. Save and return the XLSX file.',
    ],
];
