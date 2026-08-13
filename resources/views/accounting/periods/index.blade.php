@extends('layouts.app')

@section('title', 'Periode Fiskal')
@section('pretitle', 'Akuntansi')

@section('actions')
    @can('fiscal-period.edit')
        <form method="POST" action="{{ route('fiscal-periods.generate') }}" class="d-flex gap-2">
            @csrf
            <select name="year" class="form-select" style="width:8rem">
                @foreach($years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary"><i class="ti ti-calendar-plus me-1"></i> Buat Periode</button>
        </form>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Tahun</label>
                    <select name="year" class="form-select" data-filter-submit>
                        @foreach($years as $option)
                            <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($periods->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-calendar-off" :title="'Periode tahun '.$year.' belum dibuat'"
                         message="Gunakan tombol Buat Periode untuk membuat 12 periode bulanan sekaligus." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr><th>Periode</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Ditutup oleh</th><th class="w-1"></th></tr>
                    </thead>
                    <tbody>
                    @foreach($periods as $period)
                        <tr>
                            <td class="fw-bold">{{ $period->label() }}</td>
                            <td>{{ fdate($period->start_date) }}</td>
                            <td>{{ fdate($period->end_date) }}</td>
                            <td>
                                <span class="badge bg-{{ $period->is_closed ? 'red' : 'green' }}-lt">
                                    {{ $period->is_closed ? 'Ditutup' : 'Terbuka' }}
                                </span>
                            </td>
                            <td class="text-secondary">
                                {{ $period->closer?->name ?? '—' }}
                                @if($period->closed_at)<div class="small">{{ fdatetime($period->closed_at) }}</div>@endif
                            </td>
                            <td class="text-end">
                                @can('fiscal-period.edit')
                                    <x-action-form :action="route('fiscal-periods.toggle', $period)"
                                                   :label="$period->is_closed ? 'Buka' : 'Tutup'"
                                                   :icon="$period->is_closed ? 'ti ti-lock-open' : 'ti ti-lock'"
                                                   :class="'btn btn-sm '.($period->is_closed ? 'btn-outline-primary' : 'btn-outline-danger')"
                                                   :confirm="$period->is_closed
                                                       ? 'Buka kembali periode ini? Jurnal baru akan diizinkan.'
                                                       : 'Tutup periode ini? Jurnal baru pada periode ini akan ditolak.'" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
