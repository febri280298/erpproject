@extends('layouts.app')

@section('title', $journal->journal_no)
@section('pretitle', 'Jurnal')

@section('actions')
    @if($journal->status === 'posted')
        @can('journal.post')
            <x-action-form :action="route('journals.reverse', $journal)" label="Balik Jurnal" icon="ti ti-arrow-back-up"
                           class="btn btn-outline-danger"
                           confirm="Buat jurnal pembalik untuk entri ini? Jurnal asli akan ditandai dibalik." />
        @endcan
    @endif
@endsection

@section('content')
    @if($journal->status === 'reversed' && $journal->reversal)
        <div class="alert alert-warning">
            Jurnal ini telah dibalik oleh
            <a href="{{ route('journals.show', $journal->reversal) }}" class="fw-bold">{{ $journal->reversal->journal_no }}</a>.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Baris Jurnal" flush>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr><th>Akun</th><th>Keterangan</th><th>Mitra</th><th class="text-num">Debit</th><th class="text-num">Kredit</th></tr>
                        </thead>
                        <tbody>
                        @foreach($journal->lines as $line)
                            <tr>
                                <td>
                                    <a href="{{ route('accounts.show', $line->account_id) }}">{{ $line->account?->code }}</a>
                                    <div class="text-secondary small">{{ $line->account?->name }}</div>
                                </td>
                                <td class="text-secondary">{{ $line->description ?? '—' }}</td>
                                <td class="text-secondary">{{ $line->partner?->name ?? '—' }}</td>
                                <td class="text-num">{{ (float) $line->debit > 0 ? rupiah($line->debit) : '' }}</td>
                                <td class="text-num">{{ (float) $line->credit > 0 ? rupiah($line->credit) : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="fw-bold">
                            <td colspan="3">Total</td>
                            <td class="text-num">{{ rupiah($journal->total_debit) }}</td>
                            <td class="text-num">{{ rupiah($journal->total_credit) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="Informasi Jurnal">
                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$journal->status" /></dd>
                    <dt class="col-5 text-secondary">Tanggal</dt>
                    <dd class="col-7">{{ fdate($journal->date) }}</dd>
                    <dt class="col-5 text-secondary">Jenis</dt>
                    <dd class="col-7">{{ $journal->typeLabel() }}</dd>
                    <dt class="col-5 text-secondary">Referensi</dt>
                    <dd class="col-7">{{ $journal->reference ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Keterangan</dt>
                    <dd class="col-7">{{ $journal->description }}</dd>
                    <dt class="col-5 text-secondary">Sumber</dt>
                    <dd class="col-7">{{ $journal->sourceLabel() }}</dd>
                    <dt class="col-5 text-secondary">Dibuat oleh</dt>
                    <dd class="col-7">{{ $journal->creator?->name ?? 'Sistem' }}</dd>
                    <dt class="col-5 text-secondary">Seimbang</dt>
                    <dd class="col-7">
                        @if($journal->isBalanced())
                            <span class="badge bg-green-lt">Ya</span>
                        @else
                            <span class="badge bg-red-lt">Tidak</span>
                        @endif
                    </dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
