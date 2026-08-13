@extends('layouts.print')

@section('title', 'Penerimaan Barang ' . $document->grn_no)
@section('doc-title', 'Bukti Penerimaan Barang')
@section('doc-subtitle', $document->grn_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->supplier,
        'partnerRole' => 'Diterima dari',
        'showPrice' => false,
        'meta' => [
            'Nomor GRN' => $document->grn_no,
            'Tanggal' => fdate($document->date),
            'No. PO' => $document->purchaseOrder?->po_no ?? '—',
            'No. SJ Pemasok' => $document->supplier_do_no ?? '—',
            'Gudang' => $document->warehouse?->name,
        ],
        'signatures' => ['Penerima', 'Kepala Gudang', 'Pengirim'],
    ])
@endsection
