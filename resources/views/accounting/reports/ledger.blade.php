@extends('layouts.app')

@section('title', 'Buku Besar')
@section('pretitle', 'Akuntansi')

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label required" for="account_id">Akun</label>
                <select name="account_id" id="account_id" class="form-select" required>
                    <option value="">— Pilih akun —</option>
                    @foreach($accounts as $id => $label)
                        <option value="{{ $id }}" @selected($filters['accountId'] == $id)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="from">Dari</label>
                <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">Sampai</label>
                <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
        </form>
    </x-card>

    @if(! $account)
        <x-card>
            <x-empty icon="ti ti-book" title="Pilih akun" message="Pilih akun untuk menampilkan buku besarnya." />
        </x-card>
    @else
        <x-card :title="$account->label()" :subtitle="fdate($filters['from']).' – '.fdate($filters['to'])" flush>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Tanggal</th><th>No. Jurnal</th><th>Keterangan</th><th>Mitra</th>
                        <th class="text-num">Debit</th><th class="text-num">Kredit</th><th class="text-num">Saldo</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php $running = $opening; @endphp
                    <tr class="table-light fw-bold">
                        <td colspan="6">Saldo awal</td>
                        <td class="text-num">{{ rupiah($running) }}</td>
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
                            <td class="text-num">{{ (float) $line->debit > 0 ? rupiah($line->debit) : '' }}</td>
                            <td class="text-num">{{ (float) $line->credit > 0 ? rupiah($line->credit) : '' }}</td>
                            <td class="text-num fw-bold">{{ rupiah($running) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada mutasi pada periode ini.</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr class="fw-bold">
                        <td colspan="4">Total</td>
                        <td class="text-num">{{ rupiah($lines->sum('debit')) }}</td>
                        <td class="text-num">{{ rupiah($lines->sum('credit')) }}</td>
                        <td class="text-num fs-4">{{ rupiah($running) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
    @endif
@endsection
