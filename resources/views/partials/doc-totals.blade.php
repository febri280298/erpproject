@php
    /** Money summary block shared by document show + print views. */
    $showPaid = $showPaid ?? false;
@endphp

<table class="table table-sm mb-0">
    <tr>
        <td class="text-secondary">Subtotal</td>
        <td class="text-num">{{ rupiah($document->subtotal, 2) }}</td>
    </tr>
    @if((float) $document->discount_amount > 0)
        <tr>
            <td class="text-secondary">Diskon Nota</td>
            <td class="text-num text-danger">− {{ rupiah($document->discount_amount, 2) }}</td>
        </tr>
    @endif
    @if((float) $document->shipping_cost > 0)
        <tr>
            <td class="text-secondary">Biaya Kirim</td>
            <td class="text-num">{{ rupiah($document->shipping_cost, 2) }}</td>
        </tr>
    @endif
    <tr>
        <td class="text-secondary">Total Pajak</td>
        <td class="text-num">{{ rupiah($document->tax_amount, 2) }}</td>
    </tr>
    <tr class="fw-bold border-top">
        <td>Total</td>
        <td class="text-num fs-3">{{ rupiah($document->total, 2) }}</td>
    </tr>
    @if($showPaid)
        <tr>
            <td class="text-secondary">Dibayar</td>
            <td class="text-num text-success">{{ rupiah($document->paid_amount, 2) }}</td>
        </tr>
        <tr class="fw-bold">
            <td>Sisa Tagihan</td>
            <td class="text-num {{ $document->outstandingAmount() > 0 ? 'text-danger' : 'text-success' }}">
                {{ rupiah($document->outstandingAmount(), 2) }}
            </td>
        </tr>
    @endif
</table>
