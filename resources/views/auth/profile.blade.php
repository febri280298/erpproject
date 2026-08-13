@extends('layouts.app')

@section('title', 'Profil Saya')
@section('pretitle', 'Akun')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <x-card title="Informasi Akun">
                    <div class="row g-3">
                        <x-form.input name="name" label="Nama Lengkap" :value="$user->name" required />
                        <x-form.input name="email" label="Email" type="email" :value="$user->email" required />
                        <x-form.input name="phone" label="Telepon" :value="$user->phone" />
                        <div class="col-md-6">
                            <label class="form-label" for="avatar">Foto Profil</label>
                            <input type="file" name="avatar" id="avatar" class="form-control" accept="image/*">
                            @error('avatar')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="mt-3">
                @csrf @method('PUT')
                <x-card title="Ubah Kata Sandi">
                    <div class="row g-3">
                        <x-form.input name="current_password" label="Kata Sandi Saat Ini" type="password" required col="col-12" autocomplete="current-password" />
                        <x-form.input name="password" label="Kata Sandi Baru" type="password" required autocomplete="new-password" />
                        <x-form.input name="password_confirmation" label="Konfirmasi Kata Sandi" type="password" required autocomplete="new-password" />
                    </div>

                    <x-slot:footer>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Ubah Kata Sandi</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>

        <div class="col-lg-5">
            <x-card title="Ringkasan">
                <div class="text-center mb-3">
                    @if($user->avatarUrl())
                        <span class="avatar avatar-xl mb-2" style="background-image: url({{ $user->avatarUrl() }})"></span>
                    @else
                        <span class="avatar avatar-xl bg-primary-lt mb-2">{{ $user->initials() }}</span>
                    @endif
                    <h3 class="mb-0">{{ $user->name }}</h3>
                    <div class="text-secondary">{{ $user->email }}</div>
                </div>

                <dl class="row mb-0">
                    <dt class="col-5 text-secondary">Peran</dt>
                    <dd class="col-7">{{ $user->roleNames() }}</dd>
                    <dt class="col-5 text-secondary">Status</dt>
                    <dd class="col-7"><x-status :value="$user->is_active ? 'active' : 'cancelled'" :label="$user->is_active ? 'Aktif' : 'Nonaktif'" /></dd>
                    <dt class="col-5 text-secondary">Login Terakhir</dt>
                    <dd class="col-7">{{ fdatetime($user->last_login_at) }}</dd>
                    <dt class="col-5 text-secondary">Bergabung</dt>
                    <dd class="col-7">{{ fdate($user->created_at) }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
