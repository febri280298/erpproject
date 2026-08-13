@props([
    'name',
    'label' => null,
    'value' => false,
    'help' => null,
    'col' => 'col-md-6',
])

@php
    $id = $attributes->get('id', $name);
    $checked = (bool) old($name, $value);
@endphp

<div class="{{ $col }}">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="form-check form-switch mt-md-4">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1"
               {{ $attributes->merge(['class' => 'form-check-input']) }} @checked($checked)>
        <span class="form-check-label">{{ $label }}</span>
    </label>
    @if($help)<small class="form-hint">{{ $help }}</small>@endif
    @error($name)<div class="text-danger small">{{ $message }}</div>@enderror
</div>
