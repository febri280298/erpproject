@extends('layouts.app')

@section('title', $leave ? 'Ubah Pengajuan Cuti' : 'Ajukan Cuti')
@section('pretitle', 'SDM')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ $leave ? route('leaves.update', $leave) : route('leaves.store') }}">
                @csrf
                @if($leave) @method('PUT') @endif

                <x-card title="Pengajuan Cuti">
                    <div class="row g-3">
                        <x-form.select name="employee_id" label="Karyawan" :options="$employees"
                                       :value="$leave->employee_id ?? null" required col="col-md-6" />
                        <x-form.select name="leave_type_id" label="Jenis Cuti" :options="$leaveTypes"
                                       :value="$leave->leave_type_id ?? null" required col="col-md-6" />

                        <x-form.input name="start_date" label="Tanggal Mulai" type="date"
                                      :value="optional($leave?->start_date)->toDateString() ?? now()->toDateString()"
                                      required col="col-md-6" />
                        <x-form.input name="end_date" label="Tanggal Selesai" type="date"
                                      :value="optional($leave?->end_date)->toDateString() ?? now()->toDateString()"
                                      required col="col-md-6"
                                      help="Jumlah hari dihitung otomatis, akhir pekan tidak dihitung." />

                        <x-form.textarea name="reason" label="Alasan" :value="$leave->reason ?? null" rows="3" />
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('leaves.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Simpan Pengajuan
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
@endsection
