@extends('layouts.app')

@section('title', $document->payment_no)
@section('pretitle', 'Pembayaran Pemasok')

@section('actions')
    @if($document->isDraft())
        @can('supplier-payment.post')
            <x-action-form :action="route('supplier-payments.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Posting pembayaran ini? Faktur terkait akan dikurangi dan jurnal kas dibuat." />
        @endcan
    @elseif($document->status === 'posted')
        @can('supplier-payment.post')
            <x-action-form :action="route('supplier-payments.cancel', $document)" label="Batalkan Posting" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger" confirm="Batalkan posting pembayaran ini?" />
        @endcan
    @endif
@endsection

@section('content')
    @include('partials.payment.show', [
        'routeName' => 'supplier-payments',
        'invoiceRoute' => 'purchase-invoices',
        'partnerLabel' => 'Pemasok',
        'partnerRelation' => 'supplier',
    ])
@endsection
