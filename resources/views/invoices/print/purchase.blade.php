{{--
    The checking sheet of one purchase invoice. It is held in the hand of the employee who receives the goods from the
    factory, on a clipboard, while they open the cartons and count. So every line is tall and plain: a big picture to
    know the item at a glance, the numbers to count (packets, packet capacity, cartons) large and bold, the price of one
    piece and the line's total beside them, an empty box to tick when the line matches, and an empty cell to write what
    was really found when it does not. The totals of the packets and cartons close the table, so the whole shipment is
    checked at once. It prints in black and white as well as in color, runs over
    pages with its head repeated and no line cut in two, and follows the language of the panel.
--}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $supplier = $invoice->supplier;
    $lines = $invoice->items;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('invoices.purchase_print.title', ['name' => $invoice->name]) }}</title>
    <link rel="stylesheet" href="{{ asset('fonts/invoice/font.css') }}">
    <style>
        @page {
            size: A4;
            margin: 12mm 12mm 16mm;

            @bottom-center {
                content: counter(page) "/" counter(pages);
                direction: ltr;
                color: #57534e;
                font-family: 'IBM Plex Sans Arabic', sans-serif;
                font-size: 8pt;
            }
        }

        :root {
            --ink: #1c1917;
            --text: #44403c;
            --label: #57534e;
            --hair: #d6d3d1;
            --amber: #f59e0b;
            --amber-wash: #fef3c7;
            --amber-deep: #78350f;
            --paper: #ffffff;
            --desk: #f5f5f4;
            --cell: 6px;
            --font: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--text);
            font-family: var(--font);
            font-size: 12.5px;
            line-height: 1.5;
            font-variant-numeric: tabular-nums lining-nums;
        }

        body:lang(ar) {
            font-size: 13px;
            line-height: 1.7;
        }

        h1,
        p,
        dl,
        dd {
            margin: 0;
        }

        .num {
            direction: ltr;
            unicode-bidi: isolate;
            white-space: nowrap;
        }

        .caps {
            color: var(--label);
            font-size: 11px;
            font-weight: 500;
        }

        .caps:lang(en) {
            font-size: 9.5px;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .brand-bar {
            height: 4px;
            margin-bottom: 6mm;
            background: var(--amber);
        }

        .masthead {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 5mm;
            border-bottom: 1px solid var(--ink);
        }

        .title {
            color: var(--ink);
            font-size: 24px;
            font-weight: 700;
            line-height: 1.25;
        }

        .subtitle {
            margin-top: 2px;
            color: var(--label);
            font-size: 12px;
        }

        .invoice-name {
            color: var(--ink);
            font-size: 16px;
            font-weight: 700;
            text-align: end;
            overflow-wrap: anywhere;
        }

        .facts {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 4mm;
            margin-top: 5mm;
        }

        .fact dt {
            color: var(--label);
            font-size: 11px;
        }

        .fact dd {
            margin-top: 2px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .fact .secondary {
            display: block;
            color: var(--label);
            font-size: 12px;
            font-weight: 500;
        }

        .how-to {
            margin-top: 5mm;
            padding: 7px 10px;
            border-inline-start: 4px solid var(--amber);
            background: var(--amber-wash);
            color: var(--amber-deep);
            font-size: 12px;
        }

        .lines {
            width: 100%;
            margin-top: 5mm;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .col-check {
            width: 9mm;
        }

        .col-image {
            width: 24mm;
        }

        .col-count {
            width: 15mm;
        }

        .col-price {
            width: 17mm;
        }

        .col-total {
            width: 21mm;
        }

        .col-found {
            width: 22mm;
        }

        .lines thead {
            display: table-header-group;
        }

        .lines tr {
            break-inside: avoid;
        }

        .lines th,
        .lines td {
            padding: var(--cell);
            border: 1px solid var(--hair);
            text-align: center;
            vertical-align: middle;
        }

        .lines th {
            border-color: var(--ink);
            background: var(--amber-wash);
            color: var(--amber-deep);
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1.3;
        }

        .lines td {
            height: 26mm;
        }

        .lines td.item {
            text-align: start;
        }

        .item-name {
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .item-index {
            display: block;
            margin-bottom: 2px;
            color: var(--label);
            font-size: 11px;
        }

        .picture {
            display: block;
            width: 22mm;
            height: 22mm;
            margin-inline: auto;
            border: 1px solid var(--hair);
            object-fit: cover;
        }

        .no-picture {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22mm;
            height: 22mm;
            margin-inline: auto;
            border: 1px dashed var(--hair);
            color: var(--label);
            font-size: 10px;
        }

        .count {
            color: var(--ink);
            font-size: 17px;
            font-weight: 700;
        }

        .price {
            color: var(--ink);
            font-size: 13px;
            font-weight: 500;
        }

        .total {
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        .currency {
            display: block;
            color: var(--label);
            font-size: 10px;
            font-weight: 400;
        }

        .tick {
            display: block;
            width: 6mm;
            height: 6mm;
            margin-inline: auto;
            border: 1.5px solid var(--ink);
        }

        .lines tfoot td {
            height: auto;
            padding-block: 10px;
            border-top: 2px solid var(--ink);
            border-color: var(--ink);
            background: var(--amber-wash);
            color: var(--ink);
            font-weight: 700;
        }

        .lines tfoot .label {
            text-align: start;
            font-size: 13px;
        }

        .lines tfoot {
            break-inside: avoid;
        }

        .print-button {
            position: fixed;
            inset-block-end: 24px;
            inset-inline-end: 24px;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 11px 22px;
            border: 0;
            border-radius: 999px;
            background: var(--ink);
            color: var(--paper);
            font-family: var(--font);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 6px 20px rgb(28 25 23 / .18);
        }

        .print-button::before {
            content: '';
            width: 7px;
            height: 7px;
            background: var(--amber);
        }

        @media screen {
            html,
            body {
                background: var(--desk);
            }

            body {
                padding: 32px 16px 112px;
            }

            .sheet {
                max-width: 210mm;
                min-height: 297mm;
                margin-inline: auto;
                padding: 12mm;
                background: var(--paper);
                box-shadow: 0 1px 2px rgb(28 25 23 / .06), 0 12px 32px rgb(28 25 23 / .08);
            }
        }

        @media screen and (max-width: 760px) {
            body {
                padding: 0 0 96px;
            }

            .sheet {
                min-height: 0;
                padding: 20px 16px;
                box-shadow: none;
                overflow-x: auto;
            }

            .facts {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .lines {
                min-width: 186mm;
            }
        }

        @media print {
            .screen-only {
                display: none !important;
            }

            html,
            body {
                background: var(--paper);
            }
        }
    </style>
</head>
<body>
<button type="button" class="print-button screen-only" onclick="window.print()">{{ __('invoices.print.print') }}</button>

<main class="sheet">
    <div class="brand-bar"></div>

    <header class="masthead">
        <div>
            <h1 class="title">{{ __('invoices.purchase_print.heading') }}</h1>
            <p class="subtitle">{{ __('invoices.purchase_print.subheading') }}</p>
        </div>
        <p class="invoice-name"><bdi>{{ $invoice->name }}</bdi></p>
    </header>

    <dl class="facts">
        <div class="fact">
            <dt>{{ __('invoices.purchase_print.supplier') }}</dt>
            <dd>
                <bdi>{{ $supplier?->name_ar ?? '—' }}</bdi>
                @if (filled($supplier?->name_zh))
                    <span class="secondary"><bdi>{{ $supplier->name_zh }}</bdi></span>
                @endif
            </dd>
        </div>
        <div class="fact">
            <dt>{{ __('invoices.purchase_print.category') }}</dt>
            <dd><bdi>{{ $invoice->category?->name ?? '—' }}</bdi></dd>
        </div>
        <div class="fact">
            <dt>{{ __('invoices.purchase_print.date') }}</dt>
            <dd><span class="num">{{ $invoice->date?->format('Y-m-d') ?? '—' }}</span></dd>
        </div>
        <div class="fact">
            <dt>{{ __('invoices.purchase_print.container') }}</dt>
            <dd><bdi>{{ $invoice->container?->displayName() ?? '—' }}</bdi></dd>
        </div>
        <div class="fact">
            <dt>{{ __('invoices.purchase_print.lines_count') }}</dt>
            <dd><span class="num">{{ $lines->count() }}</span></dd>
        </div>
    </dl>

    <p class="how-to">{{ __('invoices.purchase_print.how_to') }}</p>

    <table class="lines">
        <colgroup>
            <col class="col-check">
            <col class="col-image">
            <col>
            <col class="col-count">
            <col class="col-count">
            <col class="col-count">
            <col class="col-price">
            <col class="col-total">
            <col class="col-found">
        </colgroup>
        <thead>
            <tr>
                <th>✓</th>
                <th>{{ __('invoices.purchase_print.image') }}</th>
                <th>{{ __('invoices.purchase_print.item') }}</th>
                <th>{{ __('invoices.purchase_print.packets') }}</th>
                <th>{{ __('invoices.purchase_print.packet_capacity') }}</th>
                <th>{{ __('invoices.purchase_print.cartons') }}</th>
                <th>{{ __('invoices.purchase_print.piece_price') }}</th>
                <th>{{ __('invoices.purchase_print.total') }}</th>
                <th>{{ __('invoices.purchase_print.found') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td><span class="tick"></span></td>
                    <td>
                        @if (filled($line->item?->image))
                            <img class="picture" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($line->item->image) }}" alt="">
                        @else
                            <span class="no-picture">{{ __('invoices.purchase_print.no_image') }}</span>
                        @endif
                    </td>
                    <td class="item">
                        <span class="item-index num">#{{ $loop->iteration }}</span>
                        <span class="item-name"><bdi>{{ $line->item?->name ?? $line->specificDescription?->name }}</bdi></span>
                    </td>
                    <td class="count"><span class="num">{{ $number($line->boxes_count) }}</span></td>
                    <td class="count"><span class="num">{{ $number($line->box_quantity) }}</span></td>
                    <td class="count"><span class="num">{{ $number($line->cartons_count) }}</span></td>
                    <td class="price">
                        <span class="num">{{ $line->quantity == 0 ? '—' : $money($line->price / $line->quantity) }}</span>
                        <span class="currency">{{ $currency }}</span>
                    </td>
                    <td class="total">
                        <span class="num">{{ $money((float) $line->price) }}</span>
                        <span class="currency">{{ $currency }}</span>
                    </td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="label">{{ __('invoices.purchase_print.totals') }}</td>
                <td class="count"><span class="num">{{ $number((float) $lines->sum('boxes_count')) }}</span></td>
                <td></td>
                <td class="count"><span class="num">{{ $number((float) $lines->sum('cartons_count')) }}</span></td>
                <td></td>
                <td class="total">
                    <span class="num">{{ $money((float) $lines->sum('price')) }}</span>
                    <span class="currency">{{ $currency }}</span>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>

</main>
</body>
</html>
