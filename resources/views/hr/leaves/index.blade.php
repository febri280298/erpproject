@extends('layouts.app')

@section('title', 'Cuti')
@section('pretitle', 'SDM')

@section('actions')
    <a href="{{ route('leave-types.index') }}" class="btn"><i class="ti ti-list me-1"></i> Jenis Cuti</a>
    @can('leave.create')
        <a href="{{ route('leaves.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Ajukan Cuti</a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nomor cuti…">
                </div>
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
                    <label class="form-label small mb-1">Jenis</label>
                    <select name="leave_type_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($leaveTypes as $id => $name)
                            <option value="{{ $id }}" @selected(request('leave_type_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="pending" @selected(request('status') === 'pending')>Menunggu</option>
                        <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Ditolak</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
            </form>
        </div>

        @if($leaves->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-beach" title="Belum ada pengajuan cuti"
                         message="Pengajuan cuti karyawan akan tampil di sini." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Karyawan</th><th>Jenis</th><th>Mulai</th><th>Selesai</th>
                        <th class="text-num">Hari</th><th>Status</th><th>Disetujui oleh</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($leaves as $leave)
                        <tr>
                            <td><a href="{{ route('leaves.show', $leave) }}" class="fw-bold">{{ $leave->leave_no }}</a></td>
                            <td>{{ $leave->employee?->name }}</td>
                            <td class="text-secondary">{{ $leave->leaveType?->name }}</td>
                            <td>{{ fdate($leave->start_date) }}</td>
                            <td>{{ fdate($leave->end_date) }}</td>
                            <td class="text-num">{{ fnum($leave->days) }}</td>
                            <td><x-status :value="$leave->status" /></td>
                            <td class="text-secondary">{{ $leave->approver?->name ?? '—' }}</td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('leaves.show', $leave) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @if($leave->isEditable())
                                            @can('leave.edit')
                                                <a class="dropdown-item" href="{{ route('leaves.edit', $leave) }}">
                                                    <i class="ti ti-edit me-2"></i> Ubah
                                                </a>
                                            @endcan
                                            @can('leave.delete')
                                                <x-delete-form :action="route('leaves.destroy', $leave)" label="Hapus" />
                                            @endcan
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $leaves->links() }}</div>
        @endif
    </x-card>
@endsection
