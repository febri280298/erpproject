@extends('layouts.app')

@section('title', 'Buat Faktur Penjualan')
@section('pretitle', 'Penjualan')

@section('content')
    {{-- Langkah pertama: pilih sumbernya. Bentuknya sengaja sama dengan form
         Surat Jalan, yang menghadapi persoalan serupa — satu jalur menarik data
         dari dokumen hulu, satu jalur lepas untuk yang tidak punya hulu. --}}
    <div class="row row-cards">
        <div class="col-md-6">
            <x-card title="Dari Surat Jalan"
                    subtitle="Item, jumlah, dan No. PO pelanggan ditarik otomatis.">
                <p class="text-secondary">
                    Jalur yang biasa dipakai. Faktur menagih barang yang <strong>sudah
                    benar-benar dikirim</strong>, jadi jumlahnya mengikuti surat jalan —
                    bukan mengikuti pesanan yang mungkin belum keluar gudang.
                </p>
                <p class="text-secondary">
                    Beberapa surat jalan boleh ditagih sekaligus dalam satu faktur. Yang
                    sudah pernah ditagih tidak muncul lagi, jadi tidak ada barang yang
                    tertagih dua kali.
                </p>
                <a href="{{ route('sales-invoices.select-deliveries') }}" class="btn btn-primary w-100">
                    <i class="ti ti-truck-delivery me-1"></i> Pilih Surat Jalan
                </a>
            </x-card>
        </div>

        <div class="col-md-6">
            <x-card title="Tanpa Surat Jalan"
                    subtitle="Isi produk dan jumlahnya sendiri.">
                <p class="text-secondary">
                    Dipakai untuk tagihan yang memang tidak melewati pengiriman barang:
                    <strong>jasa</strong>, ongkos pasang, biaya kirim yang ditagih
                    terpisah, atau penyesuaian tagihan.
                </p>
                <p class="text-secondary">
                    Karena tidak bersandar pada surat jalan, jumlah yang ditagih tidak
                    dicocokkan dengan apa pun — periksa sendiri sebelum diposting.
                </p>
                <a href="{{ route('sales-invoices.create', ['mode' => 'manual']) }}"
                   class="btn btn-outline-primary w-100">
                    <i class="ti ti-pencil-plus me-1"></i> Buat Faktur Manual
                </a>
            </x-card>
        </div>
    </div>
@endsection
