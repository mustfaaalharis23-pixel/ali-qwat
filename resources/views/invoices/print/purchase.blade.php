{{--
    The checking sheet of one purchase invoice. It is held in the hand of the employee who receives the goods from the
    factory, on a clipboard, while they open the cartons and count. So the lines are one table, like the customer's invoice,
    with one row for every line: a small picture, the item's name and the colors of its packet; a small table of its powers
    (in hundreds, as the factories write them) with the packets and dozens of each; then the price of one piece in yuan and
    the landed price of a piece and a dozen in dinars, with the transport, each with its currency sign so yuan and dinars are
    never mixed up at a glance. The totals of the packets, the cartons, and the price close the sheet, with room for whoever
    received the goods to sign. It prints in black and white as well as in color, runs over pages with the head of the table
    on every page and no row cut in two, and follows the language of the panel.
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

        /* One row for every line, like the customer's invoice: the item, a table of its powers, and its prices */

        .lines {
            width: 100%;
            margin-top: 5mm;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .col-index {
            width: 5%;
        }

        .col-powers {
            width: 34%;
        }

        .col-yuan {
            width: 12%;
        }

        .col-landed {
            width: 19%;
        }

        .lines > thead {
            display: table-header-group;
        }

        .lines > tbody > tr {
            break-inside: avoid;
        }

        .lines > thead > tr > th {
            padding: 7px 8px;
            border-bottom: 1px solid var(--ink);
            background: var(--brand-wash);
            color: var(--brand-deep);
            font-size: 11px;
            font-weight: 600;
            line-height: 1.4;
            text-align: start;
            vertical-align: bottom;
        }

        .lines > thead > tr > th.center {
            text-align: center;
        }

        .lines > thead > tr > th .note {
            display: block;
            font-size: 10px;
            font-weight: 400;
        }

        .lines > tbody > tr > td {
            padding: 2.5mm 8px;
            border-bottom: 1px solid var(--rule);
            color: var(--ink);
            vertical-align: middle;
        }

        .lines > tbody > tr:last-child > td {
            border-bottom-color: var(--ink);
        }

        .lines > tbody > tr > td.index {
            color: var(--label);
            font-size: 11px;
        }

        .item-cell {
            display: flex;
            align-items: center;
            gap: 3mm;
        }

        .picture,
        .no-picture {
            flex-shrink: 0;
            width: 14mm;
            height: 14mm;
            border-radius: 4px;
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
            font-size: 8px;
            text-align: center;
        }

        .item-name {
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .colors {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            margin-top: 3px;
        }

        .price {
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
        }

        .landed {
            display: grid;
            gap: 2px;
        }

        .pair {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 6px;
            white-space: nowrap;
        }

        .pair .label {
            font-size: 10px;
        }

        .pair b {
            color: var(--brand-deep);
            font-size: 12.5px;
        }

        .money .currency {
            color: inherit;
        }

        /* The powers of a row: the packets and dozens of each power */

        .powers {
            width: 100%;
            border: 1px solid var(--rule);
            border-radius: 6px;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            table-layout: fixed;
        }

        .powers th,
        .powers td {
            padding: 1px 6px;
            color: var(--ink);
            font-size: 12px;
            font-weight: 500;
            line-height: 1.5;
            text-align: center;
        }

        .powers th {
            background: var(--desk);
            color: var(--label);
            font-size: 10px;
        }

        .powers td {
            border-top: 1px solid var(--hair);
        }

        .powers th + th,
        .powers td + td {
            border-inline-start: 1px solid var(--hair);
        }

        .chip {
            padding: 0 7px;
            border: 1px solid var(--rule);
            border-radius: 999px;
            color: var(--ink);
            font-size: 10.5px;
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

    <table class="lines">
        <colgroup>
            <col class="col-index">
            <col>
            <col class="col-powers">
            <col class="col-yuan">
            <col class="col-landed">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('invoices.purchase_print.item') }}</th>
                <th class="center">
                    {{ __('invoices.purchase_print.powers_heading') }}
                    <span class="note">{{ __('invoices.purchase_print.powers_heading_note') }}</span>
                </th>
                <th>{{ __('invoices.purchase_print.yuan_piece') }}</th>
                <th>{{ __('invoices.purchase_print.landed_column') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                @php
                    // A row per power, lowest first, with its packets; a line without powers is one row of all its packets.
                    $powerRows = $line->groups->isEmpty()
                        ? collect([['power' => null, 'packets' => (float) $line->boxes_count]])
                        : $line->groups
                            ->sortBy(fn ($group) => $group->lensPower === null ? -INF : (float) $group->lensPower->value)
                            ->map(fn ($group): array => ['power' => $group->lensPower?->inHundreds(), 'packets' => (float) $group->boxes_count]);
                @endphp
                <tr>
                    <td class="index num">{{ $loop->iteration }}</td>
                    <td>
                        <div class="item-cell">
                            @if (filled($line->item?->image))
                                <img class="picture" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($line->item->image) }}" alt="">
                            @else
                                <span class="no-picture">{{ __('invoices.purchase_print.no_image') }}</span>
                            @endif
                            <div>
                                <h2 class="item-name"><bdi>{{ $line->item?->name ?? $line->specificDescription?->name }}</bdi></h2>
                                @if ($line->colors->isNotEmpty())
                                    <p class="colors">
                                        @foreach ($line->colors as $color)
                                            <span class="chip"><bdi>{{ $color->color?->name }}</bdi></span>
                                        @endforeach
                                    </p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        <table class="powers">
                            <thead>
                                <tr>
                                    <th>{{ __('invoices.purchase_print.power') }}</th>
                                    <th>{{ __('invoices.purchase_print.packets') }}</th>
                                    <th>{{ __('invoices.purchase_print.dozens') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($powerRows as $row)
                                    <tr>
                                        <td>@if ($row['power'] === null)<span class="none">{{ __('invoices.purchase_print.no_power') }}</span>@else<span class="num">{{ $row['power'] }}</span>@endif</td>
                                        <td><span class="num">{{ $number($row['packets']) }}</span></td>
                                        <td><span class="num">{{ $number($row['packets'] * (float) $line->pieces_per_packet / $dozen) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </td>
                    <td>@if ($line->unit_cost === null)<span class="none">—</span>@else<b class="price num money"><span class="currency">{{ $currency }}</span>{{ $money($line->unit_cost) }}</b>@endif</td>
                    <td>
                        @if ($line->final_unit_cost_iqd === null)
                            <span class="none">—</span>
                        @else
                            <div class="landed">
                                <span class="pair"><span class="label">{{ __('invoices.purchase_print.piece') }}</span><b class="num money"><span class="currency">{{ $dinar }}</span>{{ $dinars($line->final_unit_cost_iqd) }}</b></span>
                                <span class="pair"><span class="label">{{ __('invoices.purchase_print.dozen') }}</span><b class="num money"><span class="currency">{{ $dinar }}</span>{{ $dinars($line->final_unit_cost_iqd * $dozen) }}</b></span>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

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
