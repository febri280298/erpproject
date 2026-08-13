@extends('layouts.app')

@section('title', $document->so_no)
@section('pretitle', 'Pesanan Penjualan')

@section('actions')
    <a href="{{ route('sales-orders.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @if($document->isDraft())
        @can('sales-order.edit')
            <a href="{{ route('sales-orders.edit', $document) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endcan
        @can('sales-order.approve')
            <x-action-form :action="route('sales-orders.approve', $document)" label="Konfirmasi" icon="ti ti-check"
                           class="btn btn-primary" confirm="Konfirmasi pesanan ini? Setelah dikonfirmasi tidak dapat diubah." />
        @endcan
    @endif

    @if($document->canDeliver())
        @can('delivery-order.create')
            <a href="{{ route('delivery-orders.create', ['sales_order_id' => $document->id]) }}" class="btn btn-primary">
                <i class="ti ti-truck me-1"></i> Buat Surat Jalan
            </a>
        @endcan
    @endif

    @if($document->canInvoice())
        @can('sales-invoice.create')
            <a href="{{ route('sales-invoices.from-order', $document) }}" class="btn">
                <i class="ti ti-file-invoice me-1"></i> Buat Faktur
            </a>
        @endcan
    @endif

    @if(in_array($document->status, ['confirmed', 'partial', 'delivered']))
        @can('sales-order.approve')
            <div class="dropdown">
                <button class="btn dropdown-toggle" data-bs-toggle="dropdown">Lainnya</button>
                <div class="dropdown-menu dropdown-menu-end">
                    <x-action-form :action="route('sales-orders.close', $document)" label="Tutup Pesanan" icon="ti ti-lock"
                                   confirm="Tutup pesanan ini?" />
                    <x-action-form :action="route('sales-orders.cancel', $document)" label="Batalkan" icon="ti ti-x"
                                   class="dropdown-item text-danger" confirm="Batalkan pesanan ini?" />
                </div>
            </div>
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Rincian Item" flush>
                @include('partials.doc-items-table', [
                    'items' => $document->items,
                    'extraColumns' => [
                        ['label' => 'Dikirim', 'render' => fn($i) => fnum($i->delivered_qty)],
                        ['label' => 'Sisa', 'render' => fn($i) => '<span class="'.($i->outstandingQty() > 0 ? 'text-orange fw-bold' : 'text-secondary').'">'.fnum($i->outstandingQty()).'</span>'],
                    ],
                ])

                <div class="card-body border-top">
                    <div class="row">
                        <div class="col-md-6">
                            @if($document->notes)
                                <div class="mb-2"><strong>Catatan</strong><div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div></div>
                            @endif
                            @if($document->terms)
                                <div><strong>Syarat & Ketentuan</strong><div class="text-secondary">{!! nl2br(e($document->terms)) !!}</div></div>
                            @endif
                        </div>
                        <div class="col-md-6">@include('partials.doc-totals')</div>
                    </div>
                </div>
            </x-card>

            @if($document->deliveryOrders->isNotEmpty())
                <x-card title="Surat Jalan" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. SJ</th><th>Tanggal</th><th>Kendaraan</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->deliveryOrders as $delivery)
                                <tr>
                                    <td><a href="{{ route('delivery-orders.show', $delivery) }}">{{ $delivery->do_no }}</a></td>
                                    <td>{{ fdate($delivery->date) }}</td>
                                    <td class="text-secondary">{{ $delivery->vehicle_no ?? '—' }}</td>
                                    <td><x-status :value="$delivery->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if($document->invoices->isNotEmpty())
                <x-card title="Faktur Penjualan" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. Faktur</th><th>Tanggal</th><th class="text-num">Total</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->invoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('sales-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
                                    <td>{{ fdate($invoice->date) }}</td>
                                    <td class="text-num">{{ rupiah($invoice->total) }}</td>
                                    <td><x-status :value="$invoice->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Dokumen">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>
                    <dt class="col-5 text-secondary">Rencana Kirim</dt>
                    <dd class="col-7">{{ fdate($document->delivery_date) }}</dd>
                    <dt class="col-5 text-secondary">Pelanggan</dt>
                    <dd class="col-7"><a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->customer?->name }}</a></dd>
                    <dt class="col-5 text-secondary">Gudang</dt>
                    <dd class="col-7">{{ $document->warehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">PO Pelanggan</dt>
                    <dd class="col-7">{{ $document->customer_po_no ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Termin</dt>
                    <dd class="col-7">{{ $document->paymentTerm?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                </dl>

                @if(! $document->isDraft())
                    <hr>
                    <div class="mb-1 d-flex justify-content-between">
                        <span class="text-secondary">Progres pengiriman</span>
                        <span class="fw-bold">{{ fnum($document->deliveredPercent()) }}%</span>
                    </div>
                    <div class="progress progress-sm">
                        <div class="progress-bar bg-green" style="width: {{ min(100, $document->deliveredPercent()) }}%"></div>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
