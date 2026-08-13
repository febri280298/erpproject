@extends('layouts.app')

@section('title', 'Input Absensi Harian')
@section('pretitle', 'SDM · ' . fdate($date))

@section('content')
    <x-card class="mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="date">Tanggal</label>
                <input type="date" name="date" id="date" value="{{ $date }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="department_id">Departemen</label>
                <select name="department_id" id="department_id" class="form-select">
                    <option value="">Semua departemen</option>
                    @foreach($departments as $id => $name)
                        <option value="{{ $id }}" @selected($departmentId == $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Muat</button></div>
        </form>
    </x-card>

    @if($employees->isEmpty())
        <x-card>
            <x-empty icon="ti ti-users-off" title="Tidak ada karyawan aktif"
                     message="Tambahkan karyawan terlebih dahulu." />
        </x-card>
    @else
        {{-- One row per active employee; existing records for the date are pre-filled. --}}
        <form method="POST" action="{{ route('attendances.store') }}"
              x-data="{ setAll(status) { document.querySelectorAll('[data-status-select]').forEach(el => { el.value = status; el.dispatchEvent(new Event('change')); }); } }">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">

            <x-card :title="'Daftar Kehadiran — '.fdate($date)" flush>
                <x-slot:actions>
                    <button type="button" class="btn btn-sm" @click="setAll('present')">Tandai Semua Hadir</button>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" @click="setAll('absent')">Tandai Semua Alpa</button>
                </x-slot:actions>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                        <tr>
                            <th>NIK</th><th>Karyawan</th><th>Departemen</th>
                            <th style="width:9rem">Status</th>
                            <th style="width:8rem">Masuk</th><th style="width:8rem">Pulang</th>
                            <th style="width:7rem" class="text-end">Lembur (jam)</th>
                            <th style="min-width:10rem">Catatan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($employees as $index => $employee)
                            @php $record = $existing->get($employee->id); @endphp
                            <tr>
                                <td class="text-secondary">
                                    <input type="hidden" name="rows[{{ $index }}][employee_id]" value="{{ $employee->id }}">
                                    {{ $employee->nik }}
                                </td>
                                <td>{{ $employee->name }}</td>
                                <td class="text-secondary">{{ $employee->department?->name ?? '—' }}</td>
                                <td>
                                    <select name="rows[{{ $index }}][status]" class="form-select" data-status-select>
                                        @foreach(['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Alpa', 'leave' => 'Cuti', 'sick' => 'Sakit', 'holiday' => 'Libur'] as $value => $label)
                                            <option value="{{ $value }}" @selected(($record->status ?? 'present') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="time" name="rows[{{ $index }}][check_in]" class="form-control"
                                           value="{{ $record?->check_in ? substr($record->check_in, 0, 5) : $defaultCheckIn }}">
                                </td>
                                <td>
                                    <input type="time" name="rows[{{ $index }}][check_out]" class="form-control"
                                           value="{{ $record?->check_out ? substr($record->check_out, 0, 5) : $defaultCheckOut }}">
                                </td>
                                <td>
                                    <input type="number" step="0.5" min="0" max="24" name="rows[{{ $index }}][overtime_hours]"
                                           class="form-control text-end" value="{{ (float) ($record->overtime_hours ?? 0) }}">
                                </td>
                                <td>
                                    <input type="text" name="rows[{{ $index }}][notes]" class="form-control"
                                           value="{{ $record->notes ?? '' }}">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-secondary small">
                            Keterlambatan dihitung otomatis dari jam masuk standar ({{ $defaultCheckIn }}).
                        </span>
                        <div class="d-flex gap-2">
                            <a href="{{ route('attendances.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Simpan Absensi
                            </button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    @endif
@endsection
