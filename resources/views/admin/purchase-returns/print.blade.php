<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return {{ $purchaseReturn->return_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #222; }
        .wrap { max-width: {{ $paper === 'thermal' ? '340px' : '900px' }}; margin: 0 auto; }
        h1, h2, h3, p { margin: 0; }
        .head { margin-bottom: 14px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; font-size: 12px; }
        th { background: #f6f6f6; }
        .right { text-align: right; }
        .totals { width: {{ $paper === 'thermal' ? '100%' : '320px' }}; margin-left: auto; margin-top: 12px; }
        .small { font-size: 12px; color: #666; }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
            .wrap { max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="no-print" style="margin-bottom: 10px;">
        <button onclick="window.print()">Print</button>
    </div>

    <div class="head">
        <h2>RNZ AUTO PARTS</h2>
        <p class="small">Purchase Return Note</p>
    </div>

    <div class="grid">
        <div>
            <p><strong>Return No:</strong> {{ $purchaseReturn->return_no }}</p>
            <p><strong>Date:</strong> {{ optional($purchaseReturn->return_date)->format('Y-m-d') }}</p>
            <p><strong>Type:</strong> {{ strtoupper(str_replace('_', ' ', $purchaseReturn->return_type)) }}</p>
        </div>
        <div>
            <p><strong>Supplier:</strong> {{ $purchaseReturn->supplier?->name ?? '-' }}</p>
            <p><strong>Invoice:</strong> {{ $purchaseReturn->purchaseOrder?->invoice_number ?: ($purchaseReturn->purchaseOrder?->order_code ?? '-') }}</p>
            <p><strong>Notes:</strong> {{ $purchaseReturn->notes ?: '-' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Discount</th>
                <th class="right">Tax</th>
                <th class="right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseReturn->items as $item)
                <tr>
                    <td>{{ $item->product?->name ?? '-' }} @if($item->variant_value) ({{ $item->variant_value }}) @endif</td>
                    <td class="right">{{ number_format($item->return_qty, 3) }}</td>
                    <td class="right">{{ number_format($item->rate, 2) }}</td>
                    <td class="right">{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><th>Subtotal</th><td class="right">{{ number_format($purchaseReturn->subtotal, 2) }}</td></tr>
        <tr><th>Discount</th><td class="right">{{ number_format($purchaseReturn->overall_discount, 2) }}</td></tr>
        <tr><th>Tax</th><td class="right">{{ number_format($purchaseReturn->tax_total, 2) }}</td></tr>
        <tr><th>Grand Total</th><td class="right"><strong>{{ number_format($purchaseReturn->grand_total, 2) }}</strong></td></tr>
    </table>
</div>
</body>
</html>
