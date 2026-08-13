@extends('layouts.print')

@section('title', 'Surat Jalan ' . $document->do_no)
@section('doc-title', 'Surat Jalan')
@section('doc-subtitle', $document->do_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->customer,
        'partnerRole' => 'Kirim Kepada',
        'showPrice' => false,
        'meta' => [
            'Nomor SJ' => $document->do_no,
            'Tanggal' => fdate($document->date),
            'No. SO' => $document->salesOrder?->so_no ?? '—',
            'Pengemudi' => $document->driver_name ?? '—',
            'No. Kendaraan' => $document->vehicle_no ?? '—',
        ],
        'signatures' => ['Pengirim', 'Pengemudi', 'Penerima'],
        'footerNote' => 'Barang telah diterima dalam keadaan baik dan sesuai jumlah.',
    ])
@endsection
