@extends('layouts.app')

@section('title', 'Jurnal Umum')
@section('pretitle', 'Akuntansi')

@section('actions')
    @can('journal.create')
        <a href="{{ route('journals.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Jurnal Manual
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        @include('partials.doc-filters', [
            'searchPlaceholder' => 'Nomor / keterangan…',
            'statuses' => ['posted' => 'Diposting', 'reversed' => 'Dibalik'],
            'selects' => [[
                'name' => 'type',
                'label' => 'Jenis',
                'options' => ['general' => 'Umum', 'sales' => 'Penjualan', 'purchase' => 'Pembelian', 'cash' => 'Kas/Bank', 'inventory' => 'Persediaan', 'payroll' => 'Penggajian'],
            ]],
        ])

        @if($journals->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-book-off" title="Belum ada jurnal"
                         message="Jurnal terbentuk otomatis saat dokumen diposting, atau dibuat manual." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>No. Jurnal</th><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th>Sumber</th>
                        <th class="text-num">Debit</th><th class="text-num">Kredit</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($journals as $journal)
                        <tr>
                            <td><a href="{{ route('journals.show', $journal) }}" class="fw-bold">{{ $journal->journal_no }}</a></td>
                            <td>{{ fdate($journal->date) }}</td>
                            <td><span class="badge bg-secondary-lt">{{ $journal->typeLabel() }}</span></td>
                            <td class="text-secondary">{{ $journal->description }}</td>
                            <td class="text-secondary small">{{ $journal->sourceLabel() }}</td>
                            <td class="text-num">{{ rupiah($journal->total_debit, 2) }}</td>
                            <td class="text-num">{{ rupiah($journal->total_credit, 2) }}</td>
                            <td><x-status :value="$journal->status" /></td>
                            <td class="text-end">
                                <a href="{{ route('journals.show', $journal) }}" class="btn btn-sm btn-ghost-secondary">
                                    <i class="ti ti-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $journals->links() }}</div>
        @endif
    </x-card>
@endsection
