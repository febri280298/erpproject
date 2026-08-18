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

    @if((float) $document->paid_amount > 0)
        <div class="mt-3">
            <table class="table table-sm w-50 ms-auto">
                <tr><td class="text-secondary">Sudah dibayar</td><td class="text-num">{{ rupiah($document->paid_amount, null, false) }}</td></tr>
                <tr class="fw-bold"><td>Sisa tagihan</td><td class="text-num">{{ rupiah($document->outstandingAmount()) }}</td></tr>
            </table>
        </div>
    @endif
@endsection
