@extends('layouts.app')

@section('title', 'Detail Aktivitas')
@section('pretitle', 'Log Aktivitas')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <x-card title="Informasi Aktivitas">
                <dl class="row mb-0">
                    <dt class="col-4 text-secondary">Waktu</dt><dd class="col-8">{{ fdatetime($log->created_at, 'd F Y H:i:s') }}</dd>
                    <dt class="col-4 text-secondary">Pengguna</dt><dd class="col-8">{{ $log->user?->name ?? 'Sistem' }}</dd>
                    <dt class="col-4 text-secondary">Aksi</dt>
                    <dd class="col-8"><span class="badge bg-{{ $log->eventColor() }}-lt">{{ $log->event }}</span></dd>
                    <dt class="col-4 text-secondary">Objek</dt><dd class="col-8">{{ $log->subjectName() }} #{{ $log->subject_id }}</dd>
                    <dt class="col-4 text-secondary">Keterangan</dt><dd class="col-8">{{ $log->description }}</dd>
                    <dt class="col-4 text-secondary">Alamat IP</dt><dd class="col-8">{{ $log->ip_address ?? '—' }}</dd>
                </dl>
            </x-card>

            @if(! empty($log->properties['changes']))
                <x-card title="Perubahan Data" flush class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Kolom</th><th>Nilai Baru</th></tr></thead>
                            <tbody>
                            @foreach($log->properties['changes'] as $field => $value)
                                <tr>
                                    <td class="text-secondary">{{ \Illuminate\Support\Str::headline($field) }}</td>
                                    <td>{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @elseif($log->properties)
                <x-card title="Properti" class="mt-3">
                    <pre class="mb-0">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </x-card>
            @endif

            <div class="mt-3">
                <a href="{{ route('activity-logs.index') }}" class="btn btn-link">← Kembali ke log aktivitas</a>
            </div>
        </div>
    </div>
@endsection
