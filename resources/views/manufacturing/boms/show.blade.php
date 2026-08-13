@extends('layouts.app')

@section('title', $bom->bom_no)
@section('pretitle', 'Bill of Materials')

@section('actions')
    @can('bom.edit')
        <a href="{{ route('boms.edit', $bom) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
    @endcan
    @can('production-order.create')
        <a href="{{ route('production-orders.create', ['bom_id' => $bom->id]) }}" class="btn btn-primary">
            <i class="ti ti-tools me-1"></i> Buat Perintah Produksi
        </a>
    @endcan
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Komponen" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th style="width:2.5rem">#</th><th>Komponen</th>
                            <th class="text-num">Qty</th><th class="text-num">Waste</th>
                            <th class="text-num">Qty Efektif</th><th class="text-num">Harga</th>
                            <th class="text-num">Biaya</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($bom->items as $index => $item)
                            <tr>
                                <td class="text-secondary">{{ $index + 1 }}</td>
                                <td>
                                    <div>{{ $item->product?->name }}</div>
                                    <div class="text-secondary small">{{ $item->product?->sku }} · {{ $item->product?->uom?->code }}</div>
                                </td>
                                <td class="text-num">{{ fnum($item->quantity) }}</td>
                                <td class="text-num text-secondary">{{ fnum($item->waste_percent) }}%</td>
                                <td class="text-num fw-bold">{{ fnum($item->effectiveQty()) }}</td>
                                <td class="text-num">{{ rupiah($item->product?->purchase_price) }}</td>
                                <td class="text-num">{{ rupiah($item->effectiveQty() * (float) ($item->product?->purchase_price ?? 0)) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Total Biaya Bahan per Batch</td>
                            <td class="text-num">{{ rupiah($bom->materialCost()) }}</td>
                        </tr>
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">Biaya Bahan per Unit</td>
                            <td class="text-num fs-4">{{ rupiah($bom->costPerUnit()) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                @if($bom->notes)
                    <div class="card-body border-top">
                        <strong>Catatan Proses</strong>
                        <div class="text-secondary">{!! nl2br(e($bom->notes)) !!}</div>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi BOM">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Nomor</dt>
                    <dd class="col-7 fw-bold">{{ $bom->bom_no }}</dd>
                    <dt class="col-5 text-secondary">Nama</dt>
                    <dd class="col-7">{{ $bom->name }}</dd>
                    <dt class="col-5 text-secondary">Produk Jadi</dt>
                    <dd class="col-7"><a href="{{ route('products.show', $bom->product_id) }}">{{ $bom->product?->name }}</a></dd>
                    <dt class="col-5 text-secondary">Output/Batch</dt>
                    <dd class="col-7">{{ fnum($bom->quantity) }} {{ $bom->uom?->code ?? $bom->product?->uom?->code }}</dd>
                    <dt class="col-5 text-secondary">Harga Jual</dt>
                    <dd class="col-7">{{ rupiah($bom->product?->sale_price) }}</dd>
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$bom->is_active ? 'active' : 'cancelled'" :label="$bom->is_active ? 'Aktif' : 'Nonaktif'" /></dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
