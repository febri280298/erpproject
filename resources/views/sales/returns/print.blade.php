@extends('layouts.print')

@section('title', $document->return_no)
@section('doc-title', $document->hasCreditNote() ? 'Retur Penjualan & Nota Kredit' : 'Bukti Retur Penjualan')
@section('doc-subtitle', $document->return_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->customer,
        'partnerRole' => 'Diterima dari',
        'meta' => array_filter([
            'Tanggal Retur' => fdate($document->date),
            'No. Surat Jalan' => $document->deliveryOrder?->do_no,
            'No. Faktur' => $document->hasCreditNote() ? $document->salesInvoice?->invoice_no : null,
            'Gudang' => $document->warehouse?->name,
            'Alasan' => $document->reason,
        ]),
        'showPrice' => true,
        'signatures' => ['Diserahkan oleh', 'Diperiksa oleh', 'Diterima oleh'],
        'footerNote' => $document->hasCreditNote()
            ? 'Nilai retur di atas dipotongkan sebagai nota kredit pada faktur '.$document->salesInvoice?->invoice_no.'.'
            : 'Retur ini tidak menerbitkan nota kredit; hanya persediaan yang disesuaikan.',
    ])
@endsection
