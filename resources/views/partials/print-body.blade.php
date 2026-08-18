@php
    /**
     * Body shared by every printable document.
     *
     * Column widths are percentages, not rem, so the table always fits the
     * 186 mm of usable width on A4 portrait regardless of browser zoom.
     *
     * @var array  $meta        label => value pairs shown on the right
     * @var object $partner     the counterparty (name/address/phone)
     * @var string $partnerRole heading above the counterparty block
     * @var bool   $showPrice   include money columns and the totals block
     * @var array  $signatures  ['Dibuat oleh', 'Disetujui oleh', …]
     */
    $showPrice = $showPrice ?? true;
    $signatures = $signatures ?? ['Dibuat oleh', 'Disetujui oleh', 'Diterima oleh'];
    $footerNote = $footerNote ?? null;
@endphp

<div class="row mb-4 avoid-break">
    <div class="col-6">
        <div class="text-uppercase text-secondary" style="font-size:8pt">{{ $partnerRole }}</div>
        <div class="fw-bold">{{ $partner?->name ?? '—' }}</div>
        <div style="font-size:8.5pt; line-height:1.35">
            {!! nl2br(e($partner?->address ?? '')) !!}
            @if($partner?->phone)<br>Telp: {{ $partner->phone }}@endif
            @if($partner?->npwp)<br>NPWP: {{ $partner->npwp }}@endif
        </div>
    </div>
    <div class="col-6">
        <table class="table table-sm table-borderless mb-0">
            @foreach($meta as $label => $value)
                <tr>
                    <td class="text-secondary p-0" style="width:42%">{{ $label }}</td>
                    <td class="p-0">: {{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</div>

<table class="table table-bordered table-sm">
    <thead>
    <tr>
        <th style="width:4%">#</th>
        <th>Deskripsi</th>
        <th class="text-num" style="width:{{ $showPrice ? '9%' : '14%' }}">Qty</th>
        <th style="width:{{ $showPrice ? '8%' : '14%' }}">Satuan</th>
        @if($showPrice)
            <th class="text-num" style="width:15%">Harga</th>
            <th class="text-num" style="width:7%">Disc</th>
            <th class="text-num" style="width:17%">Jumlah</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $index => $item)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                {{ $item->product?->name ?? $item->description }}
                @if($item->description && $item->description !== $item->product?->name)
                    <div class="text-secondary" style="font-size:8pt">{{ $item->description }}</div>
                @endif
                <div class="text-secondary" style="font-size:8pt">{{ $item->product?->sku }}</div>
            </td>
            <td class="text-num">{{ fnum($item->quantity) }}</td>
            <td>{{ $item->product?->uom?->code ?? '—' }}</td>
            @if($showPrice)
                <td class="text-num">{{ rupiah($item->unit_price, null, false) }}</td>
                <td class="text-num">{{ fnum($item->discount_percent) }}%</td>
                <td class="text-num">{{ rupiah($item->subtotal, null, false) }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>

@if($showPrice)
    <div class="row avoid-break">
        <div class="col-7">
            @if($document->notes)
                <div class="mb-2"><strong>Catatan:</strong>
                    <div style="font-size:8.5pt">{!! nl2br(e($document->notes)) !!}</div>
                </div>
            @endif
            @if(! empty($document->terms))
                <div class="mb-2"><strong>Syarat &amp; Ketentuan:</strong>
                    <div style="font-size:8.5pt">{!! nl2br(e($document->terms)) !!}</div>
                </div>
            @endif
            <div style="font-size:8.5pt" class="mt-3">
                <strong>Terbilang:</strong> <em>{{ terbilang((float) $document->total) }}</em>
            </div>
        </div>
        <div class="col-5">
            <table class="table table-sm mb-0">
                <tr><td class="text-secondary">Subtotal (DPP)</td><td class="text-num">{{ rupiah($document->subtotal, null, false) }}</td></tr>
                @if((float) ($document->dpp_other_amount ?? 0) > 0
                    && abs((float) $document->dpp_other_amount - (float) $document->subtotal) >= 0.01)
                    <tr>
                        <td class="text-secondary">DPP Nilai Lain ({{ app(\App\Services\LineItemCalculator::class)->ratioLabel() }})</td>
                        <td class="text-num">{{ rupiah($document->dpp_other_amount, null, false) }}</td>
                    </tr>
                @endif
                @if((float) $document->discount_amount > 0)
                    <tr><td class="text-secondary">Diskon</td><td class="text-num">({{ rupiah($document->discount_amount, null, false) }})</td></tr>
                @endif
                @if((float) $document->shipping_cost > 0)
                    <tr><td class="text-secondary">Biaya Kirim</td><td class="text-num">{{ rupiah($document->shipping_cost, null, false) }}</td></tr>
                @endif
                <tr><td class="text-secondary">PPN</td><td class="text-num">{{ rupiah($document->tax_amount, null, false) }}</td></tr>
                <tr class="fw-bold" style="border-top:1pt solid #000">
                    <td>TOTAL</td><td class="text-num">{{ rupiah($document->total) }}</td>
                </tr>
                {{-- PPh 23 disetor sendiri oleh customer, jadi yang ditransfer lebih kecil --}}
                @if((float) ($document->wht_amount ?? 0) > 0)
                    <tr>
                        <td class="text-secondary">PPh 23 ({{ fnum($document->wht_rate) }}%)</td>
                        <td class="text-num">({{ rupiah($document->wht_amount, null, false) }})</td>
                    </tr>
                    <tr class="fw-bold" style="border-top:.5pt solid #666">
                        <td>DIBAYAR</td><td class="text-num">{{ rupiah($document->amountDue()) }}</td>
                    </tr>
                @endif
            </table>
        </div>
    </div>
@elseif($document->notes)
    <div class="mt-3 avoid-break"><strong>Catatan:</strong> <span style="font-size:8.5pt">{{ $document->notes }}</span></div>
@endif

@if($footerNote)
    <p class="text-secondary mt-4" style="font-size:8.5pt">{{ $footerNote }}</p>
@endif

<div class="row signatures text-center">
    @foreach($signatures as $signature)
        <div class="col">
            <div class="text-secondary" style="font-size:8.5pt">{{ $signature }}</div>
            <div style="height:18mm"></div>
            <div style="border-top:.5pt solid #666; width:75%; margin:0 auto"></div>
        </div>
    @endforeach
</div>
