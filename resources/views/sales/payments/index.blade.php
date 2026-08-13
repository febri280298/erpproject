@extends('layouts.app')

@section('title', 'Penerimaan Pembayaran')
@section('pretitle', 'Penjualan')

@section('actions')
    @can('customer-payment.create')
        <a href="{{ route('customer-payments.create') }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i> Catat Penerimaan
        </a>
    @endcan
@endsection

@section('content')
    @include('partials.payment.index', [
        'routeName' => 'customer-payments',
        'title' => 'Penerimaan Pembayaran',
        'partnerLabel' => 'Pelanggan',
        'partnerRelation' => 'customer',
    ])
@endsection
