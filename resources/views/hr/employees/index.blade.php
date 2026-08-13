@extends('layouts.app')

@section('title', 'Karyawan')
@section('pretitle', 'SDM')

@section('actions')
    <a href="{{ route('attendances.recap') }}" class="btn"><i class="ti ti-report me-1"></i> Rekap Absensi</a>
    @can('employee.create')
        <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i> Tambah Karyawan</a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="NIK / nama…">
                    </div>
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
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="resigned" @selected(request('status') === 'resigned')>Resign</option>
                        <option value="terminated" @selected(request('status') === 'terminated')>Diberhentikan</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
                <div class="col text-end text-secondary">{{ $employees->total() }} karyawan</div>
            </form>
        </div>

        @if($employees->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-users-off" title="Belum ada karyawan"
                         message="Tambahkan data karyawan untuk mulai mencatat absensi dan penggajian." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>NIK</th><th>Nama</th><th>Departemen</th><th>Jabatan</th>
                        <th>Bergabung</th><th class="text-num">Gaji Pokok</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($employees as $employee)
                        <tr>
                            <td class="fw-bold">{{ $employee->nik }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($employee->photoUrl())
                                        <span class="avatar avatar-sm" style="background-image: url({{ $employee->photoUrl() }})"></span>
                                    @else
                                        <span class="avatar avatar-sm bg-primary-lt">{{ $employee->initials() }}</span>
                                    @endif
                                    <a href="{{ route('employees.show', $employee) }}">{{ $employee->name }}</a>
                                </div>
                            </td>
                            <td class="text-secondary">{{ $employee->department?->name ?? '—' }}</td>
                            <td class="text-secondary">{{ $employee->position?->name ?? '—' }}</td>
                            <td>{{ fdate($employee->join_date) }}</td>
                            <td class="text-num">{{ rupiah($employee->basic_salary) }}</td>
                            <td><span class="badge bg-{{ $employee->statusColor() }}-lt">{{ ucfirst($employee->status) }}</span></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('employees.show', $employee) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @can('employee.edit')
                                            <a class="dropdown-item" href="{{ route('employees.edit', $employee) }}">
                                                <i class="ti ti-edit me-2"></i> Ubah
                                            </a>
                                        @endcan
                                        @can('employee.delete')
                                            <x-delete-form :action="route('employees.destroy', $employee)" label="Hapus" />
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $employees->links() }}</div>
        @endif
    </x-card>
@endsection
