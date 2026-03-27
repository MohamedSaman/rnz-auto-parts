<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales Return {{ $salesReturn->return_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 0; }
        .sheet { margin: 14px auto; padding: 14px; }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #333; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        .text-end { text-align: right; }
        .totals { margin-top: 10px; width: 280px; margin-left: auto; }
        .totals td { border: none; padding: 4px 0; }

        @media print {
            .no-print { display: none; }
            @page { margin: 8mm; size: A4; }
            .sheet { margin: 0; padding: 0; }
        }

        @media print and (max-width: 80mm) {
            @page { size: 80mm auto; margin: 2mm; }
            .sheet { width: 76mm; font-size: 11px; }
            .title { font-size: 14px; }
            table { font-size: 10px; }
        }
    </style>
</head>
<body>
<div class="sheet" style="max-width: {{ $paper === 'thermal' ? '80mm' : '210mm' }};">
    <div class="title">Sales Return Note</div>
    <div class="meta">
        <div>
            <div><strong>Return No:</strong> {{ $salesReturn->return_no }}</div>
            <div><strong>Date:</strong> {{ optional($salesReturn->return_date)->format('Y-m-d') }}</div>
            <div><strong>Invoice:</strong> {{ $salesReturn->sale?->invoice_number }}</div>
        </div>
        <div>
            <div><strong>Customer:</strong> {{ $salesReturn->sale?->customer?->name ?? 'Walk-in' }}</div>
            <div><strong>Refund Type:</strong> {{ strtoupper(str_replace('_', ' ', $salesReturn->refund_type)) }}</div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Product</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Rate</th>
            <th class="text-end">Disc</th>
            <th class="text-end">Tax</th>
            <th class="text-end">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach($salesReturn->items as $item)
            <tr>
                <td>{{ $item->product?->name ?? 'Product' }}{{ $item->variant_value ? ' (' . $item->variant_value . ')' : '' }}</td>
                <td class="text-end">{{ number_format((float) $item->return_qty, 3) }}</td>
                <td class="text-end">{{ number_format((float) $item->rate, 2) }}</td>
                <td class="text-end">{{ number_format((float) $item->discount_amount, 2) }}</td>
                <td class="text-end">{{ number_format((float) $item->tax_amount, 2) }}</td>
                <td class="text-end">{{ number_format((float) $item->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="text-end">{{ number_format((float) $salesReturn->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="text-end">{{ number_format((float) $salesReturn->overall_discount, 2) }}</td></tr>
        <tr><td>Tax</td><td class="text-end">{{ number_format((float) $salesReturn->tax_total, 2) }}</td></tr>
        <tr><td><strong>Grand Total</strong></td><td class="text-end"><strong>{{ number_format((float) $salesReturn->grand_total, 2) }}</strong></td></tr>
    </table>

    <div style="margin-top:10px; font-size:12px;">
        <strong>Notes:</strong> {{ $salesReturn->notes ?: '-' }}
    </div>

    <div class="no-print" style="margin-top: 12px; display:flex; gap:8px;">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('admin.sales-return-print', ['salesReturn' => $salesReturn->id, 'paper' => $paper === 'a4' ? 'thermal' : 'a4']) }}">Switch to {{ $paper === 'a4' ? 'Thermal' : 'A4' }}</a>
    </div>
</div>
</body>
</html>
