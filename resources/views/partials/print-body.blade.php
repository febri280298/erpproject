@php
    /**
     * Badan yang dipakai bersama oleh setiap dokumen cetak.
     *
     * Lebar kolom dinyatakan dalam persen, bukan rem, supaya tabelnya selalu
     * pas pada 186 mm lebar terpakai A4 potret berapa pun zoom peramban.
     *
     * @var array  $meta        pasangan label => nilai di kolom kanan
     * @var object $partner     lawan transaksi (nama/alamat/telepon)
     * @var string $partnerRole judul di atas blok lawan transaksi
     * @var bool   $showPrice   sertakan kolom uang dan blok total
     * @var array  $signatures  ['Dibuat oleh', 'Disetujui oleh', …]
     */
    $showPrice = $showPrice ?? true;
    $signatures = $signatures ?? ['Dibuat oleh', 'Disetujui oleh', 'Diterima oleh'];
    $footerNote = $footerNote ?? null;

    /*
     * Kolom DPP Nilai Lain hanya dicetak bila dasar pengenaan tiap baris memang
     * berbeda dari DPP-nya, dan diperiksa dari angka yang TERSIMPAN — bukan dari
     * setelan saat ini — agar cetak ulang faktur lama tetap sama persis.
     */
    $showDppOther = $showPrice && $document->items->contains(
        fn ($item) => isset($item->dpp_other)
            && round((float) $item->dpp_other, 2) !== round((float) $item->subtotal, 2)
    );

    $adaDiskonBaris = $document->items->contains(fn ($i) => (float) $i->discount_percent > 0);
@endphp

