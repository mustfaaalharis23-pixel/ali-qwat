{{--
    The full page invoice of one order. The type carries the order of importance, and blue is the brand: a bar across
    the top, the square full stop of the wordmark, the band behind the head of the table, and the panel of the net total.
    Blue is never used for text, only the dark blue-800 for small eyebrows, so the page reads the same printed in
    black and white, where the rules, the weights, and the sizes carry it all.
    Every amount is one left-to-right run with its currency, lined up on its last digit in both languages.
    The details of the order (date and time, payment, cashier, item count) sit in a small two-column block beside the
    customer. The lines of one item at one price and one color mix are one row, with a small picture of the item. The row
    reads in dozens: a small table of its powers with the packets and dozens of each (or, without powers, its quantity
    in dozens with the whole packets under it), and the price of one dozen, so the dozens by the price make the row's total.
    A long order runs over pages: the head of the table repeats on every page, a line is never cut in two, and the
    totals stay together. A down payment, when the order has one, sits right above the customer's signature, so whoever
    signs sees what was paid. It is the customer's invoice, so it never carries what the items cost us, the profit,
    or the margin. It follows the language of the panel, right to left in Arabic and left to right in English.
--}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $discount = (float) $order->amount - (float) $order->total_amount;
    $customer = $order->customer;
    $hasCustomer = filled($customer?->name) || filled($customer?->phone) || filled($customer?->email);
    $count = fn (float $value): string => \Illuminate\Support\Number::format($value, maxPrecision: 2, locale: app()->getLocale());

    // One row per item sold at one price with one color mix, so an item sold at many powers is not repeated:
    // its powers go in a small table inside the row, lowest first (no power before them), with the packets and dozens of each.
    $rows = $order->items
        ->groupBy(fn ($line): string => $line->item_id.'|'.(float) $line->price.'|'.json_encode(\App\Models\Item::colorMix($line->colors)))
        ->values()
        ->map(fn ($lines): array => [
            'line' => $lines->first(),
            'powers' => $lines
                ->groupBy(fn ($line): string => (string) $line->lens_power_id)
                ->map(fn ($same): array => [
                    'power' => $same->first()->lensPower?->value,
                    'packets' => (float) $same->sum(fn ($line): float => $line->packets()),
                    'dozens' => (float) $same->sum('quantity'),
                ])
                ->sortBy(fn (array $power): float => $power['power'] === null ? -INF : (float) $power['power'])
                ->values(),
            'pieces' => (float) $lines->sum('pieces'),
            'dozens' => (float) $lines->sum('quantity'),
            'total' => (float) $lines->sum('total_price'),
        ]);
    $hasAnyPowers = $order->items->contains(fn ($line): bool => $line->lens_power_id !== null);
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('invoices.print.title', ['number' => $order->id]) }}</title>
    <style>
        {!! $fonts !!}

        @page {
            size: A4;
            margin: 14mm 16mm 18mm;

            @bottom-center {
                content: "#{{ $order->id }} · " counter(page) "/" counter(pages);
                direction: ltr;
                color: #475569;
                font-family: 'IBM Plex Sans Arabic', sans-serif;
                font-size: 8pt;
            }
        }

        :root {
            --ink: #0f172a;
            --text: #334155;
            --label: #475569;
            --hair: #cbd5e1;
            --brand: #2563eb;
            --brand-wash: #dbeafe;
            --brand-text: #1e40af;
            --brand-deep: #1e3a8a;
            --paper: #ffffff;
            --desk: #f1f5f9;
            --end-column: 40%;
            --gutter: 10mm;
            --cell: 8px;
            --font: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, 'PingFang SC', 'Noto Sans CJK SC', 'Noto Sans SC', 'Microsoft YaHei', sans-serif;
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
            font-weight: 400;
            line-height: 1.55;
            font-variant-numeric: tabular-nums lining-nums;
            font-kerning: normal;
            text-rendering: optimizeLegibility;
        }

        body:lang(ar) {
            font-size: 13px;
            line-height: 1.8;
        }

        h1,
        p,
        dl,
        dd {
            margin: 0;
        }

        .money {
            direction: ltr;
            text-align: end;
            white-space: nowrap;
        }

        .currency {
            unicode-bidi: isolate;
            margin-inline-end: 4px;
            color: var(--label);
            font-size: .8em;
            font-weight: 400;
        }

        .caps {
            color: var(--label);
            font-size: 11px;
            font-weight: 500;
            line-height: 1.5;
        }

        .caps:lang(en) {
            font-size: 9.5px;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .eyebrow {
            color: var(--brand-text);
        }

        .brand-bar {
            height: 4px;
            margin-bottom: 7mm;
            background: var(--brand);
        }

        .masthead {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
        }

        .wordmark-name {
            color: var(--ink);
            font-size: 32px;
            font-weight: 700;
            line-height: 1.1;
        }

        .wordmark-name:lang(ar) {
            line-height: 1.35;
        }

        .wordmark-name:lang(en) {
            letter-spacing: -.01em;
        }

        .wordmark-name::after {
            content: '';
            display: inline-block;
            width: .22em;
            height: .22em;
            margin-inline-start: .1em;
            background: var(--brand);
        }

        .wordmark-line {
            margin-top: 2px;
            color: var(--brand-text);
            font-size: 11.5px;
            font-weight: 500;
            line-height: 1.4;
        }

        .wordmark-line:lang(en) {
            font-size: 9.5px;
            letter-spacing: .28em;
            text-transform: uppercase;
        }

        .document {
            text-align: end;
        }

        .document-title {
            font-size: 13px;
            font-weight: 500;
            line-height: 1.4;
        }

        .document-title:lang(en) {
            font-size: 10.5px;
            letter-spacing: .24em;
            text-transform: uppercase;
        }

        .document-number {
            color: var(--ink);
            font-size: 26px;
            font-weight: 700;
            line-height: 1.2;
        }

        .summary,
        .closing,
        .signoff {
            display: grid;
            grid-template-columns: minmax(0, 1fr) var(--end-column);
            column-gap: var(--gutter);
            align-items: start;
        }

        .summary {
            margin-top: 9mm;
        }

        .party {
            padding-top: 7px;
            border-top: 1px solid var(--ink);
        }

        .party-name {
            margin-top: 4px;
            color: var(--ink);
            font-size: 17px;
            font-weight: 700;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .party-line {
            overflow-wrap: anywhere;
        }

        .facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 6px 16px;
            padding-top: 7px;
            border-top: 1px solid var(--ink);
            line-height: 1.4;
        }

        .facts dt {
            color: var(--label);
            font-size: 10.5px;
        }

        .facts dd {
            color: var(--ink);
            font-size: 12px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .facts .time {
            color: var(--label);
            font-weight: 500;
        }

        .lines {
            width: calc(100% + 2 * var(--cell));
            margin-top: 9mm;
            margin-inline: calc(-1 * var(--cell));
            border-collapse: collapse;
            table-layout: fixed;
        }

        .col-index {
            width: 5%;
        }

        .col-picture {
            width: 15%;
        }

        .col-quantity {
            width: 30%;
        }

        .col-price {
            width: 12%;
        }

        .col-amount {
            width: 13%;
        }

        .lines thead {
            display: table-header-group;
        }

        .lines tr {
            break-inside: avoid;
        }

        .lines th,
        .lines td {
            padding-inline: var(--cell);
            text-align: start;
        }

        .lines th {
            padding-block: 8px;
            border-bottom: 1px solid var(--ink);
            background: var(--brand-wash);
            color: var(--brand-deep);
            font-size: 11px;
            font-weight: 500;
            line-height: 1.4;
            vertical-align: bottom;
        }

        .lines th:lang(en) {
            font-size: 9.5px;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .lines th.money,
        .lines td.money {
            text-align: end;
        }

        .lines td {
            padding-block: 8px;
            border-bottom: 1px solid var(--hair);
            line-height: 1.5;
            vertical-align: middle;
        }

        .lines td:lang(ar) {
            line-height: 1.65;
        }

        .lines th.center {
            text-align: center;
        }

        /* The picture in its own column, large enough to see the frame, and the model centered beside it */

        .lines td.picture-cell {
            text-align: center;
        }

        .picture {
            display: block;
            width: 100%;
            max-width: 26mm;
            height: 18mm;
            margin-inline: auto;
            border: 1px solid var(--hair);
            border-radius: 6px;
            background: var(--desk);
            object-fit: cover;
        }

        .lines td.index {
            color: var(--label);
            font-size: 11px;
        }

        .lines td.item {
            color: var(--ink);
            font-weight: 500;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .lines td.item .model {
            display: block;
            font-size: 15px;
            font-weight: 700;
            line-height: 1.35;
        }

        .lines td.quantity .sets {
            display: block;
            color: var(--label);
            font-size: 11px;
            font-weight: 400;
        }

        .lines td.item .power {
            display: block;
            color: var(--label);
            font-size: 11px;
            font-weight: 600;
        }

        .lines td.amount {
            color: var(--ink);
            font-weight: 700;
        }

        .lines > tbody > tr:last-child > td {
            border-bottom-color: var(--ink);
        }

        /* The powers of a row: a table of the packets and dozens of each power, under a two-line heading */

        .lines th.powers-heading,
        .lines td.powers-cell {
            text-align: center;
        }

        .lines th.powers-heading .note {
            display: block;
            font-size: 10.5px;
            font-weight: 400;
        }

        .lines .powers {
            width: 100%;
            border: 1px solid var(--hair);
            border-radius: 6px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            table-layout: fixed;
            font-variant-numeric: tabular-nums;
        }

        .lines .powers th,
        .lines .powers td {
            padding: 2px 8px;
            border: 0;
            color: var(--ink);
            font-size: 12.5px;
            font-weight: 500;
            letter-spacing: normal;
            line-height: 1.5;
            text-align: center;
            text-transform: none;
            vertical-align: middle;
        }

        .lines .powers th {
            padding-inline: 4px;
            background: var(--desk);
            color: var(--label);
            font-size: 10.5px;
            white-space: nowrap;
        }

        .lines .powers td {
            border-top: 1px solid var(--hair);
        }

        .lines .powers th + th,
        .lines .powers td + td {
            border-inline-start: 1px solid var(--hair);
        }

        .lines .powers .no-power {
            color: var(--label);
            font-size: 11px;
        }

        .closing {
            margin-top: 7mm;
            break-inside: avoid;
        }

        .notes-body {
            min-height: 26mm;
            margin-top: 6px;
            padding: 8px 10px;
            border: 1px solid var(--hair);
            color: var(--ink);
            white-space: pre-line;
            overflow-wrap: anywhere;
            unicode-bidi: plaintext;
        }

        .totals {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            column-gap: 16px;
        }

        .total {
            display: grid;
            grid-column: 1 / -1;
            grid-template-columns: subgrid;
            align-items: baseline;
            padding-block: 4px;
        }

        .total-label {
            color: var(--label);
        }

        .total-amount {
            color: var(--ink);
            font-weight: 500;
        }

        .total.net {
            position: relative;
            isolation: isolate;
            margin-block: 10px 4px;
            padding-block: 10px 9px;
        }

        .total.net::before {
            content: '';
            position: absolute;
            z-index: -1;
            inset-block: 0;
            inset-inline: -3mm;
            border-top: 1px solid var(--ink);
            border-bottom: 3px double var(--ink);
            border-inline-start: 4px solid var(--brand);
            background: var(--brand-wash);
        }

        .total.net .total-label {
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        .total.net .total-amount {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.15;
        }

        .total.net .currency {
            color: var(--ink);
            font-size: 13px;
            font-weight: 500;
        }

        .ending {
            margin-top: 16mm;
            break-inside: avoid;
        }

        .down-payment {
            display: grid;
            grid-column: 2;
            grid-template-columns: minmax(0, 1fr) auto;
            column-gap: 16px;
            align-items: baseline;
            margin-bottom: 4mm;
            padding: 8px 3mm;
            border: 1px solid var(--ink);
            border-inline-start: 4px solid var(--brand);
        }

        .down-payment-label {
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
        }

        .down-payment-amount {
            color: var(--ink);
            font-size: 20px;
            font-weight: 700;
            line-height: 1.2;
        }

        .down-payment .currency {
            color: var(--ink);
            font-size: 12px;
            font-weight: 500;
        }

        .down-payment-note {
            grid-column: 1 / -1;
            margin-top: 2px;
            color: var(--label);
            font-size: 11px;
            line-height: 1.5;
        }

        .signature-line {
            height: 12mm;
            border-bottom: 1px solid var(--ink);
        }

        .signature .caps {
            margin-top: 6px;
        }

        .signature-note {
            margin-top: 4px;
            color: var(--label);
            font-size: 11px;
            line-height: 1.5;
        }

        .thanks {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 12mm;
            color: var(--text);
            font-size: 12px;
            text-align: center;
        }

        .thanks::before,
        .thanks::after {
            content: '';
            flex: 1;
            border-top: 1px solid var(--hair);
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
            line-height: 1.2;
            cursor: pointer;
            box-shadow: 0 6px 20px rgb(28 25 23 / .18);
        }

        .print-button::before {
            content: '';
            width: 7px;
            height: 7px;
            background: var(--brand);
        }

        .print-button:hover {
            background: #000000;
        }

        .print-button:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: 3px;
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
                padding: 14mm 16mm 16mm;
                background: var(--paper);
                box-shadow: 0 1px 2px rgb(28 25 23 / .06), 0 12px 32px rgb(28 25 23 / .08);
            }
        }

        @media screen and (max-width: 640px) {
            body {
                padding: 0 0 96px;
            }

            .sheet {
                min-height: 0;
                padding: 24px 16px;
                box-shadow: none;
            }

            .summary,
            .closing,
            .signoff {
                grid-template-columns: minmax(0, 1fr);
                row-gap: 20px;
            }

            .totals {
                order: -1;
            }

            .down-payment {
                grid-column: auto;
                margin-bottom: 0;
                order: 1;
            }

            .customer-signature {
                order: 2;
            }

            .lines {
                font-size: 11.5px;
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
        <div class="wordmark">
            <p class="wordmark-name">{{ __('invoices.print.brand') }}</p>
            <p class="wordmark-line">{{ __('invoices.print.brand_line') }}</p>
        </div>
        <div class="document">
            <h1 class="document-title eyebrow">{{ __('invoices.print.heading') }}</h1>
            <p class="document-number"><span dir="ltr">#{{ $order->id }}</span></p>
        </div>
    </header>

    <section class="summary">
        <div>
            @if ($hasCustomer)
                <div class="party">
                    <p class="caps eyebrow">{{ __('invoices.print.invoice_to') }}</p>
                    @if (filled($customer?->name))
                        <p class="party-name"><bdi>{{ $customer->name }}</bdi></p>
                    @endif
                    @if (filled($customer?->phone))
                        <p class="party-line"><span dir="ltr">{{ $customer->phone }}</span></p>
                    @endif
                    @if (filled($customer?->email))
                        <p class="party-line"><span dir="ltr">{{ $customer->email }}</span></p>
                    @endif
                </div>
            @endif
        </div>

        <dl class="facts">
            @if ($order->date)
                <div class="fact">
                    <dt>{{ __('invoices.print.date') }}</dt>
                    <dd>
                        <span dir="ltr">{{ $order->date->format('Y-m-d') }}</span>
                        @if ($order->created_at)
                            <span class="time">· {{ $order->created_at->translatedFormat('h:i A') }}</span>
                        @endif
                    </dd>
                </div>
            @endif
            @if ($order->payment_type)
                <div class="fact">
                    <dt>{{ __('invoices.print.payment_method') }}</dt>
                    <dd>{{ $order->payment_type->getLabel() }}</dd>
                </div>
            @endif
            @if (filled($order->cashier?->name))
                <div class="fact">
                    <dt>{{ __('invoices.print.cashier') }}</dt>
                    <dd><bdi>{{ $order->cashier->name }}</bdi></dd>
                </div>
            @endif
            <div class="fact">
                <dt>{{ __('invoices.print.items_count') }}</dt>
                <dd>{{ $rows->count() }}</dd>
            </div>
        </dl>
    </section>

    <table class="lines">
        <colgroup>
            <col class="col-index">
            <col class="col-picture">
            <col>
            <col class="col-quantity">
            <col class="col-price">
            <col class="col-amount">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th class="center">{{ __('invoices.print.picture') }}</th>
                <th class="center">{{ __('invoices.print.model') }}</th>
                @if ($hasAnyPowers)
                    <th class="powers-heading">
                        {{ __('invoices.print.powers_heading') }}
                        <span class="note">{{ __('invoices.print.powers_heading_note') }}</span>
                    </th>
                @else
                    <th class="money">{{ __('invoices.print.quantity') }}</th>
                @endif
                <th class="money">{{ __('invoices.print.unit_price') }}</th>
                <th class="money">{{ __('invoices.print.total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php
                    $line = $row['line'];
                    $hasPowers = $row['powers']->contains(fn (array $power): bool => $power['power'] !== null);
                @endphp
                <tr>
                    <td class="index">{{ $loop->iteration }}</td>
                    <td class="picture-cell">
                        @if ($source = $picture($line->item))
                            <img class="picture" src="{{ $source }}" alt="">
                        @else
                            <span class="picture"></span>
                        @endif
                    </td>
                    <td class="item">
                        <bdi class="model">{{ $line->item?->name }}</bdi>
                        @if ($line->colors)
                            <span class="power">
                                @foreach ($line->colorsWithNames() as $color)
                                    <bdi>{{ $color['name'] }}</bdi> {{ __('invoices.print.color_pieces', ['pieces' => \Illuminate\Support\Number::format($color['pieces'], maxPrecision: 2, locale: app()->getLocale())]) }}{{ $loop->last ? '' : __('filament/resources/invoice_item.group.separator') }}
                                @endforeach
                            </span>
                        @endif
                    </td>
                    <td class="quantity {{ $hasAnyPowers ? 'powers-cell' : 'money' }}">
                        @if ($hasPowers)
                            <table class="powers">
                                <thead>
                                    <tr>
                                        <th>{{ __('invoices.print.power_column') }}</th>
                                        <th>{{ __('invoices.print.packets_column') }}</th>
                                        <th>{{ __('invoices.print.dozens_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($row['powers'] as $power)
                                        <tr>
                                            <td>@if ($power['power'] === null)<span class="no-power">{{ __('invoices.print.no_power') }}</span>@else<bdi dir="ltr">{{ (float) $power['power'] > 0 ? '+'.$power['power'] : $power['power'] }}</bdi>@endif</td>
                                            <td><bdi>{{ $count($power['packets']) }}</bdi></td>
                                            <td><bdi>{{ $count($power['dozens']) }}</bdi></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <bdi>{{ trans('orders.dozens', ['count' => $count($row['dozens'])]) }}</bdi>
                            <span class="sets"><bdi>{{ $line->package->describe($row['pieces'], $line->package_size) }}</bdi></span>
                        @endif
                    </td>
                    <td class="money">{{ $money((float) $line->price) }}</td>
                    <td class="money amount">{{ $money($row['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="closing">
        <div class="notes">
            <p class="caps eyebrow">{{ __('invoices.print.notes') }}</p>
            <div class="notes-body">{{ $order->notes }}</div>
        </div>

        <div class="totals">
            <div class="total">
                <span class="total-label">{{ __('invoices.print.subtotal') }}</span>
                <span class="total-amount money"><span class="currency">{{ $currency }}</span>{{ $money((float) $order->amount) }}</span>
            </div>
            @if ($discount != 0)
                <div class="total">
                    <span class="total-label">{{ __('invoices.print.discount') }}</span>
                    <span class="total-amount money"><span class="currency">{{ $currency }}</span>{{ $money($discount) }}</span>
                </div>
            @endif
            <div class="total net">
                <span class="total-label">{{ __('invoices.print.net_total') }}</span>
                <span class="total-amount money"><span class="currency">{{ $currency }}</span>{{ $money((float) $order->total_amount) }}</span>
            </div>
        </div>
    </section>

    <footer class="ending">
        <div class="signoff">
            @if ($order->down_payment !== null)
                <div class="down-payment">
                    <span class="down-payment-label">{{ __('invoices.print.down_payment') }}</span>
                    <span class="down-payment-amount money"><span class="currency">{{ $currency }}</span>{{ $money((float) $order->down_payment) }}</span>
                    <p class="down-payment-note">{{ __('invoices.print.down_payment_note') }}</p>
                </div>
            @endif
            <div class="signature">
                <div class="signature-line"></div>
                <p class="caps">{{ __('invoices.print.preparer') }}</p>
                <p class="signature-note">{{ __('invoices.print.preparer_note') }}</p>
            </div>
            <div class="signature customer-signature">
                <div class="signature-line"></div>
                <p class="caps">{{ __('invoices.print.signature') }}</p>
            </div>
        </div>

        <p class="thanks">{{ __('invoices.print.thanks', ['name' => __('invoices.print.brand')]) }}</p>
    </footer>
</main>
</body>
</html>
