<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Stock Card — {{ $item->stock_number }}</title>
    @include('partials.form-styles')
    <style>@include('stock-cards._styles')</style>
</head>
<body>
    @include('stock-cards._sheet')
</body>
</html>
