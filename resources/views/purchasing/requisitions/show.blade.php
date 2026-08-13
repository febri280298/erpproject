@extends('layouts.app')

@section('title', $document->pr_no)
@section('pretitle', 'Permintaan Pembelian')

@section('actions')
    @if($document->isDraft())
        @can('purchase-requisition.edit')
            <a href="{{ route('purchase-requisitions.edit', $document) }}" class="btn">
                <i class="ti ti-edit me-1"></i> Ubah
            </a>
            <x-action-form :action="route('purchase-requisitions.submit', $document)" label="Ajukan" icon="ti ti-send"
                           class="btn btn-primary" confirm="Ajukan permintaan ini untuk persetujuan?" />
        @endcan
    @endif

    @if($document->status === 'submitted')
        @can('purchase-requisition.approve')
            <x-action-form :action="route('purchase-requisitions.approve', $document)" label="Setujui" icon="ti ti-check"
                           class="btn btn-primary" confirm="Setujui permintaan pembelian ini?" />
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject-modal">
                <i class="ti ti-x me-1"></i> Tolak
            </button>
        @endcan
    @endif

    @if($document->canCreatePo())
        @can('purchase-order.create')
            <a href="{{ route('purchase-orders.from-requisition', $document) }}" class="btn btn-primary">
                <i class="ti ti-shopping-cart me-1"></i> Buat Pesanan Pembelian
            </a>
        @endcan
    @endif
@endsection

@section('content')
    @if($document->status === 'rejected' && $document->reject_reason)
        <div class="alert alert-danger">
            <h4 class="alert-title">Permintaan ditolak</h4>
            <p class="mb-0">{{ $document->reject_reason }}</p>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Barang yang Diminta" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="width:2.5rem">#</th><th>Produk</th>
                            <th class="text-num">Diminta</th><th>Satuan</th>
                            <th class="text-num">Sudah Dipesan</th>
                            <th class="text-num">Perkiraan Harga</th>
                            <th class="text-num">Estimasi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($document->items as $index => $item)
                            <tr>
                                <td class="text-secondary">{{ $index + 1 }}</td>
                                <td>
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }}{{ $item->description ? ' · '.$item->description : '' }}</div>
                                </td>
                                <td class="text-num">{{ fnum($item->quantity) }}</td>
                                <td class="text-secondary">{{ $item->product?->uom?->code }}</td>
                                <td class="text-num">{{ fnum($item->ordered_qty) }}</td>
                                <td class="text-num">{{ rupiah($item->unit_price) }}</td>
                                <td class="text-num">{{ rupiah((float) $item->quantity * (float) $item->unit_price) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total Estimasi</td>
                            <td class="text-num">{{ rupiah($document->estimatedTotal()) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                @if($document->notes)
                    <div class="card-body border-top">
                        <strong>Catatan</strong>
                        <div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div>
                    </div>
                @endif
            </x-card>

            @if($document->purchaseOrders->isNotEmpty())
                <x-card title="Pesanan Pembelian Terkait" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>No. PO</th><th>Tanggal</th><th class="text-num">Total</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($document->purchaseOrders as $order)
                                <tr>
                                    <td><a href="{{ route('purchase-orders.show', $order) }}">{{ $order->po_no }}</a></td>
                                    <td>{{ fdate($order->date) }}</td>
                                    <td class="text-num">{{ rupiah($order->total) }}</td>
                                    <td><x-status :value="$order->status" /></td>
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
                    <dt class="col-5 text-secondary">Dibutuhkan</dt>
                    <dd class="col-7">{{ fdate($document->required_date) }}</dd>
                    <dt class="col-5 text-secondary">Departemen</dt>
                    <dd class="col-7">{{ $document->department?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Pemohon</dt>
                    <dd class="col-7">{{ $document->requester?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Disetujui oleh</dt>
                    <dd class="col-7">{{ $document->approver?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Waktu setujui</dt>
                    <dd class="col-7">{{ fdatetime($document->approved_at) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="reject-modal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('purchase-requisitions.reject', $document) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Permintaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label required" for="reject_reason">Alasan penolakan</label>
                    <textarea name="reject_reason" id="reject_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Permintaan</button>
                </div>
            </form>
        </div>
    </div>
@endpush
