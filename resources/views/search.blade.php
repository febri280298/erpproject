@extends('layouts.app')

@section('title', 'Hasil Pencarian')
@section('pretitle', $term ? 'Kata kunci: ' . $term : 'Pencarian')

@section('content')
    @if($term === '')
        <x-card>
            <x-empty icon="ti ti-search" title="Masukkan kata kunci"
                     message="Cari produk, mitra bisnis, atau nomor dokumen melalui kolom pencarian di atas." />
        </x-card>
    @elseif(empty($results))
        <x-card>
            <x-empty icon="ti ti-mood-empty" :title="'Tidak ada hasil untuk &quot;'.$term.'&quot;'"
                     message="Coba kata kunci lain atau periksa ejaannya." />
        </x-card>
    @else
        <div class="row g-3">
            @foreach($results as $group => $items)
                <div class="col-lg-6">
                    <x-card :title="$group" :subtitle="$items->count().' hasil'" flush>
                        <div class="list-group list-group-flush">
                            @foreach($items as $item)
                                <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action">
                                    <div class="fw-bold">{{ $item['label'] }}</div>
                                    @if($item['meta'])
                                        <div class="text-secondary small">{{ $item['meta'] }}</div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </x-card>
                </div>
            @endforeach
        </div>
    @endif
@endsection
