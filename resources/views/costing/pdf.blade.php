<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Report — InvoFlow</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: white; padding: 20px 24px; margin-bottom: 20px; }
        .header h1 { font-size: 20px; font-weight: 900; letter-spacing: -0.5px; }
        .header .subtitle { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.8; margin-top: 2px; }
        .header .meta { font-size: 10px; opacity: 0.85; margin-top: 6px; }
        .content { padding: 0 24px 24px; }
        .product-block { margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .product-header { background: #fffbeb; border-bottom: 1px solid #fde68a; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; }
        .product-name { font-size: 12px; font-weight: 900; color: #92400e; }
        .product-meta { font-size: 9px; color: #b45309; font-weight: 700; margin-top: 2px; }
        .product-cost { font-size: 14px; font-weight: 900; color: #d97706; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; text-align: left; padding: 7px 10px; font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
        th:last-child, td:last-child { text-align: right; }
        td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 10px; color: #374151; }
        tr:last-child td { border-bottom: none; }
        .no-price { color: #ef4444; font-weight: 700; }
        .tfoot-row td { background: #fffbeb; font-weight: 900; color: #92400e; border-top: 2px solid #fde68a; }
        .grand-total-box { margin-top: 20px; background: linear-gradient(135deg, #f59e0b, #ea580c); color: white; padding: 16px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .grand-total-box .label { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; opacity: 0.85; }
        .grand-total-box .amount { font-size: 22px; font-weight: 900; }
        .footer { margin-top: 24px; text-align: center; font-size: 9px; color: #94a3b8; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h1>💰 Manufacturing Cost Report</h1>
        <div class="subtitle">InvoFlow — Costing Manager</div>
        <div class="meta">Generated: {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; By: {{ Auth::user()->name ?? Auth::user()->username }}</div>
    </div>
    <div class="content">
        @forelse($results as $r)
        <div class="product-block">
            <div class="product-header">
                <div>
                    <div class="product-name">{{ $r['product']->name }}</div>
                    <div class="product-meta">
                        {{ $r['product']->pack_name }} &nbsp;&bull;&nbsp;
                        Qty: {{ number_format($r['quantity'], 2) }} &nbsp;&bull;&nbsp;
                        Purity: {{ number_format($r['purity'] ?? 100, 1) }}% &nbsp;&bull;&nbsp;
                        Formulation: {{ number_format($r['formulation'] ?? 100, 1) }}% &nbsp;&bull;&nbsp;
                        Density: {{ number_format($r['density'] ?? 1.0, 2) }} &nbsp;&bull;&nbsp;
                        Cost/Unit: ₹{{ number_format($r['cost_per_unit'], 2) }}
                    </div>
                </div>
                <div class="product-cost">₹ {{ number_format($r['total_cost'], 2) }}</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Raw Material</th>
                        <th>Item Code</th>
                        <th>UOM</th>
                        <th style="text-align:right">Qty Needed</th>
                        <th style="text-align:right">Unit Price (₹)</th>
                        <th style="text-align:right">Cost (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($r['breakdown'] as $rm)
                    <tr>
                        <td>{{ $rm['rm_name'] }}</td>
                        <td>{{ $rm['item_code'] ?? '—' }}</td>
                        <td>{{ $rm['uom'] ?? '—' }}</td>
                        <td style="text-align:right">{{ number_format($rm['required_qty'], 3) }}</td>
                        <td style="text-align:right">
                            @if($rm['price'] > 0)
                                {{ number_format($rm['price'], 2) }}
                            @else
                                <span class="no-price">No price</span>
                            @endif
                        </td>
                        <td style="text-align:right">
                            @if($rm['price'] > 0)
                                {{ number_format($rm['sub_cost'], 2) }}
                            @else
                                <span class="no-price">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="tfoot-row">
                        <td colspan="5">Total Manufacturing Cost</td>
                        <td>₹ {{ number_format($r['total_cost'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @empty
        <p style="text-align:center;color:#94a3b8;padding:40px;font-weight:700;">No products selected for this report.</p>
        @endforelse

        <div class="grand-total-box">
            <div class="label">Grand Total Manufacturing Cost</div>
            <div class="amount">₹ {{ number_format($grandTotal, 2) }}</div>
        </div>

        <div class="footer">This report was auto-generated by InvoFlow Costing Module &mdash; {{ now()->format('Y') }}</div>
    </div>
</body>
</html>
