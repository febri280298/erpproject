@extends('layouts.print')

@section('title', 'Invoice ' . $document->invoice_no)
{{-- Semua tipe dicetak sebagai "Invoice"; yang membedakan tampak dari blok
     pajaknya, bukan dari judulnya. Faktur pajak resmi adalah dokumen ber-nomor
     seri DJP yang terbit dari e-Faktur, bukan cetakan ini. --}}
@section('doc-title', match($document->invoice_type) {
    'jasa' => 'Invoice Jasa',
    default => 'Invoice',
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
            'No. PO Pelanggan' => $document->customer_po_no ?? '—',
        ],
        'signatures' => ['Hormat kami'],
        'showBank' => true,
        'footerNote' => setting('invoice_footer_note'),
    ])
@endsection
