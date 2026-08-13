@php $user = auth()->user(); @endphp

<header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-nav flex-row order-md-last">
            {{-- Theme toggle: two buttons, only the relevant one is visible per theme --}}
            <div class="d-none d-md-flex">
                <a href="?theme=dark" class="nav-link px-0 hide-theme-dark" title="Mode gelap"
                   data-bs-toggle="tooltip" data-bs-placement="bottom" onclick="event.preventDefault(); erpSetTheme('dark')">
                    <i class="ti ti-moon fs-2"></i>
                </a>
                <a href="?theme=light" class="nav-link px-0 hide-theme-light" title="Mode terang"
                   data-bs-toggle="tooltip" data-bs-placement="bottom" onclick="event.preventDefault(); erpSetTheme('light')">
                    <i class="ti ti-sun fs-2"></i>
                </a>
            </div>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Menu pengguna">
                    @if($user->avatarUrl())
                        <span class="avatar avatar-sm" style="background-image: url({{ $user->avatarUrl() }})"></span>
                    @else
                        <span class="avatar avatar-sm bg-primary-lt">{{ $user->initials() }}</span>
                    @endif
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ $user->name }}</div>
                        <div class="mt-1 small text-secondary">{{ $user->roleNames() }}</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <a href="{{ route('profile.edit') }}" class="dropdown-item">
                        <i class="ti ti-user me-2"></i> Profil Saya
                    </a>
                    @can('setting.view')
                        <a href="{{ route('settings.edit') }}" class="dropdown-item">
                            <i class="ti ti-settings me-2"></i> Pengaturan
                        </a>
                    @endcan
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="ti ti-logout me-2"></i> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="collapse navbar-collapse" id="navbar-menu-top">
            <div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center">
                <form action="{{ route('search') }}" method="GET" class="w-100">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                               placeholder="Cari produk, mitra, atau nomor dokumen…" aria-label="Pencarian global">
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>

@push('scripts')
    <script>
        function erpSetTheme(theme) {
            window.localStorage.setItem('tabler-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
        }
    </script>
@endpush
