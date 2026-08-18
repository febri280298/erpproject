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

    {{--
        Daftar akun uji coba. Hanya muncul di luar produksi: begitu APP_ENV
        disetel ke production, panel ini hilang dengan sendirinya sehingga
        kata sandi tidak pernah terpampang di server sungguhan.
    --}}
    @if(! app()->environment('production'))
        <div class="card card-md mt-3" x-data="{ buka: true }">
            <div class="card-header d-flex align-items-center py-2">
                <h3 class="card-title mb-0">
                    <i class="ti ti-key me-1"></i> Akun Uji Coba
                </h3>
                <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-ghost-secondary" @click="buka = !buka">
                        <span x-text="buka ? 'Sembunyikan' : 'Tampilkan'"></span>
                    </button>
                </div>
            </div>

            <div class="table-responsive" x-show="buka" x-cloak>
                <table class="table table-sm table-vcenter card-table">
                    <thead>
                    <tr><th>Peran</th><th>Email</th><th class="w-1"></th></tr>
                    </thead>
                    <tbody>
                    @foreach([
                        ['Super Admin', 'admin@bonecomtricom.com', 'Seluruh modul & pengaturan'],
                        ['Manajer', 'manajer@bonecomtricom.com', 'Menyetujui PO & SO, laporan'],
                        ['Pembelian', 'pembelian@bonecomtricom.com', 'Buat PO, kelola supplier'],
                        ['Gudang', 'gudang@bonecomtricom.com', 'Terima barang, surat jalan, stok'],
                        ['Penjualan', 'penjualan@bonecomtricom.com', 'Penawaran, SO, pelanggan'],
                        ['Akuntansi', 'akuntansi@bonecomtricom.com', 'Faktur, pembayaran, jurnal'],
                    ] as [$peran, $email, $tugas])
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $peran }}</div>
                                <div class="text-secondary small">{{ $tugas }}</div>
                            </td>
                            <td class="text-secondary small">{{ $email }}</td>
                            <td>
                                {{-- Mengisikan form di atas, bukan mengirimnya, agar tetap terlihat apa yang dipakai --}}
                                <button type="button" class="btn btn-sm"
                                        onclick="isiAkun('{{ $email }}')">Pakai</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer py-2 text-secondary small" x-show="buka" x-cloak>
                Kata sandi semua akun: <strong>password</strong>.
                Panel ini otomatis hilang saat <code>APP_ENV=production</code>.
            </div>
        </div>

        <script>
            function isiAkun(email) {
                document.getElementById('email').value = email;
                document.getElementById('password').value = 'password';
                document.getElementById('password').focus();
            }
        </script>
    @endif
@endsection
