@extends('layouts.app')

@section('title', $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna')
@section('pretitle', 'Pengaturan')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">
                @csrf
                @if($user->exists) @method('PUT') @endif

                <x-card title="Data Pengguna">
                    <div class="row g-3">
                        <x-form.input name="name" label="Nama Lengkap" :value="$user->name" required />
                        <x-form.input name="email" label="Email" type="email" :value="$user->email" required />
                        <x-form.input name="phone" label="Telepon" :value="$user->phone" />
                        <x-form.checkbox name="is_active" label="Akun aktif" :value="$user->exists ? $user->is_active : true" />

                        <x-form.input name="password" label="Kata Sandi" type="password" :required="! $user->exists"
                                      autocomplete="new-password"
                                      :help="$user->exists ? 'Kosongkan jika tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter.'" />
                        <x-form.input name="password_confirmation" label="Konfirmasi Kata Sandi" type="password"
                                      :required="! $user->exists" autocomplete="new-password" />
                    </div>
                </x-card>

                <x-card title="Peran & Hak Akses" class="mt-3">
                    <div class="row g-2">
                        @foreach($roles as $name)
                            <div class="col-md-4">
                                <label class="form-selectgroup-item">
                                    <input type="checkbox" name="roles[]" value="{{ $name }}" class="form-check-input"
                                           @checked(in_array($name, old('roles', $assigned), true))>
                                    <span class="form-check-label ms-1">{{ $name }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('roles')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('users.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
@endsection
