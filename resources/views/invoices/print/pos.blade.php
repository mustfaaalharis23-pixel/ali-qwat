{{--
    The receipt of one order for the small thermal printer: an 80mm roll that prints about 72mm. Thermal paper has
    one colour, so it is pure black on white with no greys and no fills, and the order of importance is carried by
    size, weight, and rules alone, in type heavy enough for the print head. It reads the way a receipt reads: the
    details as label and value lines, every sold line as its name with its quantity by its price under it and its
    total at the end. A line reads in dozens: its quantity in dozens by the price of one dozen, its power and the
    colors of its packet under its name, and the whole packets it left in under the math. Then come the sums, with the net total between a single and a double rule as in a ledger.
    Every amount is one left-to-right run lined up on its last digit. Notes print only when the order has them.
    A down payment, when the order has one, sits in a box right above the signatures, so whoever signs sees what was paid.
    It carries no cost and no profit, because it is handed to the customer.
--}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $discount = (float) $order->amount - (float) $order->total_amount;
    $customer = $order->customer;
    $hasCustomer = filled($customer?->name) || filled($customer?->phone) || filled($customer?->email);
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
            margin: 0;
        }

        :root {
            --ink: #000000;
            --paper: #ffffff;
            --desk: #e2e8f0;
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
            color: var(--ink);
            font-family: var(--font);
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.45;
            font-variant-numeric: tabular-nums lining-nums;
            font-kerning: normal;
        }

        body:lang(ar) {
            font-size: 13px;
            line-height: 1.6;
        }

        h1,
        p {
            margin: 0;
        }

        .receipt {
            width: 100%;
            max-width: 80mm;
            margin-inline: auto;
            padding: 5mm 4mm 12mm;
        }

        .money {
            direction: ltr;
            text-align: end;
            white-space: nowrap;
        }

        .currency {
            unicode-bidi: isolate;
            margin-inline-end: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .label {
            font-size: 11px;
            line-height: 1.4;
        }

        .label:lang(en) {
            font-size: 10px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .brand {
            text-align: center;
        }

        .brand-name {
            padding-inline-start: .32em;
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
        }

        .brand-name:lang(ar) {
            line-height: 1.35;
        }

        .brand-name:lang(en) {
            letter-spacing: -.01em;
        }

        .brand-name::after {
            content: '';
            display: inline-block;
            width: .22em;
            height: .22em;
            margin-inline-start: .1em;
            background: var(--ink);
        }

        .brand-line {
            margin-top: 1px;
            font-size: 11px;
        }

        .brand-line:lang(en) {
            padding-inline-start: .3em;
            font-size: 10px;
            letter-spacing: .3em;
            text-transform: uppercase;
        }

        .document {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4mm;
            padding-block: 5px;
            border-top: 2px solid var(--ink);
            border-bottom: 1px solid var(--ink);
        }

        .document-title {
            font-size: 13px;
            font-weight: 700;
        }

        .document-number {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }

        .section {
            padding-block: 3mm;
            border-bottom: 1px dashed var(--ink);
            break-inside: avoid;
        }

        .row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }

        .row-key {
            flex: none;
        }

        .row-value {
            min-width: 0;
            font-weight: 700;
            text-align: end;
            overflow-wrap: anywhere;
        }

        .customer-name {
            margin-top: 1px;
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .customer-line {
            overflow-wrap: anywhere;
        }

        .items {
            padding-top: 3mm;
        }

        .items-head,
        .item {
            display: grid;
            grid-template-columns: 6mm minmax(0, 1fr) 24mm;
            align-items: baseline;
        }

        .items-head {
            padding-bottom: 4px;
            border-bottom: 1px solid var(--ink);
        }

        .head-index,
        .item-index {
            grid-column: 1;
            grid-row: 1;
        }

        .head-item {
            grid-column: 2;
            grid-row: 1;
        }

        .head-total {
            grid-column: 3;
            grid-row: 1;
        }

        .head-hint {
            grid-column: 2 / 4;
            grid-row: 2;
        }

        .item {
            padding-block: 5px;
            break-inside: avoid;
        }

        .item-index {
            font-size: 11px;
        }

        .item-name {
            grid-column: 2 / 4;
            grid-row: 1;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .item-power {
            grid-column: 2 / 4;
            grid-row: 2;
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        .item-power-value {
            margin-inline-end: 6px;
            font-weight: 700;
        }

        .item-math {
            grid-column: 2;
            grid-row: 3;
            font-size: 12px;
        }

        .item-total {
            grid-column: 3;
            grid-row: 3;
            font-weight: 700;
        }

        .item-sets {
            grid-column: 2;
            grid-row: 4;
            font-size: 11px;
        }

        .items-foot {
            padding-block: 5px 0;
            border-top: 1px solid var(--ink);
        }

        .totals {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            column-gap: 10px;
            padding-block: 3mm;
            border-bottom: 1px dashed var(--ink);
            break-inside: avoid;
        }

        .total {
            display: grid;
            grid-column: 1 / -1;
            grid-template-columns: subgrid;
            align-items: baseline;
        }

        .total + .total {
            margin-top: 1px;
        }

        .total-amount {
            font-weight: 700;
        }

        .total.net {
            margin-block: 2mm;
            padding-block: 5px 4px;
            border-top: 1px solid var(--ink);
            border-bottom: 4px double var(--ink);
        }

        .total.net .total-label {
            font-size: 14px;
            font-weight: 700;
        }

        .total.net .total-amount {
            font-size: 20px;
            line-height: 1.2;
        }

        .total.net .currency {
            font-size: 12px;
            font-weight: 700;
        }

        .notes-body {
            margin-top: 2px;
            white-space: pre-line;
            overflow-wrap: anywhere;
            unicode-bidi: plaintext;
        }

        .signoff {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            column-gap: 5mm;
            padding-top: 4mm;
            break-inside: avoid;
        }

        .down-payment {
            display: grid;
            grid-column: 1 / -1;
            grid-template-columns: minmax(0, 1fr) auto;
            column-gap: 10px;
            align-items: baseline;
            margin-bottom: 1mm;
            padding: 5px 6px;
            border: 2px solid var(--ink);
        }

        .down-payment-label {
            font-size: 14px;
            font-weight: 700;
        }

        .down-payment-amount {
            font-size: 18px;
            font-weight: 700;
            line-height: 1.2;
        }

        .down-payment .currency {
            font-size: 12px;
            font-weight: 700;
        }

        .down-payment-note {
            grid-column: 1 / -1;
            font-size: 11px;
            line-height: 1.5;
        }

        .signature-line {
            height: 10mm;
            border-bottom: 1px solid var(--ink);
        }

        .signature .label {
            margin-top: 3px;
        }

        .signoff-note {
            grid-column: 1 / -1;
            margin-top: 2mm;
            font-size: 11px;
            line-height: 1.5;
        }

        .thanks {
            margin-top: 5mm;
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
        }

        .thanks::after {
            content: '';
            display: block;
            width: 6px;
            height: 6px;
            margin: 3mm auto 0;
            background: var(--ink);
        }

        .print-button {
            display: block;
            width: 100%;
            margin-bottom: 4mm;
            padding: 10px;
            border: 2px solid var(--ink);
            background: var(--paper);
            color: var(--ink);
            font-family: var(--font);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
        }

        .print-button:hover {
            background: var(--ink);
            color: var(--paper);
        }

        .print-button:focus-visible {
            outline: 2px solid var(--ink);
            outline-offset: 2px;
        }

        @media screen {
            html,
            body {
                background: var(--desk);
            }

            body {
                padding: 24px 12px 48px;
            }

            .receipt {
                background: var(--paper);
                box-shadow: 0 1px 2px rgb(0 0 0 / .08), 0 10px 28px rgb(0 0 0 / .1);
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

            .receipt {
                width: 72mm;
                max-width: none;
                padding-inline: 0;
            }
        }
    </style>
</head>
<body>
<main class="receipt">
    <button type="button" class="print-button screen-only" onclick="window.print()">{{ __('invoices.print.print') }}</button>

    <header class="brand">
        <p class="brand-name">{{ __('invoices.print.brand') }}</p>
        <p class="brand-line">{{ __('invoices.print.brand_line') }}</p>
    </header>

    <div class="document">
        <h1 class="document-title">{{ __('invoices.print.heading') }}</h1>
        <p class="document-number"><span dir="ltr">#{{ $order->id }}</span></p>
    </div>

    <section class="section">
        @if ($order->date)
            <div class="row">
                <span class="row-key">{{ __('invoices.print.date') }}</span>
                <span class="row-value"><span dir="ltr">{{ $order->date->format('Y-m-d') }}</span></span>
            </div>
        @endif
        @if ($order->created_at)
            <div class="row">
                <span class="row-key">{{ __('invoices.print.time') }}</span>
                <span class="row-value">{{ $order->created_at->translatedFormat('h:i A') }}</span>
            </div>
        @endif
        @if ($order->payment_type)
            <div class="row">
                <span class="row-key">{{ __('invoices.print.payment_method') }}</span>
                <span class="row-value">{{ $order->payment_type->getLabel() }}</span>
            </div>
        @endif
        @if (filled($order->cashier?->name))
            <div class="row">
                <span class="row-key">{{ __('invoices.print.cashier') }}</span>
                <span class="row-value"><bdi>{{ $order->cashier->name }}</bdi></span>
            </div>
        @endif
    </section>

    @if ($hasCustomer)
        <section class="section">
            <p class="label">{{ __('invoices.print.customer') }}</p>
            @if (filled($customer?->name))
                <p class="customer-name"><bdi>{{ $customer->name }}</bdi></p>
            @endif
            @if (filled($customer?->phone))
                <p class="customer-line"><span dir="ltr">{{ $customer->phone }}</span></p>
            @endif
            @if (filled($customer?->email))
                <p class="customer-line"><span dir="ltr">{{ $customer->email }}</span></p>
            @endif
        </section>
    @endif

    <section class="items">
        <div class="items-head label">
            <span class="head-index">#</span>
            <span class="head-item">{{ __('invoices.print.item') }}</span>
            <span class="head-total money">{{ __('invoices.print.total') }}</span>
            <span class="head-hint">{{ __('invoices.print.quantity') }} × {{ __('invoices.print.unit_price') }}</span>
        </div>

        @foreach ($order->items as $line)
            <div class="item">
                <span class="item-index">{{ $loop->iteration }}</span>
                <span class="item-name"><bdi>{{ $line->item?->name }}</bdi></span>
                @if ($line->lensPower || $line->colors)
                    <span class="item-power">
                        @if ($line->lensPower)
                            <span class="item-power-value"><bdi>{{ __('invoices.print.power', ['value' => $line->lensPower->value]) }}</bdi></span>
                        @endif
                        @foreach ($line->colorsWithNames() as $color)
                            <bdi>{{ $color['name'] }}</bdi> {{ __('invoices.print.color_pieces', ['pieces' => \Illuminate\Support\Number::format($color['pieces'], maxPrecision: 2, locale: app()->getLocale())]) }}{{ $loop->last ? '' : __('filament/resources/invoice_item.group.separator') }}
                        @endforeach
                    </span>
                @endif
                <span class="item-math"><bdi>{{ trans('orders.dozens', ['count' => \Illuminate\Support\Number::format((float) $line->quantity, maxPrecision: 2, locale: app()->getLocale())]) }}</bdi> × {{ $money((float) $line->price) }}</span>
                <span class="item-total money">{{ $money((float) $line->total_price) }}</span>
                <span class="item-sets"><bdi>{{ $line->package->describe((float) $line->pieces, $line->package_size) }}</bdi></span>
            </div>
        @endforeach

        <div class="row items-foot">
            <span class="row-key">{{ __('invoices.print.items_count') }}</span>
            <span class="row-value">{{ $order->items->count() }}</span>
        </div>
    </section>

    <section class="totals">
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
    </section>

    @if (filled($order->notes))
        <section class="section">
            <p class="label">{{ __('invoices.print.notes') }}</p>
            <p class="notes-body">{{ $order->notes }}</p>
        </section>
    @endif

    <section class="signoff">
        @if ($order->down_payment !== null)
            <div class="down-payment">
                <span class="down-payment-label">{{ __('invoices.print.down_payment') }}</span>
                <span class="down-payment-amount money"><span class="currency">{{ $currency }}</span>{{ $money((float) $order->down_payment) }}</span>
                <p class="down-payment-note">{{ __('invoices.print.down_payment_note') }}</p>
            </div>
        @endif
        <div class="signature">
            <div class="signature-line"></div>
            <p class="label">{{ __('invoices.print.preparer') }}</p>
        </div>
        <div class="signature">
            <div class="signature-line"></div>
            <p class="label">{{ __('invoices.print.signature') }}</p>
        </div>
        <p class="signoff-note">{{ __('invoices.print.preparer_note') }}</p>
    </section>

    <p class="thanks">{{ __('invoices.print.thanks', ['name' => __('invoices.print.brand')]) }}</p>
</main>
</body>
</html>
