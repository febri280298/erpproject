@extends('layouts.app')

@section('title', 'Absensi')
@section('pretitle', 'SDM')

@section('actions')
    <a href="{{ route('attendances.recap') }}" class="btn"><i class="ti ti-report me-1"></i> Rekap Bulanan</a>
    @can('attendance.create')
        <a href="{{ route('attendances.create') }}" class="btn btn-primary">
            <i class="ti ti-calendar-plus me-1"></i> Input Absensi
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Karyawan</label>
                    <select name="employee_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($employees as $id => $name)
                            <option value="{{ $id }}" @selected(request('employee_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Departemen</label>
                    <select name="department_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($departments as $id => $name)
                            <option value="{{ $id }}" @selected(request('department_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach(['present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Alpa', 'leave' => 'Cuti', 'sick' => 'Sakit', 'holiday' => 'Libur'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Dari</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Sampai</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
            </form>
        </div>

        @if($attendances->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-calendar-off" title="Belum ada data absensi"
                         message="Gunakan Input Absensi untuk mencatat kehadiran harian." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Tanggal</th><th>NIK</th><th>Karyawan</th><th>Departemen</th>
                        <th>Masuk</th><th>Pulang</th><th class="text-num">Terlambat</th>
                        <th class="text-num">Lembur</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($attendances as $attendance)
                        <tr>
                            <td>{{ fdate($attendance->date) }}</td>
                            <td class="text-secondary">{{ $attendance->employee?->nik }}</td>
                            <td><a href="{{ route('employees.show', $attendance->employee_id) }}">{{ $attendance->employee?->name }}</a></td>
                            <td class="text-secondary">{{ $attendance->employee?->department?->name ?? '—' }}</td>
                            <td>{{ $attendance->check_in ?? '—' }}</td>
                            <td>{{ $attendance->check_out ?? '—' }}</td>
                            <td class="text-num">{{ $attendance->late_minutes > 0 ? $attendance->late_minutes.' mnt' : '—' }}</td>
                            <td class="text-num">{{ fnum($attendance->overtime_hours) }}</td>
                            <td><span class="badge bg-{{ $attendance->statusColor() }}-lt">{{ $attendance->statusLabel() }}</span></td>
                            <td class="text-end">
                                @can('attendance.delete')
                                    <x-delete-form :action="route('attendances.destroy', $attendance)"
                                                   class="btn btn-sm btn-ghost-danger"
                                                   confirm="Hapus data absensi ini?" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $attendances->links() }}</div>
        @endif
    </x-card>
@endsection
