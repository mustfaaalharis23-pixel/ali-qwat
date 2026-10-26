{{--
    The checking sheet of one purchase invoice. It is held in the hand of the employee who receives the goods from the
    factory, on a clipboard, while they open the cartons and count. So every line is a short card: a small picture, the
    item's name with its prices beside it, every amount with its currency sign and each strip named, so an amount is never
    taken for a count and yuan and dinars are never mixed up at a glance (one piece in yuan; one piece and a dozen landed in dinars, with the transport), then its colors and the
    packets of each power. The totals of the packets, the cartons, and the price close the sheet, with room for whoever
    received the goods to sign. It prints in black and white as well as in color, runs over pages with no card cut in
    two, and follows the language of the panel.
--}}
@php
    $isRtl = app()->getLocale() === 'ar';
    $supplier = $invoice->supplier;
    $lines = $invoice->items;
    $dozen = \App\Models\Item::PIECES_IN_A_DOZEN;
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
                color: #475569;
                font-family: 'IBM Plex Sans Arabic', sans-serif;
                font-size: 8pt;
            }
        }

        :root {
            --ink: #0f172a;
            --text: #334155;
            --label: #64748b;
            --hair: #e2e8f0;
            --rule: #cbd5e1;
            --brand: #2563eb;
            --brand-wash: #eff6ff;
            --brand-line: #bfdbfe;
            --brand-deep: #1e3a8a;
            --paper: #ffffff;
            --desk: #f1f5f9;
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
            font-size: 12px;
            line-height: 1.5;
            font-variant-numeric: tabular-nums lining-nums;
        }

        h1,
        h2,
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

        .label {
            color: var(--label);
            font-size: 10.5px;
            font-weight: 500;
            line-height: 1.3;
        }

        .label:lang(en) {
            font-size: 9px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .money .currency {
            unicode-bidi: isolate;
            margin-inline-end: 2px;
            color: var(--label);
            font-size: .75em;
            font-weight: 500;
        }

        .unit {
            margin-inline-start: 3px;
            color: var(--label);
            font-size: .7em;
            font-weight: 500;
        }

        /* The head of the sheet */

        .brand-bar {
            height: 3px;
            margin-bottom: 7mm;
            background: var(--brand);
        }

        .masthead {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8mm;
        }

        .title {
            color: var(--ink);
            font-size: 22px;
            font-weight: 700;
            line-height: 1.25;
        }

        .subtitle {
            margin-top: 3px;
            color: var(--label);
            font-size: 12px;
        }

        .invoice-tag {
            flex-shrink: 0;
            max-width: 75mm;
            padding: 8px 14px;
            border: 1.5px solid var(--ink);
            border-radius: 6px;
            text-align: center;
        }

        .invoice-tag .name {
            display: block;
            margin-top: 2px;
            color: var(--ink);
            font-size: 15px;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .facts {
            display: grid;
            grid-template-columns: 2fr repeat(4, minmax(0, 1fr));
            margin-top: 6mm;
            border: 1px solid var(--rule);
            border-radius: 6px;
        }

        .fact {
            padding: 8px 12px;
        }

        .fact + .fact {
            border-inline-start: 1px solid var(--hair);
        }

        .fact dd {
            margin-top: 3px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .fact .secondary {
            display: block;
            color: var(--label);
            font-size: 11px;
            font-weight: 500;
        }

        .how-to {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 4mm;
            padding: 8px 12px;
            border: 1px solid var(--brand-line);
            border-radius: 6px;
            background: var(--brand-wash);
            color: var(--brand-deep);
            font-size: 11.5px;
        }

        .how-to::before {
            content: '';
            flex-shrink: 0;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--brand-deep);
        }

        /* One compact card for every line: its name with its prices in one strip per currency, then its colors and powers */

        .cards {
            display: flex;
            flex-direction: column;
            gap: 3mm;
            margin-top: 5mm;
        }

        .card {
            display: grid;
            grid-template-columns: 18mm minmax(0, 1fr);
            align-items: start;
            gap: 3.5mm;
            padding: 2.5mm 3.5mm;
            border: 1px solid var(--rule);
            border-radius: 8px;
            break-inside: avoid;
        }

        .card-body {
            display: grid;
            gap: 1.8mm;
        }

        .picture,
        .no-picture {
            width: 18mm;
            height: 18mm;
            border-radius: 5px;
        }

        .picture {
            display: block;
            border: 1px solid var(--hair);
            object-fit: cover;
        }

        .no-picture {
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed var(--rule);
            color: var(--label);
            font-size: 9px;
            text-align: center;
        }

        .card-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 8px;
        }

        .index {
            flex-shrink: 0;
            min-width: 22px;
            padding: 0 6px;
            border-radius: 4px;
            background: var(--ink);
            color: var(--paper);
            font-size: 10.5px;
            font-weight: 700;
            text-align: center;
        }

        .item-name {
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        /* Yuan in a grey strip, landed dinars in a blue one, each named once at its start */

        .prices {
            display: flex;
            flex-wrap: wrap;
            gap: 2mm;
            margin-inline-start: auto;
        }

        .price-strip {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            border: 1px solid var(--rule);
            border-radius: 5px;
            overflow: hidden;
        }

        .price-strip .pairs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1px 10px;
            padding: 2px 10px;
        }

        .price-strip .tag {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: var(--desk);
            color: var(--ink);
            font-size: 10.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .pair {
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            white-space: nowrap;
        }

        .pair .label {
            font-size: 10px;
        }

        .pair .label:lang(en) {
            font-size: 8.5px;
            letter-spacing: .04em;
        }

        .pair b {
            color: var(--ink);
            font-size: 13.5px;
        }

        .pair .money .currency {
            margin-inline-end: 3px;
            color: inherit;
            font-size: .8em;
            font-weight: 600;
        }

        .price-strip.dinar {
            border-color: var(--brand-line);
        }

        .price-strip.dinar .tag {
            background: var(--brand-wash);
            color: var(--brand-deep);
        }

        .price-strip.dinar .pair b {
            color: var(--brand-deep);
        }

        /* The colors in one packet of the line and the packets of each power, on one line */

        .details {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 14px;
        }

        .detail {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
        }

        .detail > .label {
            margin-inline-end: 2px;
        }

        .power {
            display: inline-flex;
            border: 1px solid var(--rule);
            border-radius: 4px;
            overflow: hidden;
            font-size: 11px;
            white-space: nowrap;
        }

        .power .value {
            padding: 0 6px;
            background: var(--desk);
            color: var(--ink);
            font-weight: 700;
        }

        .power .packets {
            padding: 0 6px;
            color: var(--ink);
            font-weight: 600;
        }

        .chip {
            padding: 0 7px;
            border: 1px solid var(--rule);
            border-radius: 999px;
            color: var(--ink);
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }

        .none {
            color: var(--label);
        }

        /* The totals and the signature that close the sheet */

        .closing {
            break-inside: avoid;
        }

        .totals {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(0, 1.6fr);
            margin-top: 6mm;
            border: 1.5px solid var(--ink);
            border-radius: 8px;
            overflow: hidden;
        }

        .total {
            padding: 7px 12px;
        }

        .total + .total {
            border-inline-start: 1px solid var(--rule);
        }

        .total dd {
            margin-top: 1px;
            color: var(--ink);
            font-size: 16px;
            font-weight: 700;
            line-height: 1.3;
        }

        .total.grand {
            background: var(--desk);
        }

        .total.grand dd {
            font-size: 18px;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10mm;
            margin-top: 12mm;
        }

        .signature {
            padding-top: 6px;
            border-top: 1px solid var(--ink);
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
            background: var(--brand);
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

            .sheet > * {
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
        <p class="invoice-tag">
            <span class="label">{{ __('invoices.purchase_print.invoice') }}</span>
            <span class="name"><bdi>{{ $invoice->name }}</bdi></span>
        </p>
    </header>

    <dl class="facts">
        <div class="fact">
            <dt class="label">{{ __('invoices.purchase_print.supplier') }}</dt>
            <dd>
                <bdi>{{ $supplier?->name_ar ?? '—' }}</bdi>
                @if (filled($supplier?->name_zh))
                    <span class="secondary"><bdi>{{ $supplier->name_zh }}</bdi></span>
                @endif
            </dd>
        </div>
        <div class="fact">
            <dt class="label">{{ __('invoices.purchase_print.category') }}</dt>
            <dd><bdi>{{ $invoice->category?->name ?? '—' }}</bdi></dd>
        </div>
        <div class="fact">
            <dt class="label">{{ __('invoices.purchase_print.date') }}</dt>
            <dd><span class="num">{{ $invoice->date?->format('Y-m-d') ?? '—' }}</span></dd>
        </div>
        <div class="fact">
            <dt class="label">{{ __('invoices.purchase_print.container') }}</dt>
            <dd><bdi>{{ $invoice->container?->displayName() ?? '—' }}</bdi></dd>
        </div>
        <div class="fact">
            <dt class="label">{{ __('invoices.purchase_print.lines_count') }}</dt>
            <dd><span class="num">{{ $lines->count() }}</span></dd>
        </div>
    </dl>

    <p class="how-to">{{ __('invoices.purchase_print.how_to') }}</p>

    <section class="cards">
        @foreach ($lines as $line)
            @php
                $groups = $line->groups->sortBy(fn ($group) => $group->lensPower === null ? -INF : (float) $group->lensPower->value);
            @endphp
            <article class="card">
                @if (filled($line->item?->image))
                    <img class="picture" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($line->item->image) }}" alt="">
                @else
                    <span class="no-picture">{{ __('invoices.purchase_print.no_image') }}</span>
                @endif

                <div class="card-body">
                    <div class="card-head">
                        <span class="index num">{{ $loop->iteration }}</span>
                        <h2 class="item-name"><bdi>{{ $line->item?->name ?? $line->specificDescription?->name }}</bdi></h2>

                        <div class="prices">
                            <p class="price-strip yuan">
                                <span class="tag">{{ __('invoices.purchase_print.yuan') }}</span>
                                <span class="pairs">
                                    <span class="pair"><span class="label">{{ __('invoices.purchase_print.piece') }}</span>@if ($line->unit_cost === null)<b class="none">—</b>@else<b class="num money"><span class="currency">{{ $currency }}</span>{{ $money($line->unit_cost) }}</b>@endif</span>
                                </span>
                            </p>
                            <p class="price-strip dinar">
                                <span class="tag">{{ __('invoices.purchase_print.landed') }}</span>
                                <span class="pairs">
                                    <span class="pair"><span class="label">{{ __('invoices.purchase_print.piece') }}</span>@if ($line->final_unit_cost_iqd === null)<b class="none">—</b>@else<b class="num money"><span class="currency">{{ $dinar }}</span>{{ $dinars($line->final_unit_cost_iqd) }}</b>@endif</span>
                                    <span class="pair"><span class="label">{{ __('invoices.purchase_print.dozen') }}</span>@if ($line->final_unit_cost_iqd === null)<b class="none">—</b>@else<b class="num money"><span class="currency">{{ $dinar }}</span>{{ $dinars($line->final_unit_cost_iqd * $dozen) }}</b>@endif</span>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="details">
                        @if ($line->colors->isNotEmpty())
                            <p class="detail">
                                <span class="label">{{ __('invoices.purchase_print.packet_colors') }}</span>
                                @foreach ($line->colors as $color)
                                    <span class="chip"><bdi>{{ $color->color?->name }}</bdi></span>
                                @endforeach
                            </p>
                        @endif
                        <p class="detail">
                            <span class="label">{{ __('invoices.purchase_print.powers') }}</span>
                            @forelse ($groups as $group)
                                <span class="power"><span class="value num">{{ $group->lensPower?->value ?? __('invoices.purchase_print.no_power') }}</span><span class="packets num">{{ $number($group->boxes_count) }}</span></span>
                            @empty
                                <span class="power"><span class="value">{{ __('invoices.purchase_print.no_power') }}</span><span class="packets num">{{ $number($line->boxes_count) }}</span></span>
                            @endforelse
                        </p>
                    </div>
                </div>
            </article>
        @endforeach
    </section>

    <section class="closing">
        <dl class="totals">
            <div class="total">
                <dt class="label">{{ __('invoices.purchase_print.lines_count') }}</dt>
                <dd><span class="num">{{ $lines->count() }}</span></dd>
            </div>
            <div class="total">
                <dt class="label">{{ __('invoices.purchase_print.total_packets') }}</dt>
                <dd><span class="num">{{ $number((float) $lines->sum('boxes_count')) }}</span></dd>
            </div>
            <div class="total">
                <dt class="label">{{ __('invoices.purchase_print.total_cartons') }}</dt>
                <dd><span class="num">{{ $number((float) $lines->sum('cartons_count')) }}</span></dd>
            </div>
            <div class="total grand">
                <dt class="label">{{ __('invoices.purchase_print.grand_total') }}</dt>
                <dd><span class="num money"><span class="currency">{{ $currency }}</span>{{ $money((float) $lines->sum('price')) }}</span></dd>
            </div>
        </dl>

        <div class="signatures">
            <p class="signature label">{{ __('invoices.purchase_print.received_by') }}</p>
            <p class="signature label">{{ __('invoices.purchase_print.signature') }}</p>
            <p class="signature label">{{ __('invoices.purchase_print.received_on') }}</p>
        </div>
    </section>
</main>
</body>
</html>
