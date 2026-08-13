@extends('layouts.app')

@section('title', 'Perintah Produksi')
@section('pretitle', 'Produksi')

@section('actions')
    @can('production-order.create')
        <a href="{{ route('production-orders.create') }}" class="btn btn-primary">
            <i class="ti ti-tools me-1"></i> Buat Perintah Produksi
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor perintah…',
            'statuses' => [
                'draft' => 'Draft', 'released' => 'Dirilis', 'in_progress' => 'Berjalan',
                'completed' => 'Selesai', 'cancelled' => 'Dibatalkan',
            ],
            'selects' => [['name' => 'warehouse_id', 'label' => 'Gudang', 'options' => $warehouses]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-tools-off" title="Belum ada perintah produksi"
                         message="Buat perintah produksi dari sebuah BOM aktif." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Tanggal</th><th>Produk</th><th>BOM</th><th>Gudang</th>
                        <th class="text-num">Rencana</th><th class="text-num">Hasil</th>
                        <th class="text-num">Total Biaya</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('production-orders.show', $document) }}" class="fw-bold">{{ $document->order_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ $document->product?->name }}</td>
                            <td class="text-secondary">{{ $document->bom?->bom_no ?? '—' }}</td>
                            <td class="text-secondary">{{ $document->warehouse?->name }}</td>
                            <td class="text-num">{{ fnum($document->quantity) }}</td>
                            <td class="text-num fw-bold">{{ fnum($document->produced_qty) }}</td>
                            <td class="text-num">{{ rupiah($document->total_cost) }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('production-orders.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @if($document->isDraft())
                                            @can('production-order.delete')
                                                <x-delete-form :action="route('production-orders.destroy', $document)" label="Hapus" />
                                            @endcan
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $documents->links() }}</div>
        @endif
    </x-card>
@endsection
