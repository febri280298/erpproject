@extends('layouts.print')

@section('title', 'Penawaran ' . $document->quotation_no)
@section('doc-title', 'Surat Penawaran Harga')
@section('doc-subtitle', $document->quotation_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->customer,
        'partnerRole' => 'Kepada Yth.',
        'meta' => [
            'Nomor' => $document->quotation_no,
            'Tanggal' => fdate($document->date),
            'Berlaku Sampai' => fdate($document->valid_until),
        ],
        'signatures' => ['Hormat kami', 'Menyetujui'],
        'footerNote' => setting('invoice_footer_note'),
    ])
@endsection
