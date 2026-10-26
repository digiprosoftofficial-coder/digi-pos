<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $sale->invoice_no }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0; font-size: 20px; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 6px 8px; text-align: left; }
        thead th { border-bottom: 2px solid #333; }
        tbody td { border-bottom: 1px solid #ddd; }
        .right { text-align: right; }
        .totals { margin-top: 12px; width: 40%; float: right; }
        .totals td { padding: 4px 8px; }
        .grand { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
        .foot { clear: both; text-align: center; margin-top: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $store->name }}</h1>
        @if($store->address)<div class="muted">{{ $store->address }}</div>@endif
        @if($store->phone)<div class="muted">{{ $store->phone }}</div>@endif
        @if($store->receipt_header)<div>{{ $store->receipt_header }}</div>@endif
    </div>

    <div>
        <strong>Invoice:</strong> {{ $sale->invoice_no }}<br>
        <strong>Date:</strong> {{ optional($sale->sold_at)->format('d M Y, h:i A') }}<br>
        <strong>Customer:</strong> {{ $sale->customer->name ?? 'Walk-in Customer' }}<br>
        <strong>Served by:</strong> {{ $sale->user->name ?? '-' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                    <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format($sale->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">-{{ number_format($sale->discount, 2) }}</td></tr>
        <tr><td>Tax</td><td class="right">{{ number_format($sale->tax, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $store->currency_symbol }}{{ number_format($sale->total, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format($sale->paid, 2) }}</td></tr>
        <tr><td>Change</td><td class="right">{{ number_format($sale->change, 2) }}</td></tr>
        @if($sale->due > 0)<tr><td>Due</td><td class="right">{{ number_format($sale->due, 2) }}</td></tr>@endif
    </table>

    <div class="foot">
        {{ $store->receipt_footer ?? 'Thank you for your purchase!' }}
    </div>
</body>
</html>
