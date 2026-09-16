@extends('layouts.app')

@section('title', $document->invoice_no)
@section('pretitle', 'Faktur Penjualan')

@section('actions')
    <a href="{{ route('sales-invoices.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @if($document->isDraft())
        @can('sales-invoice.edit')
            <a href="{{ route('sales-invoices.edit', $document) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endcan
        @can('sales-invoice.post')
            <x-action-form :action="route('sales-invoices.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary" confirm="Posting faktur ini ke buku besar?" />
        @endcan
    @elseif(in_array($document->status, ['posted', 'partial']))
        @can('customer-payment.create')
            <a href="{{ route('customer-payments.create', ['partner_id' => $document->partner_id]) }}" class="btn btn-primary">
                <i class="ti ti-cash me-1"></i> Terima Pembayaran
            </a>
        @endcan
        @can('sales-invoice.post')
            <x-action-form :action="route('sales-invoices.cancel', $document)" label="Batalkan" icon="ti ti-x"
                           class="btn btn-outline-danger" confirm="Batalkan faktur ini? Jurnal akan dibalik." />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Rincian Item" flush>
                @include('partials.doc-items-table', ['items' => $document->items])

                <div class="card-body border-top">
                    <div class="row">
                        <div class="col-md-6">
                            @if($document->notes)
                                <div class="mb-2"><strong>Catatan</strong><div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div></div>
                            @endif
                            @if($document->terms)
                                <div><strong>Syarat Pembayaran</strong><div class="text-secondary">{!! nl2br(e($document->terms)) !!}</div></div>
                            @endif
                        </div>
                        <div class="col-md-6">@include('partials.doc-totals', ['showPaid' => true])</div>
                    </div>
                </div>
            </x-card>

            @if($document->payments->isNotEmpty())
                <x-card title="Riwayat Penerimaan" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. Penerimaan</th><th>Tanggal</th><th>Metode</th><th class="text-num">Jumlah</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->payments as $payment)
                                <tr>
                                    <td><a href="{{ route('customer-payments.show', $payment) }}">{{ $payment->payment_no }}</a></td>
                                    <td>{{ fdate($payment->date) }}</td>
                                    <td class="text-secondary">{{ $payment->methodLabel() }}</td>
                                    <td class="text-num">{{ rupiah($payment->pivot->amount) }}</td>
                                    <td><x-status :value="$payment->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @include('partials.doc-journals', ['journals' => $document->journals])
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Dokumen">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>
                    <dt class="col-5 text-secondary">Jatuh Tempo</dt>
                    <dd class="col-7 {{ $document->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ fdate($document->due_date) }}</dd>
                    <dt class="col-5 text-secondary">Tipe Faktur</dt>
                    <dd class="col-7">
                        <span class="badge bg-{{ $document->typeColor() }}-lt">{{ $document->typeLabel() }}</span>
                    </dd>
                    @if((float) $document->wht_amount > 0)
                        <dt class="col-5 text-secondary">PPh 23 ({{ fnum($document->wht_rate) }}%)</dt>
                        <dd class="col-7 text-danger">({{ rupiah($document->wht_amount) }})</dd>
                        <dt class="col-5 text-secondary">Dibayar Customer</dt>
                        <dd class="col-7 fw-bold">{{ rupiah($document->amountDue()) }}</dd>
                    @endif
                    <dt class="col-5 text-secondary">No. PO Pelanggan</dt>
                    <dd class="col-7">{{ $document->customer_po_no ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Pelanggan</dt>
                    <dd class="col-7"><a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->customer?->name }}</a></dd>
                    <dt class="col-5 text-secondary">Pesanan</dt>
                    <dd class="col-7">
                        @if($document->salesOrder)
                            <a href="{{ route('sales-orders.show', $document->sales_order_id) }}">{{ $document->salesOrder->so_no }}</a>
                        @else — @endif
                    </dd>
                    @if($document->deliveryOrders->isNotEmpty())
                        <dt class="col-5 text-secondary">Surat Jalan</dt>
                        <dd class="col-7">
                            @foreach($document->deliveryOrders as $sj)
                                <div>
                                    <a href="{{ route('delivery-orders.show', $sj) }}">{{ $sj->do_no }}</a>
                                    <span class="text-secondary small">{{ fdate($sj->date) }}</span>
                                </div>
                            @endforeach
                        </dd>
                    @endif
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Diposting</dt>
                    <dd class="col-7">{{ fdatetime($document->posted_at) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
