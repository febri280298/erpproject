@props([
    'action',
    'confirm' => 'Hapus data ini? Tindakan ini tidak dapat dibatalkan.',
    'label' => null,
    'icon' => 'ti ti-trash',
    'class' => 'dropdown-item text-danger',
])

<form method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}" class="d-inline">
    @csrf
    @method('DELETE')
    <button type="submit" class="{{ $class }}">
        <i class="{{ $icon }} {{ $label ? 'me-2' : '' }}"></i>{{ $label }}
    </button>
</form>
