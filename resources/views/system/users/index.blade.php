@extends('layouts.app')

@section('title', 'Pengguna')
@section('pretitle', 'Pengaturan')

@section('actions')
    @can('user.create')
        <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i> Tambah Pengguna</a>
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
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nama / email…">
                    </div>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-1">Peran</label>
                    <select name="role" class="form-select">
                        <option value="">Semua</option>
                        @foreach($roles as $name)
                            <option value="{{ $name }}" @selected(request('role') === $name)>{{ $name }}</option>
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
                <tr><th>Nama</th><th>Email</th><th>Telepon</th><th>Peran</th><th>Login Terakhir</th><th>Status</th><th class="w-1"></th></tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if($user->avatarUrl())
                                    <span class="avatar avatar-sm" style="background-image: url({{ $user->avatarUrl() }})"></span>
                                @else
                                    <span class="avatar avatar-sm bg-primary-lt">{{ $user->initials() }}</span>
                                @endif
                                {{ $user->name }}
                            </div>
                        </td>
                        <td class="text-secondary">{{ $user->email }}</td>
                        <td class="text-secondary">{{ $user->phone ?? '—' }}</td>
                        <td>
                            @foreach($user->roles as $role)
                                <span class="badge bg-blue-lt">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td class="text-secondary">{{ fdatetime($user->last_login_at) }}</td>
                        <td><x-status :value="$user->is_active ? 'active' : 'cancelled'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @can('user.edit')
                                        <a class="dropdown-item" href="{{ route('users.edit', $user) }}">
                                            <i class="ti ti-edit me-2"></i> Ubah
                                        </a>
                                    @endcan
                                    @can('user.delete')
                                        <x-delete-form :action="route('users.destroy', $user)" label="Hapus"
                                                       confirm="Hapus pengguna ini? Data transaksi yang dibuatnya tetap tersimpan." />
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center">{{ $users->links() }}</div>
    </x-card>
@endsection
