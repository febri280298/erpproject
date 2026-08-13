@extends('layouts.app')

@section('title', $document->po_no)
@section('pretitle', 'Pesanan Pembelian')

@section('actions')
    <a href="{{ route('purchase-orders.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @if($document->isDraft())
        @can('purchase-order.edit')
            <a href="{{ route('purchase-orders.edit', $document) }}" class="btn">
                <i class="ti ti-edit me-1"></i> Ubah
            </a>
        @endcan
        @can('purchase-order.approve')
            <x-action-form :action="route('purchase-orders.approve', $document)" label="Setujui" icon="ti ti-check"
                           class="btn btn-primary" confirm="Setujui pesanan ini? Setelah disetujui pesanan tidak dapat diubah." />
        @endcan
    @endif

    @if($document->canReceive())
        @can('goods-receipt.create')
            <a href="{{ route('goods-receipts.create', ['purchase_order_id' => $document->id]) }}" class="btn btn-primary">
                <i class="ti ti-package-import me-1"></i> Terima Barang
            </a>
        @endcan
    @endif

    @if($document->canInvoice())
        @can('purchase-invoice.create')
            <a href="{{ route('purchase-invoices.from-order', $document) }}" class="btn">
                <i class="ti ti-file-invoice me-1"></i> Buat Faktur
            </a>
        @endcan
    @endif

    @if(in_array($document->status, ['approved', 'partial', 'received']))
        @can('purchase-order.approve')
            <div class="dropdown">
                <button class="btn dropdown-toggle" data-bs-toggle="dropdown">Lainnya</button>
                <div class="dropdown-menu dropdown-menu-end">
                    <x-action-form :action="route('purchase-orders.close', $document)" label="Tutup Pesanan" icon="ti ti-lock"
                                   confirm="Tutup pesanan ini? Sisa item tidak dapat diterima lagi." />
                    <x-action-form :action="route('purchase-orders.cancel', $document)" label="Batalkan" icon="ti ti-x"
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
                        ['label' => 'Diterima', 'render' => fn($i) => fnum($i->received_qty)],
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
                        <div class="col-md-6">
                            @include('partials.doc-totals')
                        </div>
                    </div>
                </div>
            </x-card>

            @if($document->goodsReceipts->isNotEmpty())
                <x-card title="Penerimaan Barang" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. GRN</th><th>Tanggal</th><th>No. SJ Pemasok</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->goodsReceipts as $receipt)
                                <tr>
                                    <td><a href="{{ route('goods-receipts.show', $receipt) }}">{{ $receipt->grn_no }}</a></td>
                                    <td>{{ fdate($receipt->date) }}</td>
                                    <td class="text-secondary">{{ $receipt->supplier_do_no ?? '—' }}</td>
                                    <td><x-status :value="$receipt->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if($document->invoices->isNotEmpty())
                <x-card title="Faktur Pembelian" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. Faktur</th><th>Tanggal</th><th class="text-num">Total</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->invoices as $invoice)
                                <tr>
                                    <td><a href="{{ route('purchase-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
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
                    <dt class="col-5 text-secondary">Perkiraan Tiba</dt>
                    <dd class="col-7">{{ fdate($document->expected_date) }}</dd>
                    <dt class="col-5 text-secondary">Pemasok</dt>
                    <dd class="col-7"><a href="{{ route('partners.show', $document->partner_id) }}">{{ $document->supplier?->name }}</a></dd>
                    <dt class="col-5 text-secondary">Gudang</dt>
                    <dd class="col-7">{{ $document->warehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">Termin</dt>
                    <dd class="col-7">{{ $document->paymentTerm?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Disetujui oleh</dt>
                    <dd class="col-7">{{ $document->approver?->name ?? '—' }}</dd>
                </dl>

                @if(! $document->isDraft())
                    <hr>
                    <div class="mb-1 d-flex justify-content-between">
                        <span class="text-secondary">Progres penerimaan</span>
                        <span class="fw-bold">{{ fnum($document->receivedPercent()) }}%</span>
                    </div>
                    <div class="progress progress-sm">
                        <div class="progress-bar bg-primary" style="width: {{ min(100, $document->receivedPercent()) }}%"></div>
                    </div>
                @endif
            </x-card>

            @module('purchase_requisition')
                @if($document->requisition)
                    <x-card title="Sumber" class="mt-3">
                        <a href="{{ route('purchase-requisitions.show', $document->requisition) }}">
                            {{ $document->requisition->pr_no }}
                        </a>
                    </x-card>
                @endif
            @endmodule
        </div>
    </div>
@endsection
