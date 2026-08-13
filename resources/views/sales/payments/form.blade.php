@extends('layouts.app')

@section('title', 'Catat Penerimaan Pembayaran')
@section('pretitle', 'Penjualan · ' . $nextNumber)

@section('content')
    @include('partials.payment.form', [
        'routeName' => 'customer-payments',
        'partnerLabel' => 'Pelanggan',
    ])
@endsection
