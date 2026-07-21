<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 6px; }
        .muted { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; }
        th { background: #eee; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="muted">{{ $company?->name ?? 'Continental Wholesale' }} — {{ now()->format('n/j/Y g:i A') }}</p>
    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Description</th>
                <th>UPC</th>
                <th>UOM</th>
                <th class="right">List Price</th>
                <th class="right">MSRP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->item_code }}</td>
                    <td>{{ $item->description }}</td>
                    <td>{{ $item->primary_upc }}</td>
                    <td>{{ $item->unit_of_measure }}</td>
                    <td class="right">${{ number_format((float) $item->list_price, 2) }}</td>
                    <td class="right">${{ number_format((float) $item->msrp, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
