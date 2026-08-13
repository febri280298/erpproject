@extends('layouts.app')

@section('title', 'Bill of Materials')
@section('pretitle', 'Produksi')

@section('actions')
    @can('bom.create')
        <a href="{{ route('boms.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Buat BOM</a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nomor / nama BOM…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
            </form>
        </div>

        @if($boms->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-tools-off" title="Belum ada BOM"
                         message="Definisikan komposisi bahan untuk setiap produk jadi." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Nama</th><th>Produk Jadi</th>
                        <th class="text-num">Output / Batch</th><th class="text-num">Komponen</th>
                        <th class="text-num">Biaya Bahan</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($boms as $bom)
                        <tr>
                            <td><a href="{{ route('boms.show', $bom) }}" class="fw-bold">{{ $bom->bom_no }}</a></td>
                            <td>{{ $bom->name }}</td>
                            <td>{{ $bom->product?->name }}</td>
                            <td class="text-num">{{ fnum($bom->quantity) }} {{ $bom->uom?->code }}</td>
                            <td class="text-num">{{ $bom->items_count }}</td>
                            <td class="text-num">{{ rupiah($bom->materialCost()) }}</td>
                            <td><x-status :value="$bom->is_active ? 'active' : 'cancelled'" :label="$bom->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('boms.show', $bom) }}"><i class="ti ti-eye me-2"></i> Detail</a>
                                        @can('bom.edit')
                                            <a class="dropdown-item" href="{{ route('boms.edit', $bom) }}"><i class="ti ti-edit me-2"></i> Ubah</a>
                                        @endcan
                                        @can('bom.delete')
                                            <x-delete-form :action="route('boms.destroy', $bom)" label="Hapus" />
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $boms->links() }}</div>
        @endif
    </x-card>
@endsection
