@extends('layouts.app')

@section('title', 'Catat Pembayaran Pemasok')
@section('pretitle', 'Pembelian · ' . $nextNumber)

@section('content')
    @include('partials.payment.form', [
        'routeName' => 'supplier-payments',
        'partnerLabel' => 'Pemasok',
    ])
@endsection
