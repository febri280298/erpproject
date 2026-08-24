@php
    /**
     * Rendered from config('erp.menu'). An entry is dropped when its module is
     * switched off or the user lacks its permission, and a parent disappears
     * once all its children are gone.
     */
    $modules = app(\App\Services\ModuleRegistry::class);

    $visible = fn (array $entry) => $modules->enabled($entry['module'] ?? null)
        && (! isset($entry['permission']) || auth()->user()?->can($entry['permission']))
        && (! isset($entry['route']) || \Illuminate\Support\Facades\Route::has($entry['route']));

    $menu = collect(config('erp.menu'))
        ->map(function (array $item) use ($visible) {
            if (isset($item['children'])) {
                $item['children'] = collect($item['children'])->filter($visible)->values()->all();
            }

            return $item;
        })
        ->filter(function (array $item) use ($visible, $modules) {
            if (! $modules->enabled($item['module'] ?? null)) {
                return false;
            }

            return isset($item['children']) ? count($item['children']) > 0 : $visible($item);
        })
        ->values();

    /*
     * Menentukan menu mana yang tersorot dan grup mana yang terbuka.
     *
     * Aturannya dua lapis:
     *
     * 1. Cocok persis selalu menang. Bila rute yang sedang dibuka memang salah
     *    satu tujuan menu, hanya menu itu yang aktif.
     *
     * 2. Bila tidak ada yang cocok persis — misalnya sedang membuka
     *    products.create atau purchase-orders.show — barulah dicari menu daftar
     *    (.index) dari sumber daya yang sama, supaya halaman anak tetap menyorot
     *    induknya.
     *
     * Versi sebelumnya hanya memakai pencocokan awalan: rute dipotong sebelum
     * titik terakhir lalu dicocokkan dengan pola berjoker. Untuk products.index
     * itu benar, tetapi untuk dashboard.master awalannya menjadi "dashboard" —
     * dan SELURUH dashboard modul berawalan sama, sehingga membuka satu
     * dashboard menyalakan semua grupnya sekaligus. Hal yang sama terjadi pada
     * Produk/Upload Produk dan Stok/Kartu Stok yang berbagi awalan.
     */
    $rutuSekarang = request()->route()?->getName();

    $semuaRuteMenu = collect(config('erp.menu'))
        ->flatMap(fn (array $item) => $item['children'] ?? [$item])
        ->pluck('route')
        ->filter()
        ->all();

    $adaYangCocokPersis = in_array($rutuSekarang, $semuaRuteMenu, true);

    $isActive = function (string $route) use ($rutuSekarang, $adaYangCocokPersis) {
        if ($rutuSekarang === $route) {
            return true;
        }

        if ($adaYangCocokPersis) {
            return false;
        }

        // Hanya menu daftar yang mewakili halaman anaknya; menu seperti
        // "Upload Produk" mewakili dirinya sendiri saja.
        if (! \Illuminate\Support\Str::endsWith($route, '.index')) {
            return false;
        }

        return \Illuminate\Support\Str::startsWith(
            (string) $rutuSekarang,
            \Illuminate\Support\Str::beforeLast($route, '.').'.'
        );
    };
@endphp

<aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                aria-controls="sidebar-menu" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{--
            Sengaja tanpa `navbar-brand-autodark`: kelas itu memaksa
            `filter: brightness(0) invert(1)` pada logo di sidebar gelap, yang
            mengubah logo berwarna menjadi siluet putih polos.
        --}}
        <div class="navbar-brand">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                @if(! empty($company['logo']))
                    {{-- Alas putih agar logo transparan tetap terbaca di sidebar gelap --}}
                    <img src="{{ asset('storage/'.$company['logo']) }}" alt="{{ $company['name'] }}"
                         class="rounded bg-white p-1 flex-shrink-0"
                         style="max-height:2.25rem; max-width:2.75rem; object-fit:contain">
                @else
                    <span class="avatar avatar-sm bg-primary text-white"><i class="ti ti-building-factory-2"></i></span>
                @endif
                <span class="text-truncate" style="max-width:11rem">{{ $company['name'] ?? config('app.name') }}</span>
            </a>
        </div>

        <nav class="collapse navbar-collapse" id="sidebar-menu" aria-label="Menu utama">
            <ul class="navbar-nav pt-lg-3">
                @foreach($menu as $index => $item)
                    @if(! isset($item['children']))
                        <li class="nav-item {{ $isActive($item['route']) ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($item['route']) }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="{{ $item['icon'] }}"></i></span>
                                <span class="nav-link-title">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @else
                        @php $open = collect($item['children'])->contains(fn ($c) => $isActive($c['route'])); @endphp
                        <li class="nav-item dropdown {{ $open ? 'active' : '' }}">
                            <a class="nav-link dropdown-toggle" href="#navbar-menu-{{ $index }}" data-bs-toggle="dropdown"
                               data-bs-auto-close="false" role="button" aria-expanded="{{ $open ? 'true' : 'false' }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="{{ $item['icon'] }}"></i></span>
                                <span class="nav-link-title">{{ $item['label'] }}</span>
                            </a>
                            <div class="dropdown-menu {{ $open ? 'show' : '' }}" id="navbar-menu-{{ $index }}">
                                @foreach($item['children'] as $child)
                                    <a class="dropdown-item d-flex align-items-start gap-2 py-2 {{ $isActive($child['route']) ? 'active' : '' }}"
                                       href="{{ route($child['route']) }}">
                                        {{-- Step number makes the order of the transaction chain obvious. --}}
                                        @isset($child['step'])
                                            <span class="badge bg-primary-lt flex-shrink-0 mt-1">{{ $child['step'] }}</span>
                                        @endisset
                                        <span class="flex-fill lh-sm">
                                            {{ $child['label'] }}
                                            @isset($child['hint'])
                                                <span class="d-block text-secondary" style="font-size:.7rem">{{ $child['hint'] }}</span>
                                            @endisset
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
