@extends('layouts.app')

@section('title', $document->order_no)
@section('pretitle', 'Perintah Produksi')

@section('actions')
    @if($document->isDraft())
        @can('production-order.post')
            <x-action-form :action="route('production-orders.release', $document)" label="Rilis" icon="ti ti-player-play"
                           class="btn btn-primary" confirm="Rilis perintah produksi ini ke lantai produksi?" />
        @endcan
    @endif

    @if($document->canComplete())
        @can('production-order.post')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#complete-modal">
                <i class="ti ti-check me-1"></i> Selesaikan Produksi
            </button>
        @endcan
    @endif

    @if($document->status !== 'cancelled')
        @can('production-order.post')
            <x-action-form :action="route('production-orders.cancel', $document)" label="Batalkan" icon="ti ti-x"
                           class="btn btn-outline-danger"
                           confirm="Batalkan perintah produksi ini? Jika sudah selesai, stok dan jurnal akan dibalik." />
        @endcan
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Kebutuhan Bahan" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="width:2.5rem">#</th><th>Komponen</th>
                            <th class="text-num">Rencana</th><th class="text-num">Terpakai</th>
                            <th class="text-num">Stok Tersedia</th><th class="text-num">HPP</th><th class="text-num">Biaya</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($document->items as $index => $item)
                            @php $available = (float) ($availability[$item->id] ?? 0); @endphp
                            <tr>
                                <td class="text-secondary">{{ $index + 1 }}</td>
                                <td>
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }} · {{ $item->product?->uom?->code }}</div>
                                </td>
                                <td class="text-num">{{ fnum($item->planned_qty) }}</td>
                                <td class="text-num fw-bold">{{ fnum($item->consumed_qty) }}</td>
                                <td class="text-num {{ $available < (float) $item->planned_qty ? 'text-danger fw-bold' : '' }}">
                                    {{ fnum($available) }}
                                </td>
                                <td class="text-num text-secondary">{{ rupiah($item->unit_cost, 2) }}</td>
                                <td class="text-num">{{ rupiah($item->cost(), 2) }}</td>
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

            @include('partials.doc-journals', ['journals' => $document->journals])
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Produksi">
                <dl class="row mb-0">
                    <dt class="col-6 text-secondary">Status</dt>
                    <dd class="col-6"><x-status :value="$document->status" /></dd>
                    <dt class="col-6 text-secondary">Tanggal</dt>
                    <dd class="col-6">{{ fdate($document->date) }}</dd>
                    <dt class="col-6 text-secondary">Target Selesai</dt>
                    <dd class="col-6">{{ fdate($document->due_date) }}</dd>
                    <dt class="col-6 text-secondary">Produk</dt>
                    <dd class="col-6"><a href="{{ route('products.show', $document->product_id) }}">{{ $document->product?->name }}</a></dd>
                    <dt class="col-6 text-secondary">BOM</dt>
                    <dd class="col-6">
                        @if($document->bom)<a href="{{ route('boms.show', $document->bom_id) }}">{{ $document->bom->bom_no }}</a>@else — @endif
                    </dd>
                    <dt class="col-6 text-secondary">Gudang</dt>
                    <dd class="col-6">{{ $document->warehouse?->name }}</dd>
                    <dt class="col-6 text-secondary">Rencana</dt>
                    <dd class="col-6">{{ fnum($document->quantity) }} {{ $document->product?->uom?->code }}</dd>
                    <dt class="col-6 text-secondary">Hasil</dt>
                    <dd class="col-6 fw-bold">{{ fnum($document->produced_qty) }}</dd>
                </dl>

                <hr>

                <dl class="row mb-0">
                    <dt class="col-6 text-secondary">Biaya Bahan</dt>
                    <dd class="col-6 text-end">{{ rupiah($document->material_cost) }}</dd>
                    <dt class="col-6 text-secondary">Overhead</dt>
                    <dd class="col-6 text-end">{{ rupiah($document->overhead_cost) }}</dd>
                    <dt class="col-6 fw-bold">Total Biaya</dt>
                    <dd class="col-6 text-end fw-bold">{{ rupiah($document->total_cost) }}</dd>
                    <dt class="col-6 text-secondary">HPP per Unit</dt>
                    <dd class="col-6 text-end">{{ rupiah($document->unitCost(), 2) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection

@push('modals')
    @if($document->canComplete())
        <div class="modal fade" id="complete-modal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('production-orders.complete', $document) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Selesaikan Produksi {{ $document->order_no }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label required" for="produced_qty">Jumlah Hasil Produksi</label>
                                <input type="number" step="0.0001" min="0.0001" name="produced_qty" id="produced_qty"
                                       value="{{ $document->quantity }}" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="overhead_cost">Biaya Overhead</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="number" step="0.01" min="0" name="overhead_cost" id="overhead_cost"
                                           value="0" class="form-control">
                                </div>
                            </div>
                        </div>

                        <h4>Pemakaian Bahan Aktual</h4>
                        <table class="table table-sm table-vcenter">
                            <thead><tr><th>Komponen</th><th class="text-num">Rencana</th><th style="width:10rem" class="text-end">Terpakai</th></tr></thead>
                            <tbody>
                            @foreach($document->items as $item)
                                <tr>
                                    <td>{{ $item->product?->name }}</td>
                                    <td class="text-num text-secondary">{{ fnum($item->planned_qty) }}</td>
                                    <td>
                                        <input type="number" step="0.0001" min="0" class="form-control text-end"
                                               name="consumed[{{ $item->id }}]" value="{{ (float) $item->planned_qty }}">
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <p class="text-secondary small mb-0">
                            Bahan akan dikeluarkan dari stok gudang {{ $document->warehouse?->name }} dan barang jadi
                            diterima dengan HPP = (biaya bahan + overhead) ÷ jumlah hasil.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Selesaikan Produksi</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endpush
