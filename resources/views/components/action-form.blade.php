@props([
    'action',
    'confirm' => null,
    'label' => 'Kirim',
    'icon' => null,
    'class' => 'dropdown-item',
    'method' => 'POST',
])

<form method="POST" action="{{ $action }}" @if($confirm) data-confirm="{{ $confirm }}" @endif class="d-inline">
    @csrf
    @if(strtoupper($method) !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
    <button type="submit" class="{{ $class }}">
        @if($icon)<i class="{{ $icon }} me-2"></i>@endif{{ $label }}
    </button>
</form>
