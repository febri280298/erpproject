@extends('layouts.app')

@section('title', 'Rekap Absensi')
@section('pretitle', 'SDM · ' . $period->translatedFormat('F Y'))

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="month">Bulan</label>
                <select name="month" id="month" class="form-select">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($m === $month)>
                            {{ \Carbon\CarbonImmutable::create(null, $m, 1)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="year">Tahun</label>
                <select name="year" id="year" class="form-select">
                    @for($y = now()->year - 3; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Tampilkan</button></div>
            <div class="col-md-2">
                <button type="button" class="btn w-100 d-print-none" onclick="window.print()">
                    <i class="ti ti-printer me-1"></i> Cetak
                </button>
            </div>
        </form>
    </x-card>

    <x-card :title="'Rekap Kehadiran '.$period->translatedFormat('F Y')" flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th>NIK</th><th>Karyawan</th><th>Departemen</th>
                    <th class="text-num">Hadir</th><th class="text-num">Terlambat</th>
                    <th class="text-num">Alpa</th><th class="text-num">Cuti/Sakit</th>
                    <th class="text-num">Lembur (jam)</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="text-secondary">{{ $row->nik }}</td>
                        <td><a href="{{ route('employees.show', $row) }}">{{ $row->name }}</a></td>
                        <td class="text-secondary">{{ $row->department?->name ?? '—' }}</td>
                        <td class="text-num fw-bold">{{ $row->present_days }}</td>
                        <td class="text-num {{ $row->late_days > 0 ? 'text-orange' : '' }}">{{ $row->late_days }}</td>
                        <td class="text-num {{ $row->absent_days > 0 ? 'text-danger' : '' }}">{{ $row->absent_days }}</td>
                        <td class="text-num">{{ $row->leave_days }}</td>
                        <td class="text-num">{{ fnum($row->overtime_hours ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Belum ada karyawan aktif.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
