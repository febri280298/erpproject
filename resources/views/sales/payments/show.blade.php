@extends('layouts.app')

@section('title', $document->payment_no)
@section('pretitle', 'Penerimaan Pembayaran')

@section('actions')
    @if($document->isDraft())
        @can('customer-payment.post')
            <x-action-form :action="route('customer-payments.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Posting penerimaan ini? Piutang akan berkurang dan jurnal kas dibuat." />
        @endcan
    @elseif($document->status === 'posted')
        @can('customer-payment.post')
            <x-action-form :action="route('customer-payments.cancel', $document)" label="Batalkan Posting" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger" confirm="Batalkan posting penerimaan ini?" />
        @endcan
    @endif
@endsection

@section('content')
    @include('partials.payment.show', [
        'routeName' => 'customer-payments',
        'invoiceRoute' => 'sales-invoices',
        'partnerLabel' => 'Pelanggan',
        'partnerRelation' => 'customer',
    ])
@endsection
