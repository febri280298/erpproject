@extends('layouts.app')

@section('title', 'Upload Produk')
@section('pretitle', 'Data Master')

@section('actions')
    <a href="{{ route('products.index') }}" class="btn"><i class="ti ti-arrow-left me-1"></i> Kembali ke Produk</a>
@endsection

@section('content')
    @php $result = session('import_result'); @endphp

    {{-- Hasil impor ditampilkan lebih dulu agar langsung terlihat setelah unggah --}}
    @if($result)
        <x-card title="Hasil Upload" class="mb-3">
            <div class="row row-cards mb-3">
                <div class="col-6 col-md-3">
                    <x-stat label="Produk baru" :value="$result['created']" icon="ti ti-plus" color="green" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat label="Diperbarui" :value="$result['updated']" icon="ti ti-refresh" color="blue" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat label="Dilewati" :value="$result['skipped']" icon="ti ti-arrow-right" color="secondary" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat label="Gagal" :value="count($result['errors'])" icon="ti ti-alert-triangle"
                            :color="count($result['errors']) > 0 ? 'red' : 'secondary'" />
                </div>
            </div>

            @if(count($result['errors']) > 0)
                <h4>Baris yang gagal</h4>
                <div class="table-responsive">
                    <table class="table table-vcenter table-sm">
                        <thead>
                        <tr><th style="width:6rem">Baris</th><th style="width:10rem">SKU</th><th>Alasan</th></tr>
                        </thead>
                        <tbody>
                        @foreach($result['errors'] as $error)
                            <tr>
                                <td class="fw-bold">{{ $error['row'] }}</td>
                                <td>{{ $error['sku'] ?: '—' }}</td>
                                <td class="text-danger">{{ $error['message'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-secondary small mb-0">
                    Nomor baris mengacu pada baris di berkas Excel Anda (baris 1 adalah judul kolom).
                    Perbaiki baris tersebut lalu unggah ulang — baris yang sudah masuk tidak akan terduplikasi
                    selama opsi "perbarui data lama" aktif.
                </p>
            @else
                <div class="alert alert-success mb-0">Semua baris berhasil diproses.</div>
            @endif
        </x-card>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data">
                @csrf
                <x-card title="1. Unggah Berkas">
                    <div class="mb-3">
                        <label class="form-label required" for="file">Berkas Excel / CSV</label>
                        <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror"
                               accept=".xlsx,.xls,.csv,.txt" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="form-hint">Format .xlsx, .xls, atau .csv. Maksimal 10 MB.</small>
                    </div>

                    <label class="form-check form-switch">
                        <input type="hidden" name="update_existing" value="0">
                        <input type="checkbox" name="update_existing" value="1" class="form-check-input" checked>
                        <span class="form-check-label">Perbarui produk yang SKU-nya sudah ada</span>
                        <small class="d-block form-hint">
                            Jika dimatikan, baris dengan SKU yang sudah terdaftar akan dilewati, bukan ditimpa.
                        </small>
                    </label>

                    <label class="form-check form-switch mt-2">
                        <input type="hidden" name="create_missing_refs" value="0">
                        <input type="checkbox" name="create_missing_refs" value="1" class="form-check-input">
                        <span class="form-check-label">Buat kategori &amp; satuan baru bila belum ada</span>
                        <small class="d-block form-hint">
                            Praktis untuk impor pertama. Pajak tetap harus sudah terdaftar agar tarifnya tidak salah.
                        </small>
                    </label>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-between align-items-center">
                            <a href="{{ route('products.template') }}" class="btn btn-outline-primary">
                                <i class="ti ti-download me-1"></i> Unduh Template
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-upload me-1"></i> Proses Upload
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>

            <x-card title="Format Kolom" flush class="mt-3">
                <div class="table-responsive">
                    <table class="table table-vcenter table-sm card-table">
                        <thead>
                        <tr><th>Kolom</th><th>Wajib</th><th>Keterangan</th></tr>
                        </thead>
                        <tbody>
                        @php
                            $columns = [
                                ['sku', true, 'Kode unik produk. Dipakai sebagai penanda saat memperbarui.'],
                                ['nama', true, 'Nama produk.'],
                                ['tipe', false, '"stock" untuk barang, "jasa" untuk layanan. Kosong dianggap barang.'],
                                ['kategori', false, 'Kode atau nama kategori, mis. ELK atau Elektronik.'],
                                ['satuan', false, 'Kode atau nama satuan, mis. PCS.'],
                                ['pajak', false, 'Kode pajak, mis. PPN11. Kosong memakai pajak default.'],
                                ['harga_beli', true, 'Angka. Boleh 1250000, 1.250.000, atau 1.250.000,50. Tulisan bukan angka akan ditolak, bukan dianggap nol.'],
                                ['harga_jual', true, 'Angka, format sama seperti harga beli.'],
                                ['stok_min', false, 'Batas minimum untuk peringatan stok menipis.'],
                                ['stok_max', false, 'Batas maksimum stok.'],
                                ['barcode', false, 'Kode barcode bila ada.'],
                                ['deskripsi', false, 'Keterangan tambahan.'],
                                ['aktif', false, '"ya" atau "tidak". Kosong dianggap aktif.'],
                            ];
                        @endphp
                        @foreach($columns as [$name, $required, $note])
                            <tr>
                                <td><code>{{ $name }}</code></td>
                                <td>
                                    @if($required)
                                        <span class="badge bg-red-lt">Wajib</span>
                                    @else
                                        <span class="text-secondary">opsional</span>
                                    @endif
                                </td>
                                <td class="text-secondary">{{ $note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card title="Cara Pakai">
                <ol class="ps-3 mb-0">
                    <li class="mb-2"><strong>Unduh template</strong> atau ekspor data produk yang sudah ada.</li>
                    <li class="mb-2">Isi barisnya di Excel. Hapus dua baris contoh pada template.</li>
                    <li class="mb-2">Simpan sebagai <code>.xlsx</code> atau <code>.csv</code>.</li>
                    <li class="mb-2">Unggah di form sebelah kiri, lalu klik <strong>Proses Upload</strong>.</li>
                    <li>Periksa hasilnya. Baris yang gagal akan dirinci beserta alasannya.</li>
                </ol>

                <div class="alert alert-info mt-3 mb-0">
                    Ingin memperbarui harga massal? Klik <strong>Ekspor Data Produk</strong>,
                    ubah kolom harga di Excel, lalu unggah kembali berkas yang sama.
                    Kolom-kolomnya sudah cocok.
                </div>

                <x-slot:footer>
                    <a href="{{ route('products.export') }}" class="btn btn-outline-secondary w-100">
                        <i class="ti ti-file-spreadsheet me-1"></i> Ekspor Data Produk
                    </a>
                </x-slot:footer>
            </x-card>

            <x-card title="Referensi yang Tersedia" class="mt-3">
                <p class="text-secondary small">Nilai berikut dapat langsung dipakai di kolom terkait.</p>

                <h4 class="mt-3">Kategori</h4>
                @forelse($categories as $code => $name)
                    <span class="badge bg-blue-lt me-1 mb-1">{{ $code }} — {{ $name }}</span>
                @empty
                    <span class="text-secondary small">Belum ada kategori.</span>
                @endforelse

                <h4 class="mt-3">Satuan</h4>
                @forelse($uoms as $code => $name)
                    <span class="badge bg-green-lt me-1 mb-1">{{ $code }}</span>
                @empty
                    <span class="text-secondary small">Belum ada satuan.</span>
                @endforelse

                <h4 class="mt-3">Pajak</h4>
                @forelse($taxes as $code => $name)
                    <span class="badge bg-orange-lt me-1 mb-1">{{ $code }} — {{ $name }}</span>
                @empty
                    <span class="text-secondary small">Belum ada pajak.</span>
                @endforelse
            </x-card>
        </div>
    </div>
@endsection
