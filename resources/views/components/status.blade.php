@props(['value' => null, 'color' => null, 'label' => null])

@php
    $map = [
        'draft' => ['secondary', 'Draft'],
        'submitted' => ['azure', 'Diajukan'],
        'sent' => ['azure', 'Terkirim'],
        'pending' => ['yellow', 'Menunggu'],
        'approved' => ['blue', 'Disetujui'],
        'accepted' => ['blue', 'Diterima'],
        'confirmed' => ['blue', 'Dikonfirmasi'],
        'released' => ['blue', 'Dirilis'],
        'in_progress' => ['indigo', 'Berjalan'],
        'partial' => ['orange', 'Sebagian'],
        'posted' => ['green', 'Diposting'],
        'received' => ['green', 'Diterima'],
        'delivered' => ['green', 'Terkirim'],
        'paid' => ['green', 'Lunas'],
        'completed' => ['green', 'Selesai'],
        'closed' => ['dark', 'Ditutup'],
        'rejected' => ['red', 'Ditolak'],
        'cancelled' => ['red', 'Dibatalkan'],
        'expired' => ['red', 'Kedaluwarsa'],
        'reversed' => ['red', 'Dibalik'],
        'active' => ['green', 'Aktif'],
        'resigned' => ['secondary', 'Resign'],
        'terminated' => ['red', 'Diberhentikan'],
    ];

    [$autoColor, $autoLabel] = $map[$value] ?? ['secondary', \Illuminate\Support\Str::headline((string) $value)];
@endphp

<span {{ $attributes->merge(['class' => 'badge bg-'.($color ?? $autoColor).'-lt']) }}>{{ $label ?? $autoLabel }}</span>
