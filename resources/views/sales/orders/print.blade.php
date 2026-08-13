@extends('layouts.print')

@section('title', 'Pesanan Penjualan ' . $document->so_no)
@section('doc-title', 'Konfirmasi Pesanan')
@section('doc-subtitle', $document->so_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->customer,
        'partnerRole' => 'Kepada Yth.',
        'meta' => [
            'Nomor SO' => $document->so_no,
            'Tanggal' => fdate($document->date),
            'Rencana Kirim' => fdate($document->delivery_date),
            'PO Pelanggan' => $document->customer_po_no ?? '—',
            'Termin' => $document->paymentTerm?->name ?? '—',
        ],
        'signatures' => ['Hormat kami', 'Pelanggan'],
        'footerNote' => setting('invoice_footer_note'),
    ])
@endsection
