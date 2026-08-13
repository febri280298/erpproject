@extends('layouts.app')

@section('title', 'Penggajian')
@section('pretitle', 'SDM')

@section('actions')
    @can('payroll.create')
        <a href="{{ route('payrolls.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Buat Payroll
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Cari</label>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nomor payroll…">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Tahun</label>
                    <select name="year" class="form-select">
                        <option value="">Semua</option>
                        @for($y = now()->year - 3; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="approved" @selected(request('status') === 'approved')>Disetujui</option>
                        <option value="paid" @selected(request('status') === 'paid')>Dibayar</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
            </form>
        </div>

        @if($payrolls->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-wallet-off" title="Belum ada payroll"
                         message="Buat payroll bulanan untuk menghitung gaji seluruh karyawan aktif." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Nomor</th><th>Periode</th><th class="text-num">Karyawan</th>
                        <th class="text-num">Total Bruto</th><th class="text-num">Potongan</th>
                        <th class="text-num">Total Netto</th><th>Status</th><th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($payrolls as $payroll)
                        <tr>
                            <td><a href="{{ route('payrolls.show', $payroll) }}" class="fw-bold">{{ $payroll->payroll_no }}</a></td>
                            <td>{{ $payroll->periodLabel() }}</td>
                            <td class="text-num">{{ $payroll->items_count }}</td>
                            <td class="text-num">{{ rupiah($payroll->total_gross) }}</td>
                            <td class="text-num text-danger">{{ rupiah($payroll->total_deduction) }}</td>
                            <td class="text-num fw-bold">{{ rupiah($payroll->total_net) }}</td>
                            <td><x-status :value="$payroll->status" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('payrolls.show', $payroll) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @if($payroll->isDraft())
                                            @can('payroll.delete')
                                                <x-delete-form :action="route('payrolls.destroy', $payroll)" label="Hapus" />
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

            <div class="card-footer d-flex align-items-center">{{ $payrolls->links() }}</div>
        @endif
    </x-card>
@endsection
