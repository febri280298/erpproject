@php
    /**
     * Read-only line items on a document's show page.
     *
     * @var \Illuminate\Support\Collection $items
     * @var bool $showPrice   hide money columns for PR / transfer style documents
     * @var array|null $extraColumns   [['label' => …, 'render' => fn($item) => …, 'class' => …]]
     */
    $showPrice = $showPrice ?? true;
    $extraColumns = $extraColumns ?? [];
@endphp

<div class="table-responsive">
    <table class="table table-vcenter card-table">
        <thead>
        <tr>
            <th style="width:2.5rem">#</th>
            <th>Produk</th>
            <th class="text-num">Qty</th>
            <th>Satuan</th>
            @if($showPrice)
                <th class="text-num">Harga</th>
                <th class="text-num">Disc</th>
                <th class="text-num">Pajak</th>
                <th class="text-num">Jumlah</th>
            @endif
            @foreach($extraColumns as $column)
                <th class="{{ $column['class'] ?? 'text-num' }}">{{ $column['label'] }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($items as $index => $item)
            <tr>
                <td class="text-secondary">{{ $index + 1 }}</td>
                <td>
                    <div>{{ $item->product?->name ?? '—' }}</div>
                    <div class="text-secondary small">
                        {{ $item->product?->sku }}@if($item->description && $item->description !== $item->product?->name) · {{ $item->description }} @endif
                    </div>
                </td>
                <td class="text-num">{{ fnum($item->quantity) }}</td>
                <td class="text-secondary">{{ $item->product?->uom?->code ?? '—' }}</td>
                @if($showPrice)
                    <td class="text-num">{{ rupiah($item->unit_price) }}</td>
                    <td class="text-num">{{ fnum($item->discount_percent) }}%</td>
                    <td class="text-num">{{ rupiah($item->tax_amount) }}</td>
                    <td class="text-num fw-bold">{{ rupiah($item->total) }}</td>
                @endif
                @foreach($extraColumns as $column)
                    <td class="{{ $column['class'] ?? 'text-num' }}">{!! $column['render']($item) !!}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
