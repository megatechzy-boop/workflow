{{-- Printable A4 document (invoice / receipt). Own styles so the PDF looks the same in light and dark mode. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    @vite(['resources/js/app.js'])
    <style>
        body { margin: 0; background: #eef1f4; font-family: Helvetica, Arial, sans-serif; color: #1f2937; }
        .bar { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; padding: 10px; background: #fff; border-bottom: 1px solid #e5e7eb; }
        .bar a, .bar button { font: 600 14px Helvetica, Arial, sans-serif; padding: 9px 16px; border-radius: 10px; border: 1px solid #d1d5db; background: #fff; color: #374151; text-decoration: none; cursor: pointer; }
        .bar .primary { background: #0A7A6C; border-color: #0A7A6C; color: #fff; }
        .wrap { overflow-x: auto; padding: 20px 10px; }
        .doc { width: 794px; margin: 0 auto; background: #fff; padding: 40px 44px; box-sizing: border-box; font-size: 12px; line-height: 1.45; }
        .doc h1 { margin: 0; font-size: 26px; color: #0A7A6C; }
        .row { display: flex; justify-content: space-between; gap: 24px; }
        .muted { color: #6b7280; }
        .box { flex: 1; background: #f3f8f7; border-radius: 6px; padding: 12px 14px; }
        .box b { display: block; color: #0A7A6C; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th { background: #0A7A6C; color: #fff; text-align: left; padding: 8px; font-weight: 600; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .r { text-align: right; }
        .total { font-size: 15px; font-weight: 700; }
        .stamp { display: inline-block; padding: 3px 10px; border-radius: 4px; font-weight: 700; font-size: 11px; }
        .paid { background: #dcfce7; color: #166534; }
        .unpaid { background: #fef3c7; color: #92400e; }
        @media print { .bar { display: none; } body { background: #fff; } .wrap { padding: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <a href="{{ route('admin.billing.index', ['month' => ($backMonth ?? now())->format('Y-m')]) }}">Back</a>
        <button type="button" class="primary" onclick="this.disabled = true; downloadDocPdf(document.getElementById('doc'), @js($filename)).finally(() => this.disabled = false)">Download PDF</button>
        <button type="button" onclick="print()">Print</button>
    </div>
    <div class="wrap">
        <div class="doc" id="doc">
            @yield('doc')
        </div>
    </div>
</body>
</html>
