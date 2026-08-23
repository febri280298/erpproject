@extends('layouts.print')

@section('title', 'Pesanan Pembelian ' . $document->po_no)
@section('doc-title', 'Purchase Order')
@section('doc-subtitle', $document->po_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->supplier,
        'partnerRole' => 'Kepada Pemasok',
        'meta' => [
            'Nomor PO' => $document->po_no,
            'Tanggal' => fdate($document->date),
            'Perkiraan Tiba' => fdate($document->expected_date),
            'Gudang Tujuan' => $document->warehouse?->name,
            'Termin' => $document->paymentTerm?->name ?? '—',
        ],
        'signatures' => ['Disetujui oleh'],
    ])
@endsection
