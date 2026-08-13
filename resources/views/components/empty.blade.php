@props([
    'title' => 'Belum ada data',
    'message' => 'Data akan muncul di sini setelah ditambahkan.',
    'icon' => 'ti ti-database-off',
    'action' => null,
])

<div class="empty">
    <div class="empty-icon"><i class="{{ $icon }} fs-1"></i></div>
    <p class="empty-title">{{ $title }}</p>
    <p class="empty-subtitle text-secondary">{{ $message }}</p>
    @if($action)
        <div class="empty-action">{{ $action }}</div>
    @endif
</div>
