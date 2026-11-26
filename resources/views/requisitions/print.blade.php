@extends('layouts.print')
@section('title', 'RIS '.$requisition->ris_no)

@section('content')
    @include('requisitions._sheet')
@endsection
