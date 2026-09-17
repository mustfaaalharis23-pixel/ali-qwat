{{--
    The full page invoice of one order, ready to print. It is built to be read, not decorated: near black text on white,
    grey only for the labels, hairlines instead of boxes, and one quiet accent used in a thin rule and the brand.
    The numbers are tabular so the column reads straight down, and the amounts carry the weight, not colour.
    It is the customer's invoice, so it never carries what the items cost us, the profit, or the margin,
    neither on the paper nor on the screen.
    It follows the language of the panel, right to left in Arabic and left to right in English.
--}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $discount = (float) $order->amount - (float) $order->total_amount;
@endphp
    <!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('invoices.print.title', ['number' => $order->id]) }}</title>
    <style>
        @page {
            size: A4;
            margin: 16mm;
        }

        :root {
            --ink: #1a1a1a;
            --body: #3f3f46;
            --muted: #71717a;
            --hair: #e4e4e7;
            --rule: #a1a1aa;
            --accent: #0f766e;
            --tint: #f7f8f8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 44px 56px;
            color: var(--body);
            background: #fff;
            font-family: 'Almarai', 'Tajawal', 'Segoe UI', Tahoma, system-ui, sans-serif;
            font-size: 13px;
            line-height: 1.7;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .num {
            font-variant-numeric: tabular-nums;
        }

        .label {
            font-size: 10px;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            line-height: 1.5;
        }

        .value {
            color: var(--ink);
            font-weight: 700;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
        }

        .brand {
            font-size: 26px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: .04em;
            line-height: 1.2;
        }

        .brand .mark {
            width: 34px;
            margin-bottom: 10px;
            border-top: 3px solid var(--accent);
        }

        .brand small {
            display: block;
            font-size: 10px;
            font-weight: 400;
            letter-spacing: .24em;
            color: var(--muted);
        }

        .heading {
            text-align: end;
        }

        .heading .word {
            font-size: 12px;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .heading .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.3;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 40px;
            margin-top: 26px;
            padding: 16px 0;
            border-top: 1px solid var(--hair);
            border-bottom: 1px solid var(--hair);
        }

        .party {
            margin: 24px 0 8px;
        }

        .party .name {
            font-size: 18px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0 24px;
        }

        thead th {
            padding: 0 10px 10px;
            text-align: start;
            font-size: 10px;
            font-weight: 400;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--rule);
        }

        tbody td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--hair);
            color: var(--body);
        }

        tbody td.item {
            color: var(--ink);
        }

        th.end, td.end {
            text-align: end;
        }

        td.index {
            width: 26px;
            color: var(--muted);
        }

        td.money {
            color: var(--ink);
            font-weight: 700;
        }

        .closing {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
        }

        .sums {
            min-width: 280px;
            margin-inline-start: auto;
        }

        .sums .line {
            display: flex;
            justify-content: space-between;
            gap: 32px;
            padding: 3px 0;
        }

        .sums .net {
            margin-top: 10px;
            padding-top: 12px;
            border-top: 1px solid var(--rule);
            align-items: baseline;
        }

        .sums .net .title {
            font-size: 13px;
            letter-spacing: .06em;
            color: var(--ink);
        }

        .sums .net .amount {
            font-size: 21px;
            font-weight: 700;
            color: var(--ink);
        }

        .foot {
            display: flex;
            gap: 40px;
            margin-top: 48px;
            padding-top: 20px;
            border-top: 1px solid var(--hair);
        }

        .foot > div {
            width: 50%;
        }

        .field {
            margin-bottom: 28px;
        }

        .field .rule {
            margin-top: 26px;
            border-bottom: 1px solid var(--hair);
        }

        .notes-box {
            height: 116px;
            margin-top: 6px;
            padding: 10px 12px;
            border: 1px solid var(--hair);
            border-radius: 3px;
        }

        .thanks {
            margin-top: 34px;
            text-align: center;
            font-size: 10.5px;
            letter-spacing: .08em;
            color: var(--muted);
        }

        .print-button {
            position: fixed;
            bottom: 20px;
            inset-inline-end: 20px;
            padding: 10px 24px;
            border: 1px solid var(--rule);
            border-radius: 3px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 12px;
            letter-spacing: .08em;
            cursor: pointer;
        }

        .print-button:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        @media print {
            .screen-only {
                display: none !important;
            }

            body {
                padding: 0;
                color: #000;
            }
        }
    </style>