<div class="party avoid-break">
    <div>
        <div class="label">{{ $partnerRole }}</div>
        <div class="party-name">{{ $partner?->name ?? '—' }}</div>
        <div class="party-detail">
            {!! nl2br(e($partner?->address ?? '')) !!}
            @if($partner?->phone)<br>Telp {{ $partner->phone }}@endif
            @if($partner?->npwp)<br>NPWP {{ $partner->npwp }}@endif
        </div>
    </div>
    <div>
        <table class="meta">
            @foreach($meta as $label => $value)
                <tr>
                    <td class="k">{{ $label }}</td>
                    <td class="v">{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</div>

<table class="items">
    <thead>
    <tr>
        <th style="width:5%">#</th>
        <th>Deskripsi</th>
        <th class="num" style="width:{{ $showPrice ? '8%' : '14%' }}">Qty</th>
        <th style="width:{{ $showPrice ? '8%' : '14%' }}">Satuan</th>
        @if($showPrice)
            <th class="num" style="width:{{ $showDppOther ? '13%' : '16%' }}">Harga</th>
            @if($showDppOther)
                <th class="num" style="width:15%">DPP Nilai Lain</th>
            @endif
            {{-- Kolom diskon hanya memakan ruang bila tidak ada baris yang didiskon. --}}
            @if($adaDiskonBaris)<th class="num" style="width:7%">Disc</th>@endif
            <th class="num" style="width:{{ $showDppOther ? '14%' : '18%' }}">Jumlah</th>
        @endif
    </tr>
    </thead>
    <tbody>
    @foreach($document->items as $index => $item)
        <tr>
            <td class="muted">{{ $index + 1 }}</td>
            <td>
                <div class="item-name">{{ $item->product?->name ?? $item->description }}</div>
                <div class="item-sub">
                    @if($item->product?->sku){{ $item->product->sku }}@endif
                    @if($item->description && $item->description !== $item->product?->name)
                        @if($item->product?->sku) &middot; @endif{{ $item->description }}
                    @endif
                </div>
            </td>
            <td class="num">{{ fnum($item->quantity) }}</td>
            <td>{{ $item->product?->uom?->code ?? '—' }}</td>
            @if($showPrice)
                <td class="num">{{ rupiah($item->unit_price, null, false) }}</td>
                @if($showDppOther)
                    <td class="num">{{ rupiah($item->dpp_other, null, false) }}</td>
                @endif
                @if($adaDiskonBaris)
                    <td class="num">{{ (float) $item->discount_percent > 0 ? fnum($item->discount_percent).'%' : '—' }}</td>
                @endif
                <td class="num">{{ rupiah($item->subtotal, null, false) }}</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>

@if($showPrice)
    @php
        $adaPphOrBayar = (float) ($document->wht_amount ?? 0) > 0
            || (float) ($document->paid_amount ?? 0) > 0;
    @endphp

    <div class="party avoid-break" style="margin-bottom:0">
        <div>
            @if($document->notes)
                <div class="note-block">
                    <strong>Catatan</strong><br>{!! nl2br(e($document->notes)) !!}
                </div>
            @endif
            @if(! empty($document->terms))
                <div class="note-block">
                    <strong>Syarat &amp; Ketentuan</strong><br>{!! nl2br(e($document->terms)) !!}
                </div>
            @endif

            {{-- Terbilang punya bobot hukum pada faktur, jadi diberi bingkainya sendiri. --}}
            <div class="terbilang">
                <span class="label">Terbilang</span><br>
                <em>{{ terbilang((float) $document->total) }}</em>
            </div>
        </div>

        <div>
            <table class="totals">
                <tr>
                    <td class="t-label">Subtotal (DPP)</td>
                    <td class="t-value">{{ rupiah($document->subtotal, null, false) }}</td>
                </tr>

                @if((float) ($document->dpp_other_amount ?? 0) > 0
                    && abs((float) $document->dpp_other_amount - (float) $document->subtotal) >= 0.01)
                    <tr>
                        <td class="t-label">
                            DPP Nilai Lain
                            ({{ app(\App\Services\LineItemCalculator::class)->ratioLabel() }})
                        </td>
                        <td class="t-value">{{ rupiah($document->dpp_other_amount, null, false) }}</td>
                    </tr>
                @endif

                @if((float) $document->discount_amount > 0)
                    <tr>
                        <td class="t-label">Diskon</td>
                        <td class="t-value">({{ rupiah($document->discount_amount, null, false) }})</td>
                    </tr>
                @endif

                @if((float) $document->shipping_cost > 0)
                    <tr>
                        <td class="t-label">Biaya Kirim</td>
                        <td class="t-value">{{ rupiah($document->shipping_cost, null, false) }}</td>
                    </tr>
                @endif

                <tr>
                    <td class="t-label">PPN</td>
                    <td class="t-value">{{ rupiah($document->tax_amount, null, false) }}</td>
                </tr>

                <tr class="grand">
                    <td>TOTAL</td>
                    <td class="t-value">{{ rupiah($document->total) }}</td>
                </tr>

                {{-- PPh 23 disetor sendiri oleh customer, jadi yang ditransfer lebih kecil. --}}
                @if((float) ($document->wht_amount ?? 0) > 0)
                    <tr>
                        <td class="t-label">PPh 23 ({{ fnum($document->wht_rate) }}%)</td>
                        <td class="t-value">({{ rupiah($document->wht_amount, null, false) }})</td>
                    </tr>
                @endif

                @if((float) ($document->paid_amount ?? 0) > 0)
                    <tr>
                        <td class="t-label">Sudah dibayar</td>
                        <td class="t-value">({{ rupiah($document->paid_amount, null, false) }})</td>
                    </tr>
                @endif

                {{-- Baris terakhir adalah angka yang benar-benar harus ditransfer.
                     Hanya muncul bila memang berbeda dari TOTAL, supaya tidak ada
                     dua angka besar yang bersaing tanpa alasan. --}}
                @if($adaPphOrBayar)
                    <tr class="due">
                        <td>{{ (float) ($document->paid_amount ?? 0) > 0 ? 'SISA TAGIHAN' : 'DIBAYAR' }}</td>
                        <td class="t-value">
                            {{ rupiah(method_exists($document, 'outstandingAmount') && (float) ($document->paid_amount ?? 0) > 0
                                ? $document->outstandingAmount()
                                : (method_exists($document, 'amountDue') ? $document->amountDue() : $document->total)) }}
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>
@elseif($document->notes)
    <div class="note-block avoid-break"><strong>Catatan</strong><br>{{ $document->notes }}</div>
@endif

@if($footerNote)
    <p class="note-block mt-3">{{ $footerNote }}</p>
@endif

<div class="signatures">
    @foreach($signatures as $i => $signature)
        <div>
            {{-- Tanggal hanya di kolom terakhir, seperti lazimnya surat resmi;
                 diulang di tiap kolom justru terbaca berantakan. Kota sengaja
                 tidak dicantumkan karena profil perusahaan belum menyimpannya —
                 menebaknya dari alamat lebih berisiko salah daripada berguna. --}}
            <div class="label" style="visibility:{{ $loop->last ? 'visible' : 'hidden' }}">
                {{ fdate($document->date ?? now()) }}
            </div>
            <div style="font-size:8.5pt; margin-top:1mm">{{ $signature }}</div>
            <div style="height:17mm"></div>
            <div class="sign-rule">Nama &amp; Tanda Tangan</div>
        </div>
    @endforeach
</div>
