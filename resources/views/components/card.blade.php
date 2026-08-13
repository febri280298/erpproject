@props([
    'title' => null,
    'subtitle' => null,
    'actions' => null,
    'footer' => null,
    'bodyClass' => 'card-body',
    'flush' => false,
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || $actions)
        <div class="card-header">
            <div>
                @if($title)<h3 class="card-title">{{ $title }}</h3>@endif
                @if($subtitle)<p class="card-subtitle">{{ $subtitle }}</p>@endif
            </div>
            @if($actions)
                <div class="card-actions">{{ $actions }}</div>
            @endif
        </div>
    @endif

    @if($flush)
        {{ $slot }}
    @else
        <div class="{{ $bodyClass }}">{{ $slot }}</div>
    @endif

    @if($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endif
</div>
