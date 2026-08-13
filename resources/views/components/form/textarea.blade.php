@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 3,
    'required' => false,
    'help' => null,
    'col' => 'col-12',
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

    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}"
              {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}
              @required($required)>{{ $current }}</textarea>

    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($help)<small class="form-hint">{{ $help }}</small>@endif
</div>
