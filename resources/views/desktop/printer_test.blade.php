<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 4mm; color: #000; }
        h2 { font-size: 16px; margin: 0 0 6px; text-align: center; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        td { padding: 2px 0; }
    </style>
</head>
<body>
    <h2>{{ config('app.name') }}</h2>
    <div style="text-align:center">Printer test page</div>
    <div class="line"></div>
    <table style="width:100%">
        <tr><td>Printer</td><td style="text-align:right">{{ $printer }}</td></tr>
        <tr><td>Printed</td><td style="text-align:right">{{ now()->format('Y-m-d H:i') }}</td></tr>
        <tr><td>Item #1</td><td style="text-align:right">10.00</td></tr>
        <tr><td><strong>Total</strong></td><td style="text-align:right"><strong>10.00</strong></td></tr>
    </table>
    <div class="line"></div>
    <div style="text-align:center">If you can read this, direct printing works.</div>
</body>
</html>
