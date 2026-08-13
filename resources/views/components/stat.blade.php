@props([
    'label',
    'value',
    'icon' => 'ti ti-chart-bar',
    'color' => 'primary',
    'hint' => null,
    'href' => null,
])

<div class="card card-sm stat-tile">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <span class="bg-{{ $color }} text-white avatar"><i class="{{ $icon }}"></i></span>
            </div>
            <div class="col">
                <div class="font-weight-medium stat-value">{{ $value }}</div>
                <div class="text-secondary">{{ $label }}</div>
                @if($hint)<div class="text-secondary small mt-1">{{ $hint }}</div>@endif
            </div>
            @if($href)
                <div class="col-auto">
                    <a href="{{ $href }}" class="btn btn-icon btn-ghost-secondary" aria-label="Lihat {{ $label }}">
                        <i class="ti ti-chevron-right"></i>
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
