@extends('layouts.app')

@section('title', $document->do_no)
@section('pretitle', 'Surat Jalan')

@section('actions')
    <a href="{{ route('delivery-orders.print', $document) }}" class="btn" target="_blank">
        <i class="ti ti-printer me-1"></i> Cetak
    </a>

    @if($document->isDraft())
        @can('delivery-order.post')
            <x-action-form :action="route('delivery-orders.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Posting surat jalan ini? Stok akan berkurang dan HPP dicatat." />
        @endcan
    @elseif($document->status === 'posted')
        @can('delivery-order.post')
            <x-action-form :action="route('delivery-orders.cancel', $document)" label="Batalkan Posting" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger" confirm="Batalkan posting? Stok akan dikembalikan." />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Barang Dikirim" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr><th style="width:2.5rem">#</th><th>Produk</th><th class="text-num">Qty</th><th>Satuan</th></tr>
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
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="2">Total Kuantitas</td>
                            <td class="text-num">{{ fnum($document->totalQuantity()) }}</td>
                            <td></td>
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
                        @if($document->salesOrder)
                            <a href="{{ route('sales-orders.show', $document->sales_order_id) }}">{{ $document->salesOrder->so_no }}</a>
                        @else — @endif
                    </dd>
                    <dt class="col-5 text-secondary">Pelanggan</dt>
                    <dd class="col-7">{{ $document->customer?->name }}</dd>
                    <dt class="col-5 text-secondary">Gudang</dt>
                    <dd class="col-7">{{ $document->warehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">Pengemudi</dt>
                    <dd class="col-7">{{ $document->driver_name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Kendaraan</dt>
                    <dd class="col-7">{{ $document->vehicle_no ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Diposting</dt>
                    <dd class="col-7">{{ fdatetime($document->posted_at) }}</dd>
                </dl>

                @if($document->shipping_address)
                    <hr>
                    <strong>Alamat Pengiriman</strong>
                    <div class="text-secondary">{!! nl2br(e($document->shipping_address)) !!}</div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
