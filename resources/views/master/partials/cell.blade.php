@php
    /** Renders one table cell from a column definition. */
    $value = isset($column['render'])
        ? $column['render']($record)
        : data_get($record, $column['key']);
    $type = $column['type'] ?? 'text';
@endphp

@switch($type)
    @case('raw')
        {!! $value !!}
        @break

    @case('bool')
        <span class="badge bg-{{ $value ? 'green' : 'secondary' }}-lt">{{ $value ? 'Aktif' : 'Nonaktif' }}</span>
        @break

    @case('money')
        {{ rupiah($value, 0) }}
        @break

    @case('number')
        {{ fnum($value) }}
        @break

    @case('percent')
        {{ fnum($value) }}%
        @break

    @case('date')
        {{ fdate($value) }}
        @break

    @case('code')
        <span class="fw-bold">{{ $value }}</span>
        @break

    @default
        {{ $value ?? '—' }}
@endswitch
