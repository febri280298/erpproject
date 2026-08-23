@props([
    'title' => 'Alur Kerja',
    'subtitle' => null,
    'steps' => [],
])

@php
    /**
     * Alur kerja satu modul beserta jumlah dokumen yang tertahan di tiap tahap.
     *
     * Angka yang ditonjolkan adalah "perlu tindakan", bukan total dokumen —
     * yang dicari orang saat membuka dashboard adalah apa yang macet, bukan
     * berapa banyak yang sudah beres.
     *
     * @var array<int,array{label:string,hint?:string,count:int,href?:string,icon?:string,module?:string}> $steps
     */
    $steps = array_values(array_filter($steps, fn ($s) => ($s['tampil'] ?? true)));
@endphp

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ $title }}</h3>
            @if($subtitle)<div class="text-secondary small">{{ $subtitle }}</div>@endif
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2 align-items-stretch">
            @foreach($steps as $i => $step)
                @php
                    $count = (int) ($step['count'] ?? 0);
                    // Tahap yang kosong tidak diberi warna: yang berwarna berarti
                    // ada yang harus dikerjakan, sehingga terbaca sekali lihat.
                    $color = $count > 0 ? ($step['color'] ?? 'orange') : 'secondary';
                @endphp

                <div class="col">
                    <a href="{{ $step['href'] ?? '#' }}"
                       class="card card-sm h-100 text-decoration-none {{ $count > 0 ? 'border-'.$color : '' }}">
                        <div class="card-body text-center p-2">
                            <div class="text-secondary small text-uppercase" style="font-size:.65rem;letter-spacing:.04em">
                                {{ $i + 1 }}. {{ $step['label'] }}
                            </div>
                            <div class="fs-2 fw-bold text-{{ $count > 0 ? $color : 'secondary' }} lh-1 my-1">
                                {{ fnum($count, 0) }}
                            </div>
                            <div class="text-secondary" style="font-size:.7rem">
                                {{ $step['hint'] ?? 'menunggu' }}
                            </div>
                        </div>
                    </a>
                </div>

                @if(! $loop->last)
                    <div class="col-auto d-none d-xl-flex align-items-center text-secondary px-0">
                        <i class="ti ti-chevron-right"></i>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
