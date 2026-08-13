@extends('layouts.app')

@section('title', $document->transfer_no)
@section('pretitle', 'Transfer Gudang')

@section('actions')
    @if($document->isDraft())
        @can('stock-transfer.edit')
            <a href="{{ route('stock-transfers.edit', $document) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endcan
        @can('stock-transfer.post')
            <x-action-form :action="route('stock-transfers.post', $document)" label="Posting" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Posting transfer ini? Stok akan berpindah antar gudang." />
        @endcan
    @elseif($document->status === 'posted')
        @can('stock-transfer.post')
            <x-action-form :action="route('stock-transfers.cancel', $document)" label="Batalkan Posting" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger" confirm="Batalkan posting transfer ini?" />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Barang yang Dipindahkan" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr><th style="width:2.5rem">#</th><th>Produk</th><th class="text-num">Qty</th><th>Satuan</th><th>Catatan</th></tr>
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
                                <td class="text-secondary">{{ $item->notes ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if($document->notes)
                    <div class="card-body border-top">
                        <strong>Catatan</strong>
                        <div class="text-secondary">{!! nl2br(e($document->notes)) !!}</div>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Dokumen">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$document->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($document->date) }}</dd>
                    <dt class="col-5 text-secondary">Dari Gudang</dt>
                    <dd class="col-7">{{ $document->fromWarehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">Ke Gudang</dt>
                    <dd class="col-7">{{ $document->toWarehouse?->name }}</dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $document->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Diposting</dt>
                    <dd class="col-7">{{ fdatetime($document->posted_at) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
