<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    <style>
        @page { size: 100mm 150mm; margin: 0; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { width: 100mm; height: 150mm; background: #fff; }

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, "PingFang SC", "Noto Sans CJK SC", "Noto Sans SC", "Microsoft YaHei", sans-serif;
            color: #111827;
            padding: 3mm;
        }

        .label {
            height: 100%;
            border: 0.6mm solid #111827;
            display: flex;
            flex-direction: column;
        }

        .row { display: flex; border-bottom: 0.5mm solid #111827; }
        .row:last-child { border-bottom: 0; }

        .cell { flex: 1; padding: 3mm; text-align: center; }
        .cell + .cell { border-left: 0.5mm solid #111827; }

        .caption { font-size: 2.8mm; font-weight: 700; color: #6b7280; margin-bottom: 1mm; }
        .value { font-size: 5mm; font-weight: 700; word-break: break-word; }

        .header { background: #111827; color: #fff; align-items: center; }
        .header .cell { text-align: left; }
        .header .cell + .cell { border-left-color: #374151; text-align: right; }
        .route { font-size: 5mm; font-weight: 800; }
        .brand { font-size: 6.5mm; font-weight: 800; }
        .subtitle { font-size: 3mm; font-weight: 600; color: #d1d5db; margin-top: 0.5mm; }

        .number { font-size: 13mm; font-weight: 800; letter-spacing: 0.3mm; }

        .footer .cell { display: flex; align-items: center; justify-content: space-between; text-align: left; }
        .footer .value { font-size: 4mm; }
        .phone { font-size: 3.4mm; font-weight: 700; color: #6b7280; }

        .barcode { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .barcode svg { width: 92%; height: 22mm; }
        .barcode-text { font-size: 5mm; font-weight: 700; letter-spacing: 1.5mm; margin-top: 2mm; }
    </style>
</head>
<body>
    <div class="label">
        <div class="row header">
            <div class="cell">
                <div class="route">CN → IQ</div>
            </div>
            <div class="cell">
                <div class="brand">{{ __('invoices.print.brand', [], 'en') }}</div>
                <div class="subtitle">{{ __('invoices.label.subtitle') }}</div>
            </div>
        </div>

        <div class="row">
            <div class="cell">
                <div class="caption">{{ __('invoices.label.number') }}</div>
                <div class="number">{{ $number }}</div>
            </div>
        </div>

        <div class="row">
            <div class="cell">
                <div class="caption">{{ __('invoices.label.invoice') }}</div>
                <div class="value">{{ $invoice->name }}</div>
            </div>
            <div class="cell">
                <div class="caption">{{ __('invoices.label.supplier') }}</div>
                <div class="value">{{ $invoice->supplier?->name_zh ?: $invoice->supplier?->name_ar }}</div>
            </div>
            <div class="cell">
                <div class="caption">{{ __('invoices.label.category') }}</div>
                <div class="value"><bdi>{{ $invoice->category?->name ?? '—' }}</bdi></div>
            </div>
        </div>

        <div class="row">
            <div class="cell">
                <div class="caption">{{ __('invoices.label.items') }}</div>
                <div class="value">{{ $invoice->items->count() }}</div>
            </div>
            <div class="cell">
                <div class="caption">{{ __('invoices.label.cbm') }}</div>
                <div class="value">{{ $cbm }}</div>
            </div>
            <div class="cell">
                <div class="caption">{{ __('invoices.label.cartons') }}</div>
                <div class="value">{{ $cartons }}</div>
            </div>
        </div>

        @if ($powers !== [])
            <div class="row">
                <div class="cell">
                    <div class="caption">{{ __('invoices.label.powers') }}</div>
                    <div class="value">{{ implode(' · ', $powers) }}</div>
                </div>
            </div>
        @endif

        <div class="row footer">
            <div class="cell">
                <span class="caption">{{ __('invoices.label.date') }}</span>
                <span class="value">{{ $invoice->date?->format('Y-m-d') }}</span>
                <span class="phone">{{ $invoice->supplier?->phone }}</span>
            </div>
        </div>

        <div class="row barcode">
            {!! $barcode !!}
            <div class="barcode-text">{{ $number }}</div>
        </div>
    </div>
</body>
</html>
