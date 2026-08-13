<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') · {{ $company['name'] ?? config('app.name') }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    {{-- Apply the saved theme before first paint so dark mode never flashes white. --}}
    <script>
        (function () {
            var stored = window.localStorage.getItem('tabler-theme');
            var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('styles')
</head>
{{-- layout-fluid: Tabler melepas max-width setiap .container-* agar konten memakai lebar penuh --}}
<body class="layout-fluid">
<a href="#content" class="visually-hidden-focusable skip-link">Lewati ke konten utama</a>

<div class="page">
    @include('partials.sidebar')
    @include('partials.navbar')

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        @hasSection('pretitle')
                            <div class="page-pretitle">@yield('pretitle')</div>
                        @endif
                        <h2 class="page-title">@yield('title', 'Dashboard')</h2>
                        @hasSection('subtitle')
                            <div class="text-secondary mt-1">@yield('subtitle')</div>
                        @endif
                    </div>
                    @hasSection('actions')
                        <div class="col-auto ms-auto d-print-none">
                            <div class="btn-list">@yield('actions')</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <main id="content" class="page-body">
            <div class="container-xl">
                @include('partials.flash')
                @yield('content')
            </div>
        </main>

        @include('partials.footer')
    </div>
</div>

@stack('modals')
@stack('scripts')
</body>
</html>
