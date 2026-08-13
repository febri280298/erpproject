@extends('layouts.app')

@section('title', $account->label())
@section('pretitle', 'Buku Besar Akun')

@section('actions')
    @can('account.edit')
        <a href="{{ route('accounts.edit', $account) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah Akun</a>
    @endcan
@endsection

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="from">Dari</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="to">Sampai</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-md-4 text-end">
                <div class="text-secondary small">Saldo Awal Periode</div>
                <div class="fs-3 fw-bold">{{ rupiah($openingBalance, 2) }}</div>
            </div>
        </form>
    </x-card>

    <x-card :title="'Mutasi ' . fdate($filters['from']) . ' – ' . fdate($filters['to'])" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Tanggal</th><th>No. Jurnal</th><th>Keterangan</th><th>Mitra</th>
                    <th class="text-num">Debit</th><th class="text-num">Kredit</th><th class="text-num">Saldo</th>
                </tr>
                </thead>
                <tbody>
                @php $running = $openingBalance; @endphp
                <tr class="table-light fw-bold">
                    <td colspan="6">Saldo awal</td>
                    <td class="text-num">{{ rupiah($running, 2) }}</td>
                </tr>

                @forelse($lines as $line)
                    @php
                        $delta = $account->isDebitNormal()
                            ? (float) $line->debit - (float) $line->credit
                            : (float) $line->credit - (float) $line->debit;
                        $running += $delta;
                    @endphp
                    <tr>
                        <td>{{ fdate($line->journal?->date) }}</td>
                        <td><a href="{{ route('journals.show', $line->journal_id) }}">{{ $line->journal?->journal_no }}</a></td>
                        <td class="text-secondary">{{ $line->description ?? $line->journal?->description }}</td>
                        <td class="text-secondary">{{ $line->partner?->name ?? '—' }}</td>
                        <td class="text-num">{{ (float) $line->debit > 0 ? rupiah($line->debit, 2) : '' }}</td>
                        <td class="text-num">{{ (float) $line->credit > 0 ? rupiah($line->credit, 2) : '' }}</td>
                        <td class="text-num fw-bold">{{ rupiah($running, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada mutasi pada periode ini.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td colspan="4">Total mutasi</td>
                    <td class="text-num">{{ rupiah($lines->sum('debit'), 2) }}</td>
                    <td class="text-num">{{ rupiah($lines->sum('credit'), 2) }}</td>
                    <td class="text-num fs-4">{{ rupiah($running, 2) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
