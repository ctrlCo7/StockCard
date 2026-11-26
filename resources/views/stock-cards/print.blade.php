@extends('layouts.print')
@section('title', 'Stock Card — '.$item->stock_number)
@section('page-style')
    @include('stock-cards._styles')
@endsection

@section('content')
    @include('stock-cards._sheet')
@endsection
