@extends('layouts.print')

@section('title', 'Surat Jalan ' . $document->do_no)
@section('doc-title', 'Surat Jalan')
@section('doc-subtitle', $document->do_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->customer,
        'partnerRole' => 'Kirim Kepada',
        // Tujuan surat jalan sering bukan alamat customer yang terdaftar:
        // lokasi proyek, gudang cabang, atau alamat titip. Pengemudi membawa
        // lembar ini, jadi yang tercetak harus alamat yang benar-benar dituju.
        'partnerAddress' => $document->shipping_address ?: $document->customer?->address,
        'showPrice' => false,
        'meta' => [
            'Nomor SJ' => $document->do_no,
            'Tanggal' => fdate($document->date),
            'No. SO' => $document->salesOrder?->so_no ?? '—',
            'No. PO Pelanggan' => $document->customer_po_no ?? '—',
            'Pengemudi' => $document->driver_name ?? '—',
            'No. Kendaraan' => $document->vehicle_no ?? '—',
        ],
        'signatures' => ['Pengirim', 'Pengemudi', 'Penerima'],
        'footerNote' => 'Barang telah diterima dalam keadaan baik dan sesuai jumlah.',
    ])
@endsection
