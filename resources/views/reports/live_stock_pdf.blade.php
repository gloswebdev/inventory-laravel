<!DOCTYPE html>
<html>
<head>
    <title>Live Stock Report</title>
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .product-info { text-align: left; }
        .total-row { background-color: #e9ecef; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Live Stock Report</h2>
        <p>Generated on: {{ now()->format('d M, Y H:i:s') }}</p>
        <p>Unit: {{ $displayUnit === 'kg' ? 'kg / Ltr' : 'Units / Pcs' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" class="product-info">Product Name / Code</th>
                @foreach($branches as $branch)
                    <th colspan="2">{{ $branch->name }}</th>
                @endforeach
                <th colspan="2">Consolidated Total</th>
            </tr>
            <tr>
                @foreach($branches as $branch)
                    <th>Qty</th>
                    <th>Boxes</th>
                @endforeach
                <th>Total Qty</th>
                <th>Total Boxes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reportData as $row)
            <tr>
                <td class="product-info">
                    <strong>{{ $row['product']->name }}</strong><br>
                    <small>{{ $row['product']->item_code }}</small><br>
                    <small style="color: #666; font-style: italic;">Pack: {{ $row['product']->pack_name }} ({{ $row['product']->uom }})</small>
                </td>
                @foreach($branches as $branch)
                    @php $stock = $row['branch_stocks'][$branch->code]; @endphp
                    <td>{{ number_format($stock['qty'], 2) }}</td>
                    <td>{{ number_format($stock['boxes'], 2) }}</td>
                @endforeach
                <td class="total-row">{{ number_format($row['total_qty'], 2) }}</td>
                <td class="total-row">{{ number_format($row['total_boxes'], 1) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
