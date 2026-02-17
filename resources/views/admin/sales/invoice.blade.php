<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $sale->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 10mm;
            size: A4;
        }

        body {
            margin: 20px;
            padding: 20px;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            background: white;
            color: #000;
        }

        .invoice-container {
            width: 100%;
            padding: 0;
            margin: 0;
            background: white;
            box-sizing: border-box;
        }

        /* Global Header Styles */
        .global-header {
            border-bottom: 3px solid #000000;
            padding-bottom: 5px;
            margin-bottom: 25px;
        }

        .global-header table {
            width: 100%;
            border: none;
            margin-bottom: 25px;
        }

        .global-header td {
            vertical-align: middle;
            border: none;
            padding: 0;
        }

        .global-header .logo-section {
            width: 80px;
        }

        .global-header .logo-section img {
            max-height: 80px;
            width: auto;
        }

        .global-header .company-section {
            text-align: center;
            padding: 0 10px;
        }

        .global-header .company-section h2 {
            font-size: 20pt;
            letter-spacing: 1.5px;
            font-weight: bold;
            margin: 0;
            padding: 0;
            line-height: 1.1;
        }

        .global-header .company-section p {
            font-size: 7pt;
            color: #000000;
            font-weight: 600;
            margin: 2px 0 0 0;
            padding: 0;
            line-height: 1.1;
        }

        .global-header .invoice-section {
            width: 250px;
            text-align: right;
        }

        .global-header .invoice-section h3 {
            font-size: 10pt;
            font-weight: bold;
            margin: 0;
            padding: 0;
            line-height: 1.2;
        }

        .global-header .invoice-section h6 {
            font-size: 9pt;
            font-weight: bold;
            color: #666;
            margin: 1px 0 0 0;
            padding: 0;
            line-height: 1.2;
        }

        /* Content Area */
        .print-content {
            min-height: calc(297mm - 200px);
            margin-bottom: 20px;
        }

        /* Global Footer Styles */
        .global-footer {
            margin-top: 100px;
            clear: both;
        }

        .global-footer table {
            width: 100%;
            border: none;
            margin-top: 50px;
            margin-bottom: 10px;
        }

        .global-footer td {
            text-align: center;
            vertical-align: bottom;
            border: none;
            padding: 5px;
        }

        .global-footer .signature-line {
            font-size: 9pt;
            font-weight: bold;
            margin: 0;
            padding: 0;
        }

        .global-footer .signature-label {
            font-size: 9pt;
            font-weight: bold;
            margin: 3px 0;
            padding: 0;
        }

        .global-footer img {
            height: 40px;
            margin: 5px auto 0;
            display: block;
        }

        .global-footer .info-section {
            border-top: 2px solid #000000;
            padding-top: 8px;
            margin-top: 8px;
        }

        .global-footer .info-section p {
            text-align: center;
            font-size: 8pt;
            margin: 2px 0;
            padding: 0;
        }

        .info-row {
            margin-bottom: 15px;
        }

        .info-row table {
            width: 100%;
            font-size: 9pt;
        }

        .info-row td {
            vertical-align: top;
            padding: 5px;
        }

        .customer-info {
            width: 50%;
        }

        .invoice-info {
            width: 50%;
            text-align: right;
        }

        .invoice-info table {
            float: right;
            text-align: left;
        }

        .invoice-info table td {
            padding: 2px 5px;
            font-size: 9pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table {
            margin: 10px 0;
            font-size: 9pt;
        }

        .items-table th {
            background: #e9ecef;
            padding: 8px 6px;
            border: 1px solid #999;
            font-weight: bold;
            text-align: left;
        }

        .items-table td {
            padding: 6px;
            border: 1px solid #999;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .totals-section {
            margin: 15px 0;
            text-align: right;
        }

        .totals-table {
            float: right;
            width: 45%;
            font-size: 9pt;
        }

        .totals-table td {
            padding: 4px 8px;
        }

        .totals-table .total-row td {
            border-top: 1px solid #000;
            font-weight: bold;
            padding-top: 8px;
        }

        .returned-section {
            clear: both;
            margin-top: 20px;
        }

        .returned-section h4 {
            background: #f8f8f8;
            padding: 5px;
            margin-bottom: 10px;
            font-size: 11pt;
        }




        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .fw-bold {
            font-weight: bold;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .pt-3 {
            padding-top: 1rem;
        }
    </style>
</head>

<body>
    <div class="invoice-container">

        <div class="global-header">
            <table>
                <tr>
                    <td class="logo-section">
                        <img src="{{ public_path('images/RNZ.png') }}" alt="Logo">
                    </td>
                    <td class="company-section">
                        <h2>RNZ AUTO PARTS</h2>
                        <p>All type of auto parts</p>
                    </td>
                    <td class="invoice-section">
                        <span style="font-size:10pt; font-weight:bold;"></span>
                        <span style="font-size:9pt; font-weight:bold; color:#666; margin-left:8px;">INVOICE</span>
                    </td>
                </tr>
            </table>
        </div>


        {{-- Customer and Invoice Info --}}
        <div class="info-row">
            <table>
                <tr>
                    <td class="customer-info">
                        <strong>Customer :</strong><br>
                        {{ $sale->customer->name ?? 'Walk-in Customer' }}<br>
                        @if(isset($sale->customer->address) && $sale->customer->address)
                        {{ $sale->customer->address }}<br>
                        @endif
                        Tel: {{ $sale->customer->phone ?? 'N/A' }}
                    </td>
                    <td class="invoice-info">
                        <table>
                            <tr>
                                <td><strong>Invoice #</strong></td>
                                <td>{{ $sale->invoice_number }}</td>
                            </tr>
                            <tr>
                                <td><strong>Sale ID</strong></td>
                                <td>{{ $sale->sale_id }}</td>
                            </tr>
                            <tr>
                                <td><strong>Date</strong></td>
                                <td>{{ $sale->created_at->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Time</strong></td>
                                <td>{{ $sale->created_at->format('H:i') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Payment</strong></td>
                                <td>{{ ucfirst($sale->payment_status) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Items Table --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;" class="text-center">#</th>
                    <th style="width: 15%;">ITEM CODE</th>
                    <th style="width: 40%;">DESCRIPTION</th>
                    <th style="width: 10%;" class="text-center">QTY</th>
                    <th style="width: 15%;" class="text-right">UNIT PRICE</th>
                    <th style="width: 15%;" class="text-right">UNIT DISCOUNT</th>
                    <th style="width: 15%;" class="text-right">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->product_code }}</td>
                    <td>{{ $item->product_name }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-right">Rs.{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">
                        @if($item->discount_per_unit > 0)
                            - Rs.{{ number_format($item->discount_per_unit, 2) }}
                        @else
                            - Rs.0.00
                        @endif
                    </td>
                    <td class="text-right">Rs.{{ number_format(($item->unit_price - $item->discount_per_unit) * $item->quantity, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals-section">
            <table class="totals-table">
                @php
                    $originalSubtotal = $sale->items->sum(function($item) {
                        return $item->unit_price * $item->quantity;
                    });
                    $totalDiscountRs = $originalSubtotal - $sale->total_amount;
                @endphp
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right">Rs.{{ number_format($originalSubtotal, 2) }}</td>
                </tr>
                @if($totalDiscountRs > 0)
                <tr>
                    <td>Discount</td>
                    <td class="text-right">- Rs.{{ number_format($totalDiscountRs, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Grand Total</td>
                    <td class="text-right">Rs.{{ number_format($sale->total_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Paid Amount</td>
                    <td class="text-right">Rs.{{ number_format($sale->paid_amount ?? ($sale->total_amount - $sale->due_amount), 2) }}</td>
                </tr>
                @if($sale->due_amount > 0)
                <tr>
                    <td>Due Amount</td>
                    <td class="text-right">Rs.{{ number_format($sale->due_amount, 2) }}</td>
                </tr>
                @endif
            </table>
        </div>

        {{-- Returned Items --}}
        @if(isset($sale->returns) && count($sale->returns) > 0)
        @php $returnAmount = 0; @endphp
        <div class="returned-section">
            <h4>RETURNED ITEMS</h4>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;" class="text-center">#</th>
                        <th style="width: 35%;">PRODUCT</th>
                        <th style="width: 15%;">CODE</th>
                        <th style="width: 15%;" class="text-center">RETURN QTY</th>
                        <th style="width: 17%;" class="text-right">UNIT PRICE</th>
                        <th style="width: 18%;" class="text-right">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sale->returns as $index => $return)
                    @php $returnAmount += $return->total_amount; @endphp
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td>{{ $return->product->name ?? '-' }}</td>
                        <td>{{ $return->product->code ?? '-' }}</td>
                        <td class="text-center">{{ $return->return_quantity }}</td>
                        <td class="text-right">Rs.{{ number_format($return->selling_price, 2) }}</td>
                        <td class="text-right">Rs.{{ number_format($return->total_amount, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr style="background: #f8f8f8; font-weight: bold;">
                        <td colspan="5" class="text-right" style="padding: 8px;">Return Amount:</td>
                        <td class="text-right" style="padding: 8px;">- Rs.{{ number_format($returnAmount, 2) }}</td>
                    </tr>
                    <tr style="background: #e9ecef; font-weight: bold;">
                        <td colspan="5" class="text-right" style="padding: 8px;">Net Amount:</td>
                        <td class="text-right" style="padding: 8px;">Rs.{{ number_format((($sale->subtotal ?? $sale->total_amount) - ($sale->discount_amount ?? 0) - $returnAmount), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        <div class="global-footer" style="position: absolute; bottom: 5;top: auto; width: 100%;">
            <table>
                <tr>
                    <td>
                        <p class="signature-line"><strong>.............................</strong></p>
                        <p class="signature-label"><strong>Checked By</strong></p>
                        
                    </td>
                    <td>
                        <p class="signature-line"><strong>.............................</strong></p>
                        <p class="signature-label"><strong>Authorized Officer</strong></p>
                        
                    </td>
                    <td>
                        <p class="signature-line"><strong>.............................</strong></p>
                        <p class="signature-label"><strong>Customer Stamp</strong></p>
                        
                    </td>
                </tr>
            </table>
            <div class="info-section">
                <p><strong>ADDRESS:</strong> sample address</p>
                <p><strong>TEL:</strong> (077) 1234567 | <strong>EMAIL:</strong> rnz@gmail.com</p>
                <p style="margin-top: 6px;"><strong>.</strong></p>
            </div>
        </div>
    </div>
</body>

</html>