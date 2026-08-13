@extends('layouts.app')

@section('title', $document->grn_no)
@section('pretitle', 'Penerimaan Barang')

@section('actions')
    <a href="{{ route('goods-receipts.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @if($document->isDraft())
        @can('goods-receipt.post')
            <x-action-form :action="route('goods-receipts.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Posting penerimaan ini? Stok akan bertambah dan jurnal persediaan dibuat." />
        @endcan
    @elseif($document->status === 'posted')
        @can('goods-receipt.post')
            <x-action-form :action="route('goods-receipts.cancel', $document)" label="Batalkan Posting" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger"
                           confirm="Batalkan posting? Stok akan dikembalikan dan jurnal dibalik." />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Barang Diterima" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="width:2.5rem">#</th><th>Produk</th>
                            <th class="text-num">Qty</th><th>Satuan</th>
                            <th class="text-num">Harga Satuan</th><th class="text-num">Nilai</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($document->items as $index => $item)
                            <tr>
                                <td class="text-secondary">{{ $index + 1 }}</td>
                                <td>
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }}</div>
                                </td>
                                <td class="text-num fw-bold">{{ fnum($item->quantity) }}</td>
                                <td class="text-secondary">{{ $item->product?->uom?->code }}</td>
                                <td class="text-num">{{ rupiah($item->unit_price, 2) }}</td>
                                <td class="text-num">{{ rupiah($item->lineTotal(), 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="2">Total</td>
                            <td class="text-num">{{ fnum($document->totalQuantity()) }}</td>
                            <td colspan="2"></td>
                            <td class="text-num">{{ rupiah($document->totalValue(), 2) }}</td>
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

            @include('partials.doc-journals', ['journals' => $document->journals])
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Dokumen">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>
                    <dt class="col-5 text-secondary">Pesanan</dt>
                    <dd class="col-7">
                        @if($document->purchaseOrder)
                            <a href="{{ route('purchase-orders.show', $document->purchase_order_id) }}">{{ $document->purchaseOrder->po_no }}</a>
                        @else — @endif
                    </dd>
                    <dt class="col-5 text-secondary">Pemasok</dt>
                    <dd class="col-7">{{ $document->supplier?->name }}</dd>
                    <dt class="col-5 text-secondary">Gudang</dt>
                    <dd class="col-7">{{ $document->warehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">SJ Pemasok</dt>
                    <dd class="col-7">{{ $document->supplier_do_no ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Diposting</dt>
                    <dd class="col-7">{{ fdatetime($document->posted_at) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
