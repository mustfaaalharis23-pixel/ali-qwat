{{--
    The receipt of one order for the small printer (80mm roll), written the way a receipt reads: the details as
    label and value lines, then every sold line as its name with its quantity by its price under it, and the sums
    at the end. It carries no cost and no profit, because it is handed to the customer.
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
            size: 80mm auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0 auto;
            padding: 14px 12px 28px;
            max-width: 80mm;
            background: #fff;
            color: #000;
            font-family: 'Tajawal', 'Almarai', 'Segoe UI', Tahoma, system-ui, sans-serif;
            font-size: 14px;
            line-height: 1.55;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .num {
            font-variant-numeric: tabular-nums;
        }

        .brand {
            text-align: center;
            font-size: 30px;
            font-weight: 700;
            letter-spacing: .22em;
            line-height: 1.1;
        }

        .brand small {
            display: block;
            font-size: 9px;
            font-weight: 400;
            letter-spacing: .34em;
            color: #444;
        }

        .heading {
            text-align: center;
            margin-top: 4px;
            font-size: 11px;
            letter-spacing: .2em;
            color: #444;
        }

        .cut {
            margin: 12px 0;
            border-top: 1px dashed #000;
        }

        .line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .line .key {
            color: #444;
            white-space: nowrap;
        }

        .line .value {
            font-weight: 700;
            text-align: end;
        }

        .item {
            margin-bottom: 8px;
        }

        .item .name {
            font-weight: 700;
        }

        .item .line {
            color: #222;
        }

        .sum {
            font-size: 15px;
        }

        .net {
            margin-top: 8px;
            padding: 8px 10px;
            border: 1px solid #000;
            font-size: 17px;
            font-weight: 700;
        }

        .thanks {
            margin-top: 14px;
            text-align: center;
            font-size: 12px;
            letter-spacing: .06em;
        }

        .print-button {
            display: block;
            width: 100%;
            margin-bottom: 14px;
            padding: 10px;
            border: 1px solid #000;
            background: #fff;
            color: #000;
            font: inherit;
            letter-spacing: .1em;
            cursor: pointer;
        }

        @media print {
            .screen-only {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<button type="button" class="print-button screen-only" onclick="window.print()">{{ __('invoices.print.print') }}</button>

<div class="brand">
    {{ __('invoices.print.brand') }}
    <small>{{ __('invoices.print.brand_line') }}</small>
</div>
<div class="heading">{{ __('invoices.print.heading') }} #{{ $order->id }}</div>

<div class="cut"></div>

<div class="line"><span class="key">{{ __('invoices.print.date') }}</span><span class="value num">{{ $order->date?->format('Y-m-d') }} · {{ $order->created_at?->format('h:i A') }}</span></div>
<div class="line"><span class="key">{{ __('invoices.print.cashier') }}</span><span class="value">{{ $order->cashier?->name }}</span></div>
<div class="line"><span class="key">{{ __('invoices.print.customer') }}</span><span class="value">{{ $order->customer?->name }}</span></div>
@if ($order->customer?->phone)
    <div class="line"><span class="key">{{ __('invoices.print.phone') }}</span><span class="value num">{{ $order->customer->phone }}</span></div>
@endif
<div class="line"><span class="key">{{ __('invoices.print.payment_method') }}</span><span class="value">{{ $order->payment_type?->getLabel() }}</span></div>

<div class="cut"></div>

@foreach ($order->items as $line)
    <div class="item">
        <div class="name">{{ $line->item?->name }}</div>
        <div class="line">
            <span class="num">{{ $money((float) $line->quantity) }} × {{ $money((float) $line->price) }}</span>
            <span class="value num">{{ $money((float) $line->total_price) }}</span>
        </div>
    </div>
@endforeach

<div class="cut"></div>

<div class="line sum"><span class="key">{{ __('invoices.print.items_count') }}</span><span class="value num">{{ $order->items->count() }}</span></div>
<div class="line sum"><span class="key">{{ __('invoices.print.subtotal') }}</span><span class="value num">{{ $money((float) $order->amount) }} {{ $currency }}</span></div>
@if ($discount != 0)
    <div class="line sum"><span class="key">{{ __('invoices.print.discount') }}</span><span class="value num">{{ $money($discount) }} {{ $currency }}</span></div>
@endif
<div class="line net"><span>{{ __('invoices.print.net_total') }}</span><span class="num">{{ $money((float) $order->total_amount) }} {{ $currency }}</span></div>

<div class="cut"></div>

<div class="thanks">{{ __('invoices.print.thanks', ['name' => __('invoices.print.brand')]) }}</div>
</body>
</html>