</head>
<body>
<button type="button" class="print-button screen-only" onclick="window.print()">{{ __('invoices.print.print') }}</button>

<header>
    <div class="brand">
        <div class="mark"></div>
        {{ __('invoices.print.brand') }}
        <small>{{ __('invoices.print.brand_line') }}</small>
    </div>
    <div class="heading">
        <div class="word">{{ __('invoices.print.heading') }}</div>
        <div class="number num">#{{ $order->id }}</div>
    </div>
</header>

<div class="meta">
    <div>
        <div class="label">{{ __('invoices.print.date') }}</div>
        <div class="value num">{{ $order->date?->format('Y-m-d') }}</div>
    </div>
    <div>
        <div class="label">{{ __('invoices.print.time') }}</div>
        <div class="value num">{{ $order->created_at?->format('h:i A') }}</div>
    </div>
    <div>
        <div class="label">{{ __('invoices.print.payment_method') }}</div>
        <div class="value">{{ $order->payment_type?->getLabel() }}</div>
    </div>
    <div>
        <div class="label">{{ __('invoices.print.cashier') }}</div>
        <div class="value">{{ $order->cashier?->name }}</div>
    </div>
    <div>
        <div class="label">{{ __('invoices.print.items_count') }}</div>
        <div class="value num">{{ $order->items->count() }}</div>
    </div>
</div>

<div class="party">
    <div class="label">{{ __('invoices.print.invoice_to') }}</div>
    <div class="name">{{ $order->customer?->name }}</div>
    <div class="num">{{ $order->customer?->phone }}</div>
    <div>{{ $order->customer?->email }}</div>
</div>

<table>
    <thead>
    <tr>
        <th>#</th>
        <th>{{ __('invoices.print.item') }}</th>
        <th class="end">{{ __('invoices.print.quantity') }}</th>
        <th class="end">{{ __('invoices.print.unit_price') }}</th>
        <th class="end">{{ __('invoices.print.total') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($order->items as $index => $line)
        <tr>
            <td class="index num">{{ $index + 1 }}</td>
            <td class="item">{{ $line->item?->name }}</td>
            <td class="end num">{{ $money((float) $line->quantity) }}</td>
            <td class="end num">{{ $money((float) $line->price) }}</td>
            <td class="end num money">{{ $money((float) $line->total_price) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="closing">
    <div class="sums">
        <div class="line">
            <span class="label">{{ __('invoices.print.subtotal') }}</span>
            <span class="num">{{ $money((float) $order->amount) }} {{ $currency }}</span>
        </div>
        @if ($discount != 0)
            <div class="line">
                <span class="label">{{ __('invoices.print.discount') }}</span>
                <span class="num">{{ $money($discount) }} {{ $currency }}</span>
            </div>
        @endif
        <div class="line net">
            <span class="title">{{ __('invoices.print.net_total') }}</span>
            <span class="amount num">{{ $money((float) $order->total_amount) }} {{ $currency }}</span>
        </div>
    </div>
</div>

<div class="foot">
    <div>
        <div class="field">
            <div class="label">{{ __('invoices.print.preparer') }}</div>
            <div class="rule"></div>
        </div>
        <div class="field">
            <div class="label">{{ __('invoices.print.signature') }}</div>
            <div class="rule"></div>
        </div>
        <div style="font-size:10.5px;color:var(--muted)">{{ __('invoices.print.preparer_note') }}</div>
    </div>
    <div>
        <div class="label">{{ __('invoices.print.notes') }}</div>
        <div class="notes-box">{{ $order->notes }}</div>
    </div>
</div>

<div class="thanks">{{ __('invoices.print.thanks', ['name' => __('invoices.print.brand')]) }}</div>
</body>
</html>
