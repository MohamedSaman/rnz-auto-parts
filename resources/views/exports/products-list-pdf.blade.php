<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products List Export</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        h1 {
            margin: 0;
            font-size: 18px;
        }

        .meta {
            margin-top: 4px;
            margin-bottom: 16px;
            color: #4b5563;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px 7px;
            vertical-align: middle;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 10px;
        }

        td.numeric {
            text-align: right;
            white-space: nowrap;
        }

        td.center {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Products List</h1>
    <div class="meta">Generated: {{ $generatedAt->format('Y-m-d H:i:s') }}</div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Item Name</th>
                <th>Supplier Price</th>
                <th>Wholesale Price</th>
                <th>Distributor Price</th>
                <th>Retail Price</th>
                <th>Stock</th>
                <th>Damage</th>
                <th>Rack Num</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->product_name }}</td>
                    <td class="numeric">{{ number_format((float) $product->supplier_price, 2) }}</td>
                    <td class="numeric">{{ number_format((float) $product->wholesale_price, 2) }}</td>
                    <td class="numeric">{{ number_format((float) $product->distributor_price, 2) }}</td>
                    <td class="numeric">{{ number_format((float) $product->retail_price, 2) }}</td>
                    <td class="numeric">{{ (int) $product->available_stock }}</td>
                    <td class="numeric">{{ (int) $product->damage_stock }}</td>
                    <td class="center">{{ $product->rack_number ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center">No products found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
