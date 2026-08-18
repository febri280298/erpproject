@extends('layouts.app')

@section('title', 'Bagan Akun')
@section('pretitle', 'Akuntansi')

@section('actions')
    @can('account.create')
        <a href="{{ route('accounts.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Tambah Akun</a>
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
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Kode / nama akun…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Tipe</label>
                    <select name="type" class="form-select">
                        <option value="">Semua</option>
                        @foreach(['asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Ekuitas', 'revenue' => 'Pendapatan', 'expense' => 'Beban'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="active" @selected(request('status') === 'active')>Aktif</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Filter</button></div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr>
                    <th style="width:8rem">Kode</th><th>Nama Akun</th><th>Tipe</th><th>Saldo Normal</th>
                    <th class="text-num">Mutasi Debit</th><th class="text-num">Mutasi Kredit</th>
                    <th>Status</th><th class="w-1"></th>
                </tr>
                </thead>
                <tbody>
                @foreach($accounts as $account)
                    @php $movement = $totals->get($account->id); @endphp
                    <tr class="{{ $account->is_postable ? '' : 'table-light fw-bold' }}">
                        <td class="{{ $account->parent_id ? 'ps-4' : '' }}">{{ $account->code }}</td>
                        <td>
                            @if($account->is_postable)
                                <a href="{{ route('accounts.show', $account) }}">{{ $account->name }}</a>
                            @else
                                {{ $account->name }}
                            @endif
                        </td>
                        <td><span class="badge bg-secondary-lt">{{ $account->typeLabel() }}</span></td>
                        <td class="text-secondary">{{ $account->isDebitNormal() ? 'Debit' : 'Kredit' }}</td>
                        <td class="text-num">{{ $movement ? rupiah($movement->debit) : '—' }}</td>
                        <td class="text-num">{{ $movement ? rupiah($movement->credit) : '—' }}</td>
                        <td><x-status :value="$account->is_active ? 'active' : 'cancelled'" :label="$account->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if($account->is_postable)
                                        <a class="dropdown-item" href="{{ route('accounts.show', $account) }}">
                                            <i class="ti ti-eye me-2"></i> Buku Besar
                                        </a>
                                    @endif
                                    @can('account.edit')
                                        <a class="dropdown-item" href="{{ route('accounts.edit', $account) }}">
                                            <i class="ti ti-edit me-2"></i> Ubah
                                        </a>
                                    @endcan
                                    @can('account.delete')
                                        <x-delete-form :action="route('accounts.destroy', $account)" label="Hapus" />
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center">{{ $accounts->links() }}</div>
    </x-card>
@endsection
