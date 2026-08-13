@extends('layouts.app')

@section('title', 'Penawaran')
@section('pretitle', 'Penjualan')

@section('actions')
    @can('quotation.create')
        <a href="{{ route('quotations.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Penawaran
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor penawaran…',
            'statuses' => ['draft' => 'Draft', 'sent' => 'Terkirim', 'accepted' => 'Diterima', 'rejected' => 'Ditolak', 'closed' => 'Ditutup'],
            'selects' => [['name' => 'partner_id', 'label' => 'Pelanggan', 'options' => $customers]],
        ])

        @if($documents->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-file-off" title="Belum ada penawaran"
                         message="Buat penawaran harga untuk calon pelanggan." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Tanggal</th><th>Berlaku Sampai</th><th>Pelanggan</th>
                        <th class="text-num">Total</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($documents as $document)
                        <tr>
                            <td><a href="{{ route('quotations.show', $document) }}" class="fw-bold">{{ $document->quotation_no }}</a></td>
                            <td>{{ fdate($document->date) }}</td>
                            <td class="{{ $document->isExpired() ? 'text-danger' : '' }}">{{ fdate($document->valid_until) }}</td>
                            <td>{{ $document->customer?->name }}</td>
                            <td class="text-num">{{ rupiah($document->total) }}</td>
                            <td><x-status :value="$document->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('quotations.show', $document) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        <a class="dropdown-item" href="{{ route('quotations.print', $document) }}" target="_blank">
                                            <i class="ti ti-printer me-2"></i> Cetak
                                        </a>
                                        @if($document->isEditable())
                                            @can('quotation.edit')
                                                <a class="dropdown-item" href="{{ route('quotations.edit', $document) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('quotation.delete')
                                                <x-delete-form :action="route('quotations.destroy', $document)" label="Hapus" />
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
