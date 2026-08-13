@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <form class="card card-md" method="POST" action="{{ route('login') }}" autocomplete="off">
        @csrf
        <div class="card-body">
            <h2 class="card-title text-center mb-4">Masuk ke akun Anda</h2>

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       placeholder="nama@perusahaan.com" required autofocus>
            </div>

            <div class="mb-2">
                <label class="form-label" for="password">Kata Sandi</label>
                <div class="input-group input-group-flat">
                    <input type="password" id="password" name="password"
                           class="form-control @error('password') is-invalid @enderror"
                           placeholder="Kata sandi" required autocomplete="current-password">
                    <span class="input-group-text">
                        <a href="#" class="link-secondary" title="Tampilkan kata sandi"
                           onclick="event.preventDefault(); var f = document.getElementById('password'); f.type = f.type === 'password' ? 'text' : 'password';">
                            <i class="ti ti-eye"></i>
                        </a>
                    </span>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-check">
                    <input type="checkbox" name="remember" value="1" class="form-check-input" @checked(old('remember'))>
                    <span class="form-check-label">Ingat saya di perangkat ini</span>
                </label>
            </div>

            <div class="form-footer">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="ti ti-login me-1"></i> Masuk
                </button>
            </div>
        </div>
    </form>
@endsection
