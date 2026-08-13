@extends('layouts.app')

@section('title', 'Buat Payroll')
@section('pretitle', 'SDM · ' . $nextNumber)

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('payrolls.store') }}">
                @csrf

                <x-card title="Periode Penggajian">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required" for="period_month">Bulan</label>
                            <select name="period_month" id="period_month" class="form-select" required>
                                @for($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @selected($m === $defaultMonth)>
                                        {{ \Carbon\CarbonImmutable::create(null, $m, 1)->translatedFormat('F') }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="period_year">Tahun</label>
                            <select name="period_year" id="period_year" class="form-select" required>
                                @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                                    <option value="{{ $y }}" @selected($y === $defaultYear)>{{ $y }}</option>
                                @endfor
                            </select>
                        </div>

                        <x-form.input name="payment_date" label="Rencana Tanggal Bayar" type="date"
                                      :value="now()->endOfMonth()->toDateString()" col="col-md-6" />
                        <x-form.textarea name="notes" label="Catatan" rows="2" />
                    </div>

                    <div class="alert alert-info mt-3 mb-0">
                        Seluruh karyawan aktif akan dimuat otomatis beserta gaji pokok, tunjangan,
                        lembur dari absensi, dan potongan BPJS/PPh sesuai pengaturan.
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('payrolls.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-users me-1"></i> Buat & Generate
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
@endsection
