@extends('layouts.app')

@section('title', 'Penyesuaian Stok')
@section('pretitle', 'Stok & Gudang')

@section('actions')
    @can('stock-adjustment.create')
        <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary">
            <i class="ti ti-adjustments me-1"></i> Buat Penyesuaian
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor penyesuaian…',
            'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'],
            'selects' => [['name' => 'warehouse_id', 'label' => 'Gudang', 'options' => $warehouses]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-adjustments-off" title="Belum ada penyesuaian stok"
                         message="Gunakan penyesuaian untuk mencatat hasil stok opname atau koreksi manual." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Tanggal</th><th>Gudang</th><th>Alasan</th>
                        <th class="text-num">Item</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('stock-adjustments.show', $document) }}" class="fw-bold">{{ $document->adjustment_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ $document->warehouse?->name }}</td>
                            <td class="text-secondary">{{ $document->reason ?? '—' }}</td>
                            <td class="text-num">{{ $document->items_count }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('stock-adjustments.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @if($document->isEditable())
                                            @can('stock-adjustment.edit')
                                                <a class="dropdown-item" href="{{ route('stock-adjustments.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('stock-adjustment.delete')
                                                <x-delete-form :action="route('stock-adjustments.destroy', $document)" label="Hapus" />
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
