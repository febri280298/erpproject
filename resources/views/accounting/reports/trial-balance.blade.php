@extends('layouts.app')

@section('title', 'Neraca Saldo')
@section('pretitle', 'Akuntansi · ' . fdate($filters['from']) . ' – ' . fdate($filters['to']))

@section('content')
    @include('partials.period-filter')

    <x-card title="Neraca Saldo" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>Kode</th><th>Nama Akun</th><th>Tipe</th>
                    <th class="text-num">Saldo Awal</th>
                    <th class="text-num">Mutasi Debit</th><th class="text-num">Mutasi Kredit</th>
                    <th class="text-num">Saldo Akhir</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="fw-bold">{{ $row['account']->code }}</td>
                        <td>
                            <a href="{{ route('accounting.ledger', ['account_id' => $row['account']->id, 'from' => $filters['from'], 'to' => $filters['to']]) }}">
                                {{ $row['account']->name }}
                            </a>
                        </td>
                        <td><span class="badge bg-secondary-lt">{{ $row['account']->typeLabel() }}</span></td>
                        <td class="text-num text-secondary">{{ rupiah($row['opening']) }}</td>
                        <td class="text-num">{{ rupiah($row['debit']) }}</td>
                        <td class="text-num">{{ rupiah($row['credit']) }}</td>
                        <td class="text-num fw-bold">{{ rupiah($row['closing']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada transaksi pada periode ini.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end">Total Mutasi</td>
                    <td class="text-num fs-4">{{ rupiah($totalDebit) }}</td>
                    <td class="text-num fs-4">{{ rupiah($totalCredit) }}</td>
                    <td class="text-num">
                        @if(abs($totalDebit - $totalCredit) < 0.01)
                            <span class="badge bg-green-lt">Seimbang</span>
                        @else
                            <span class="badge bg-red-lt">Selisih {{ rupiah(abs($totalDebit - $totalCredit)) }}</span>
                        @endif
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
@endsection
