@extends('layouts.app')

@section('title', $leave->leave_no)
@section('pretitle', 'Pengajuan Cuti')

@section('actions')
    @if($leave->status === 'pending')
        @can('leave.edit')
            <a href="{{ route('leaves.edit', $leave) }}" class="btn"><i class="ti ti-edit me-1"></i> Ubah</a>
        @endcan
        @can('leave.approve')
            <x-action-form :action="route('leaves.approve', $leave)" label="Setujui" icon="ti ti-check"
                           class="btn btn-primary"
                           confirm="Setujui cuti ini? Absensi pada rentang tanggal akan ditandai otomatis." />
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject-modal">
                <i class="ti ti-x me-1"></i> Tolak
            </button>
        @endcan
    @endif
@endsection

@section('content')
    @if($leave->status === 'rejected' && $leave->reject_reason)
        <div class="alert alert-danger">
            <h4 class="alert-title">Pengajuan ditolak</h4>
            <p class="mb-0">{{ $leave->reject_reason }}</p>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <x-card title="Detail Pengajuan">
                <dl class="row mb-0">
                    <dt class="col-4 text-secondary">Nomor</dt><dd class="col-8 fw-bold">{{ $leave->leave_no }}</dd>
                    <dt class="col-4 text-secondary">Karyawan</dt>
                    <dd class="col-8"><a href="{{ route('employees.show', $leave->employee_id) }}">{{ $leave->employee?->name }}</a></dd>
                    <dt class="col-4 text-secondary">Departemen</dt><dd class="col-8">{{ $leave->employee?->department?->name ?? '—' }}</dd>
                    <dt class="col-4 text-secondary">Jenis Cuti</dt><dd class="col-8">{{ $leave->leaveType?->name }}</dd>
                    <dt class="col-4 text-secondary">Mulai</dt><dd class="col-8">{{ fdate($leave->start_date) }}</dd>
                    <dt class="col-4 text-secondary">Selesai</dt><dd class="col-8">{{ fdate($leave->end_date) }}</dd>
                    <dt class="col-4 text-secondary">Jumlah Hari</dt><dd class="col-8 fw-bold">{{ fnum($leave->days) }} hari kerja</dd>
                    <dt class="col-4 text-secondary">Status</dt><dd class="col-8"><x-status :value="$leave->status" /></dd>
                    <dt class="col-4 text-secondary">Disetujui oleh</dt><dd class="col-8">{{ $leave->approver?->name ?? '—' }}</dd>
                    <dt class="col-4 text-secondary">Waktu</dt><dd class="col-8">{{ fdatetime($leave->approved_at) }}</dd>
                </dl>

                @if($leave->reason)
                    <hr>
                    <strong>Alasan</strong>
                    <div class="text-secondary">{!! nl2br(e($leave->reason)) !!}</div>
                @endif
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-stat label="Sisa kuota cuti tahun ini" :value="fnum($remaining).' hari'" icon="ti ti-beach" color="blue" />

            <x-card title="Kuota Jenis Cuti" class="mt-3">
                <dl class="row mb-0">
                    <dt class="col-7 text-secondary">Kuota per tahun</dt>
                    <dd class="col-5 text-end">{{ $leave->leaveType?->max_days }} hari</dd>
                    <dt class="col-7 text-secondary">Dibayar</dt>
                    <dd class="col-5 text-end">{{ $leave->leaveType?->is_paid ? 'Ya' : 'Tidak' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="reject-modal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" action="{{ route('leaves.reject', $leave) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tolak Pengajuan Cuti</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label required" for="reject_reason">Alasan penolakan</label>
                    <textarea name="reject_reason" id="reject_reason" class="form-control" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
@endpush
