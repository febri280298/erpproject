@extends('layouts.app')

@section('title', 'Pembayaran Pemasok')
@section('pretitle', 'Pembelian')

@section('actions')
    @can('supplier-payment.create')
        <a href="{{ route('supplier-payments.create') }}" class="btn btn-primary">
            <i class="ti ti-cash me-1"></i> Catat Pembayaran
        </a>
    @endcan
@endsection

@section('content')
    @include('partials.payment.index', [
        'routeName' => 'supplier-payments',
        'title' => 'Pembayaran Pemasok',
        'partnerLabel' => 'Pemasok',
        'partnerRelation' => 'supplier',
    ])
@endsection
