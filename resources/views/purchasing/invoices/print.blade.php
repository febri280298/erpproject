@extends('layouts.print')

@section('title', 'Faktur Pembelian ' . $document->invoice_no)
@section('doc-title', 'Faktur Pembelian')
@section('doc-subtitle', $document->invoice_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->supplier,
        'partnerRole' => 'Pemasok',
        'meta' => [
            'Nomor Faktur' => $document->invoice_no,
            'Faktur Pemasok' => $document->supplier_invoice_no ?? '—',
            'Tanggal' => fdate($document->date),
            'Jatuh Tempo' => fdate($document->due_date),
            'No. PO' => $document->purchaseOrder?->po_no ?? '—',
        ],
        'signatures' => ['Dibuat oleh', 'Diperiksa oleh', 'Disetujui oleh'],
    ])
@endsection
