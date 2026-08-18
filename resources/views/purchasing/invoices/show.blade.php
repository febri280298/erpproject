@extends('layouts.app')

@section('title', $document->invoice_no)
@section('pretitle', 'Faktur Pembelian')

@section('actions')
    <a href="{{ route('purchase-invoices.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @if($document->isDraft())
        @can('purchase-invoice.edit')
            <a href="{{ route('purchase-invoices.edit', $document) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endcan
        @can('purchase-invoice.post')
            <x-action-form :action="route('purchase-invoices.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary" confirm="Posting faktur ini ke buku besar?" />
        @endcan
    @elseif(in_array($document->status, ['posted', 'partial']))
        @can('supplier-payment.create')
            <a href="{{ route('supplier-payments.create', ['partner_id' => $document->partner_id]) }}" class="btn btn-primary">
                <i class="ti ti-cash me-1"></i> Bayar
            </a>
        @endcan
        @can('purchase-invoice.post')
            <x-action-form :action="route('purchase-invoices.cancel', $document)" label="Batalkan" icon="ti ti-x"
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
                                <strong>Catatan</strong>
                                <div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @include('partials.doc-totals', ['showPaid' => true])
                        </div>
                    </div>
                </div>
            </x-card>

            @if($document->payments->isNotEmpty())
                <x-card title="Riwayat Pembayaran" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. Pembayaran</th><th>Tanggal</th><th>Metode</th><th class="text-num">Jumlah</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->payments as $payment)
                                <tr>
                                    <td><a href="{{ route('supplier-payments.show', $payment) }}">{{ $payment->payment_no }}</a></td>
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
                    <dt class="col-5 text-secondary">Pemasok</dt>
                    <dd class="col-7"><a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->supplier?->name }}</a></dd>
                    <dt class="col-5 text-secondary">Faktur Pemasok</dt>
                    <dd class="col-7">{{ $document->supplier_invoice_no ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Pesanan</dt>
                    <dd class="col-7">
                        @if($document->purchaseOrder)
                            <a href="{{ route('purchase-orders.show', $document->purchase_order_id) }}">{{ $document->purchaseOrder->po_no }}</a>
                        @else — @endif
                    </dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
