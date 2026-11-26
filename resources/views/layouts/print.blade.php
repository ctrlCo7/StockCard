<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Print</title>
    @include('partials.form-styles')
    <style>
        body { background: #e8ebf0; font-family: Arial, Helvetica, sans-serif; }
        .sheet { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 14mm 12mm; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        .sheet.landscape { width: 297mm; min-height: 210mm; }
        .toolbar { position: sticky; top: 0; background: #1b2f4e; color: #fff; padding: 8px 14px; display: flex; gap: 8px; align-items: center; font-family: Arial, sans-serif; font-size: 13px; }
        .toolbar button, .toolbar a { background: #fff; color: #1b2f4e; border: 0; border-radius: 4px; padding: 5px 12px; cursor: pointer; text-decoration: none; font-size: 13px; }
        .toolbar .spacer { flex: 1; }
        @media screen and (max-width: 900px) { .sheet, .sheet.landscape { width: auto; min-height: 0; margin: 8px; padding: 12px; } }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet, .sheet.landscape { margin: 0; padding: 0; box-shadow: none; width: auto; min-height: 0; }
            @page { @bottom-right { content: "Page " counter(page) " of " counter(pages); font-size: 8px; color: #555; } }
        }
        @yield('page-style')
    </style>
</head>
<body>
<div class="toolbar">
    <strong>@yield('title')</strong>
    <span class="spacer"></span>
    <button type="button" onclick="window.print()">Print</button>
    <button type="button" onclick="window.close()">Close</button>
</div>
<div class="sheet @yield('sheet-class')">
    @yield('content')
</div>
<script>window.addEventListener('load', function () { if (new URLSearchParams(location.search).has('autoprint')) window.print(); });</script>
</body>
</html>
