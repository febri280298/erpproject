@extends('layouts.app')

@section('title', 'Transfer Gudang')
@section('pretitle', 'Stok & Gudang')

@section('actions')
    @can('stock-transfer.create')
        <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary">
            <i class="ti ti-arrows-exchange me-1"></i> Buat Transfer
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor transfer…',
            'statuses' => ['draft' => 'Draft', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'],
            'selects' => [['name' => 'warehouse_id', 'label' => 'Gudang', 'options' => $warehouses]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-arrows-exchange" title="Belum ada transfer gudang"
                         message="Pindahkan stok antar gudang tanpa mengubah nilai persediaan total." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Tanggal</th><th>Dari</th><th>Ke</th>
                        <th class="text-num">Item</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('stock-transfers.show', $document) }}" class="fw-bold">{{ $document->transfer_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td>{{ $document->fromWarehouse?->name }}</td>
                            <td>{{ $document->toWarehouse?->name }}</td>
                            <td class="text-num">{{ $document->items_count }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('stock-transfers.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @if($document->isEditable())
                                            @can('stock-transfer.edit')
                                                <a class="dropdown-item" href="{{ route('stock-transfers.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('stock-transfer.delete')
                                                <x-delete-form :action="route('stock-transfers.destroy', $document)" label="Hapus" />
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
