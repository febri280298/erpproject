@extends('layouts.app')

@section('title', 'Peran & Izin')
@section('pretitle', 'Pengaturan')

@section('actions')
    @can('role.create')
        <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i> Tambah Peran</a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                <tr><th>Nama Peran</th><th class="text-num">Jumlah Izin</th><th class="text-num">Jumlah Pengguna</th><th class="w-1"></th></tr>
                </thead>
                <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td class="fw-bold">
                            {{ $role->name }}
                            @if($role->name === 'Super Admin')
                                <span class="badge bg-red-lt ms-1">Akses penuh</span>
                            @endif
                        </td>
                        <td class="text-num">{{ $role->permissions_count }}</td>
                        <td class="text-num">{{ $role->users_count }}</td>
                        <td class="text-end">
                            @if($role->name !== 'Super Admin')
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @can('role.edit')
                                            <a class="dropdown-item" href="{{ route('roles.edit', $role) }}">
                                                <i class="ti ti-edit me-2"></i> Ubah Izin
                                            </a>
                                        @endcan
                                        @can('role.delete')
                                            <x-delete-form :action="route('roles.destroy', $role)" label="Hapus" />
                                        @endcan
                                    </div>
                                </div>
                            @else
                                <span class="text-secondary small">Terkunci</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
