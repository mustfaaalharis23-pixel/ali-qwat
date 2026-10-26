{{--
    The receipt (سند قبض) of one customer payment for the small thermal printer: an 80mm roll that prints about 72mm.
    Like the receipt of an order, it is pure black on white, and the order of importance is carried by size, weight, and rules alone.
    It reads the way a paper receipt reads: the number, the date and time, and who took the money, then whom it was received from,
    then the amount in a box, in numbers and in words. Under it, as in a ledger, what the customer owed before this payment,
    what was paid, and what is still owed, between a single and a double rule. The balances are the same as the account statement.
    Notes print only when the payment has them, and a down payment says so. Every amount is one left-to-right run lined up on its last digit.
--}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $customer = $payment->customer;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('invoices.receipt.title', ['number' => $payment->id]) }}</title>
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
            font-size: 15px;
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
            font-size: 15px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .customer-line {
            overflow-wrap: anywhere;
        }

        .amount {
            margin-block: 3mm;
            padding: 6px 7px;
            border: 2px solid var(--ink);
            break-inside: avoid;
        }

        .amount-figure {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
        }

        .amount-label {
            font-size: 14px;
            font-weight: 700;
        }

        .amount-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
        }

        .amount-value .currency {
            font-size: 12px;
            font-weight: 700;
        }

        .amount-words {
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed var(--ink);
        }

        .amount-words-text {
            margin-top: 1px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .down-payment {
            margin-top: 5px;
            font-size: 11px;
            line-height: 1.5;
        }

        .notes-body {
            margin-top: 2px;
            white-space: pre-line;
            overflow-wrap: anywhere;
            unicode-bidi: plaintext;
        }

        .totals {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            column-gap: 10px;
            padding-block: 3mm 0;
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
            margin-top: 2mm;
            padding-block: 5px 4px;
            border-top: 1px solid var(--ink);
            border-bottom: 4px double var(--ink);
        }

        .total.net .total-label {
            font-size: 14px;
            font-weight: 700;
        }

        .total.net .total-amount {
            font-size: 18px;
            line-height: 1.2;
        }

        .total.net .currency {
            font-size: 12px;
            font-weight: 700;
        }

        .end-mark {
            width: 6px;
            height: 6px;
            margin: 6mm auto 0;
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
        <h1 class="document-title">{{ __('invoices.receipt.heading') }}</h1>
        <p class="document-number"><span dir="ltr">#{{ $payment->id }}</span></p>
    </div>

    <section class="section">
        <div class="row">
            <span class="row-key">{{ __('invoices.print.date') }}</span>
            <span class="row-value"><span dir="ltr">{{ $payment->date->format('Y-m-d') }}</span></span>
        </div>
        @if ($payment->created_at)
            <div class="row">
                <span class="row-key">{{ __('invoices.print.time') }}</span>
                <span class="row-value">{{ $payment->created_at->translatedFormat('h:i A') }}</span>
            </div>
        @endif
        @if (filled($payment->user?->name))
            <div class="row">
                <span class="row-key">{{ __('invoices.receipt.received_by') }}</span>
                <span class="row-value"><bdi>{{ $payment->user->name }}</bdi></span>
            </div>
        @endif
    </section>

    <section class="section">
        <p class="label">{{ __('invoices.receipt.received_from') }}</p>
        <p class="customer-name"><bdi>{{ $customer?->name }}</bdi></p>
        @if (filled($customer?->phone))
            <p class="customer-line"><span dir="ltr">{{ $customer->phone }}</span></p>
        @endif
    </section>

    <section class="amount">
        <div class="amount-figure">
            <span class="amount-label">{{ __('invoices.receipt.amount') }}</span>
            <span class="amount-value money"><span class="currency">{{ $currency }}</span>{{ $money((float) $payment->amount) }}</span>
        </div>
        <div class="amount-words">
            <p class="label">{{ __('invoices.receipt.amount_in_words') }}</p>
            <p class="amount-words-text">{{ __('invoices.receipt.words', ['words' => $words]) }}</p>
        </div>
        @if ($payment->is_down_payment)
            <p class="down-payment">{{ __('invoices.receipt.down_payment') }}</p>
        @endif
    </section>

    @if (filled($payment->notes))
        <section class="section">
            <p class="label">{{ __('invoices.print.notes') }}</p>
            <p class="notes-body">{{ $payment->notes }}</p>
        </section>
    @endif

    <section class="totals">
        <div class="total">
            <span class="total-label">{{ __('invoices.receipt.previous_balance') }}</span>
            <span class="total-amount money"><span class="currency">{{ $currency }}</span>{{ $money($previousBalance) }}</span>
        </div>
        <div class="total">
            <span class="total-label">{{ __('invoices.receipt.paid') }}</span>
            <span class="total-amount money"><span class="currency">{{ $currency }}</span>{{ $money((float) $payment->amount) }}</span>
        </div>
        <div class="total net">
            <span class="total-label">{{ __('invoices.receipt.remaining') }}</span>
            <span class="total-amount money"><span class="currency">{{ $currency }}</span>{{ $money($remaining) }}</span>
        </div>
    </section>

    <div class="end-mark" aria-hidden="true"></div>
</main>
</body>
</html>
