@extends('layouts.print')

@section('title', 'Faktur ' . $document->invoice_no)
@section('doc-title', match($document->invoice_type) {
    'non_ppn' => 'Invoice',
    'jasa' => 'Invoice Jasa',
    default => 'Faktur Pajak',
})
@section('doc-subtitle', $document->invoice_no)

@section('content')
    @include('partials.print-body', [
        'partner' => $document->customer,
        'partnerRole' => 'Kepada Yth.',
        'meta' => [
            'Nomor Faktur' => $document->invoice_no,
            'Tanggal' => fdate($document->date),
            'Jatuh Tempo' => fdate($document->due_date),
            'No. SO' => $document->salesOrder?->so_no ?? '—',
        ],
        'signatures' => ['Hormat kami', 'Penerima'],
        'footerNote' => setting('invoice_footer_note'),
    ])
@endsection
