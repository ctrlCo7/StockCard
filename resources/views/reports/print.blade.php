@extends('layouts.print')
@section('title', $title)
@section('sheet-class', $landscape ? 'landscape' : '')
@section('page-style')
    @if($landscape) @page { size: A4 landscape; } @endif
@endsection

@section('content')
    @include('reports._document')
@endsection
