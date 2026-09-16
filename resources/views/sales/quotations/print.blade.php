{{-- Layout ditentukan pemanggilnya: layouts.print untuk pratinjau di layar,
     layouts.pdf saat diunduh sebagai berkas. Isinya sama, hanya pembungkusnya
     yang berbeda. --}}
@extends($layout ?? 'layouts.print')

@section('title', 'Quotation ' . $document->quotation_no)
@section('doc-title', 'Quotation')
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
