<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Matrix - {{ $indent->branch_name }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 10px; margin: 0; }
        .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; color: #1e1b4b; text-transform: uppercase; }
        .meta-grid { width: 100%; margin-top: 10px; }
        .meta-grid td { vertical-align: top; }
        .label { font-weight: bold; color: #666; font-size: 9px; text-transform: uppercase; }
        .value { font-weight: bold; color: #111; font-size: 11px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th { background: #f8fafc; color: #475569; text-align: left; padding: 8px 4px; border: 1px solid #e2e8f0; font-size: 8px; text-transform: uppercase; }
        td { padding: 6px 4px; border: 1px solid #e2e8f0; word-wrap: break-word; }
        
        .product-name { font-weight: bold; color: #1e293b; font-size: 9px; }
        .product-pack { color: #64748b; font-size: 7px; text-transform: uppercase; }
        
        .qty-box { font-weight: bold; color: #4f46e5; text-align: right; }
        .stock-val { text-align: center; color: #334155; }
        .target-col { background-color: #f5f3ff; }
        .target-tag { font-size: 7px; font-weight: bold; color: #4f46e5; display: block; margin-top: 2px; }
        
        .footer { margin-top: 30px; font-style: italic; font-size: 8px; text-align: center; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Indent Process Manager</div>
        <table class="meta-grid">
            <tr>
                <td width="33%">
                    <div class="label">Target Branch</div>
                    <div class="value">{{ $indent->branch_name }} ({{ $indent->branch_code }})</div>
                </td>
                <td width="33%" style="text-align: center;">
                    <div class="label">Created By</div>
                    <div class="value">{{ $indent->user->name ?? 'System' }}</div>
                </td>
                <td width="33%" style="text-align: right;">
                    <div class="label">Indent Date</div>
                    <div class="value">{{ date('d M, Y', strtotime($indent->indent_date)) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th width="15%">Product</th>
                <th width="8%" style="text-align: right;">Indent (Box)</th>
                <th width="8%" style="text-align: center;">Entry Stock</th>
                @foreach($branches as $branch)
                <th style="text-align: center;">{{ $branch->name }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($indent->items as $item)
            <tr>
                <td>
                    <div class="product-name">{{ $item->product_name }}</div>
                    <div class="product-pack">{{ $item->product->pack_name ?? '' }}</div>
                </td>
                <td class="qty-box">{{ number_format($item->final_qty_box, 0) }}</td>
                <td class="stock-val">{{ number_format($item->stock_box, 2) }}</td>
                @foreach($branches as $branch)
                <td class="stock-val {{ $branch->code == $indent->branch_code ? 'target-col' : '' }}">
                    {{ number_format($branchStocks[$item->product_id][$branch->code] ?? 0, 1) }}
                    @if($branch->code == $indent->branch_code)
                    <span class="target-tag">TARGET</span>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ date('d-M-Y H:i A') }} | Material Indent Tracking System
    </div>
</body>
</html>
