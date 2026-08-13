@extends('layouts.app')

@section('title', 'Customer & Supplier')
@section('pretitle', 'Data Master')

@section('actions')
    @can('partner.create')
        <a href="{{ route('partners.create', ['type' => 'customer']) }}" class="btn">
            <i class="ti ti-user-plus me-1"></i> Pelanggan
        </a>
        <a href="{{ route('partners.create', ['type' => 'supplier']) }}" class="btn btn-primary">
            <i class="ti ti-building-store me-1"></i> Pemasok
        </a>
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
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Kode, nama, kontak…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Tipe</label>
                    <select name="type" class="form-select">
                        <option value="">Semua</option>
                        <option value="customer" @selected(request('type') === 'customer')>Pelanggan</option>
                        <option value="supplier" @selected(request('type') === 'supplier')>Pemasok</option>
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
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    @if(collect(request()->query())->filter()->isNotEmpty())
                        <a href="{{ route('partners.index') }}" class="btn btn-link">Reset</a>
                    @endif
                </div>
                <div class="col text-end text-secondary">{{ $partners->total() }} mitra</div>
            </form>
        </div>

        @if($partners->isEmpty())
            <div class="card-body">
                <x-empty icon="ti ti-users-off" title="Belum ada mitra bisnis"
                         message="Tambahkan pelanggan atau pemasok untuk mulai bertransaksi." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Kontak</th>
                        <th>Kota</th>
                        <th>Termin</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($partners as $partner)
                        <tr>
                            <td class="fw-bold">{{ $partner->code }}</td>
                            <td><a href="{{ route('partners.show', $partner) }}">{{ $partner->name }}</a></td>
                            <td>
                                <span class="badge bg-{{ $partner->type === 'customer' ? 'green' : ($partner->type === 'supplier' ? 'azure' : 'purple') }}-lt">
                                    {{ $partner->typeLabel() }}
                                </span>
                            </td>
                            <td>
                                <div>{{ $partner->contact_person ?? '—' }}</div>
                                <div class="text-secondary small">{{ $partner->phone }}</div>
                            </td>
                            <td class="text-secondary">{{ $partner->city ?? '—' }}</td>
                            <td class="text-secondary">{{ $partner->paymentTerm?->name ?? '—' }}</td>
                            <td><x-status :value="$partner->is_active ? 'active' : 'cancelled'" :label="$partner->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('partners.show', $partner) }}">
                                            <i class="ti ti-eye me-2"></i> Detail
                                        </a>
                                        @can('partner.edit')
                                            <a class="dropdown-item" href="{{ route('partners.edit', $partner) }}">
                                                <i class="ti ti-edit me-2"></i> Ubah
                                            </a>
                                        @endcan
                                        @can('partner.delete')
                                            <x-delete-form :action="route('partners.destroy', $partner)" label="Hapus" />
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">{{ $partners->links() }}</div>
        @endif
    </x-card>
@endsection
