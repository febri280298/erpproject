@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => '— Pilih —',
    'required' => false,
    'help' => null,
    'col' => 'col-md-6',
])

@php
    $id = $attributes->get('id', $name);
    $current = old($name, $value);
    $hasError = $errors->has($name);
    // Accepts an Eloquent collection (pluck result), an array, or key => label pairs.
    $items = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
@endphp

<div class="{{ $col }}">
    @if($label)
        <label for="{{ $id }}" class="form-label {{ $required ? 'required' : '' }}">{{ $label }}</label>
    @endif

    <select name="{{ $name }}" id="{{ $id }}"
            {{ $attributes->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}
            @required($required)>
        @if($placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($items as $key => $text)
            <option value="{{ $key }}" @selected((string) $current === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>

    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($help)<small class="form-hint">{{ $help }}</small>@endif
</div>
