@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('pretitle', 'Pengaturan')

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Keterangan…">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Pengguna</label>
                    <select name="user_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($users as $id => $name)
                            <option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Aksi</label>
                    <select name="event" class="form-select">
                        <option value="">Semua</option>
                        @foreach($events as $event)
                            <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Dari</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Sampai</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
            </form>
        </div>

        @if($logs->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-history-off" title="Belum ada aktivitas tercatat"
                         message="Setiap perubahan data akan tercatat otomatis di sini." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Objek</th><th>Keterangan</th><th>IP</th><th class="w-1"></th></tr>
                    </thead>
                    <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td class="text-secondary">{{ fdatetime($log->created_at, 'd M Y H:i:s') }}</td>
                            <td>{{ $log->user?->name ?? 'Sistem' }}</td>
                            <td><span class="badge bg-{{ $log->eventColor() }}-lt">{{ $log->event }}</span></td>
                            <td class="text-secondary">{{ $log->subjectName() }}</td>
                            <td>{{ $log->description }}</td>
                            <td class="text-secondary small">{{ $log->ip_address ?? '—' }}</td>
                            <td class="text-end">
                                @if($log->properties)
                                    <a href="{{ route('activity-logs.show', $log) }}" class="btn btn-sm btn-ghost-secondary">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $logs->links() }}</div>
        @endif
    </x-card>
@endsection
