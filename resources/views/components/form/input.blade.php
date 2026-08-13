@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'col' => 'col-md-6',
    'prefix' => null,
    'suffix' => null,
])

@php
    $id = $attributes->get('id', $name);
    $current = old($name, $value);
    $hasError = $errors->has($name);
@endphp

<div class="{{ $col }}">
    @if($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif

    @if($prefix || $suffix)
        <div class="input-group">
            @if($prefix)<span class="input-group-text">{{ $prefix }}</span>@endif
            <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
                   {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
                   @required($required)>
            @if($suffix)<span class="input-group-text">{{ $suffix }}</span>@endif
            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
               {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
               @required($required)>
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif

    @if($help)<small class="form-hint">{{ $help }}</small>@endif
</div>
