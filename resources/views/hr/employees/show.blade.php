@extends('layouts.app')

@section('title', $employee->name)
@section('pretitle', 'Karyawan · ' . $employee->nik)

@section('actions')
    @can('employee.edit')
        <a href="{{ route('employees.edit', $employee) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i> Ubah</a>
    @endcan
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <x-card>
                <div class="text-center mb-3">
                    @if($employee->photoUrl())
                        <span class="avatar avatar-xl mb-2" style="background-image: url({{ $employee->photoUrl() }})"></span>
                    @else
                        <span class="avatar avatar-xl bg-primary-lt mb-2">{{ $employee->initials() }}</span>
                    @endif
                    <h3 class="mb-0">{{ $employee->name }}</h3>
                    <div class="text-secondary">{{ $employee->position?->name ?? '—' }}</div>
                    <span class="badge bg-{{ $employee->statusColor() }}-lt mt-2">{{ ucfirst($employee->status) }}</span>
                </div>

                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">NIK</dt><dd class="col-7">{{ $employee->nik }}</dd>
                    <dt class="col-5 text-secondary">Departemen</dt><dd class="col-7">{{ $employee->department?->name ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Status</dt><dd class="col-7">{{ ucfirst($employee->employment_type) }}</dd>
                    <dt class="col-5 text-secondary">Bergabung</dt><dd class="col-7">{{ fdate($employee->join_date) }}</dd>
                    <dt class="col-5 text-secondary">Masa Kerja</dt><dd class="col-7">{{ fnum($employee->yearsOfService(), 1) }} tahun</dd>
                    <dt class="col-5 text-secondary">Telepon</dt><dd class="col-7">{{ $employee->phone ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Email</dt><dd class="col-7">{{ $employee->email ?? '—' }}</dd>
                    <dt class="col-5 text-secondary">Bank</dt><dd class="col-7">{{ $employee->bank_name ?? '—' }} {{ $employee->bank_account }}</dd>
                    <dt class="col-5 text-secondary">Gaji Pokok</dt><dd class="col-7">{{ rupiah($employee->basic_salary) }}</dd>
                    <dt class="col-5 text-secondary">Tunjangan</dt><dd class="col-7">{{ rupiah($employee->allowance) }}</dd>
                    <dt class="col-5 text-secondary">Sisa Cuti</dt><dd class="col-7 fw-bold">{{ fnum($employee->remainingLeave()) }} hari</dd>
                </dl>
            </x-card>
        </div>

        <div class="col-lg-8">
            <div class="row row-cards mb-3">
                <div class="col-6 col-md-3">
                    <x-stat label="Hadir bulan ini" :value="(int) ($attendanceSummary->present ?? 0)" icon="ti ti-check" color="green" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat label="Terlambat" :value="(int) ($attendanceSummary->late ?? 0)" icon="ti ti-clock" color="orange" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat label="Alpa" :value="(int) ($attendanceSummary->absent ?? 0)" icon="ti ti-x" color="red" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat label="Lembur (jam)" :value="fnum($attendanceSummary->overtime ?? 0)" icon="ti ti-hourglass" color="blue" />
                </div>
            </div>

            <x-card title="Absensi Terakhir" flush>
                @if($recentAttendances->isEmpty())
                    <div class="card-body"><x-empty icon="ti ti-calendar-off" title="Belum ada absensi" message="Data absensi akan tampil di sini." /></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th class="text-num">Terlambat</th><th class="text-num">Lembur</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            @foreach($recentAttendances as $attendance)
                                <tr>
                                    <td>{{ fdate($attendance->date) }}</td>
                                    <td>{{ $attendance->check_in ?? '—' }}</td>
                                    <td>{{ $attendance->check_out ?? '—' }}</td>
                                    <td class="text-num">{{ $attendance->late_minutes > 0 ? $attendance->late_minutes.' mnt' : '—' }}</td>
                                    <td class="text-num">{{ fnum($attendance->overtime_hours) }}</td>
                                    <td><span class="badge bg-{{ $attendance->statusColor() }}-lt">{{ $attendance->statusLabel() }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card title="Riwayat Cuti" flush class="mt-3">
                @if($recentLeaves->isEmpty())
                    <div class="card-body"><x-empty icon="ti ti-beach" title="Belum ada pengajuan cuti" message="Pengajuan cuti akan tampil di sini." /></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                            <tr><th>Nomor</th><th>Jenis</th><th>Mulai</th><th>Selesai</th><th class="text-num">Hari</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            @foreach($recentLeaves as $leave)
                                <tr>
                                    <td><a href="{{ route('leaves.show', $leave) }}">{{ $leave->leave_no }}</a></td>
                                    <td>{{ $leave->leaveType?->name }}</td>
                                    <td>{{ fdate($leave->start_date) }}</td>
                                    <td>{{ fdate($leave->end_date) }}</td>
                                    <td class="text-num">{{ fnum($leave->days) }}</td>
                                    <td><x-status :value="$leave->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection
