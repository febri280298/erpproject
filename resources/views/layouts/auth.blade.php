<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Masuk') · {{ $company['name'] ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">

    <script>
        (function () {
            var stored = window.localStorage.getItem('tabler-theme');
            document.documentElement.setAttribute('data-bs-theme', stored || 'light');
        })();
    </script>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column bg-surface-secondary">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="{{ route('login') }}" class="navbar-brand navbar-brand-autodark">
                <span class="avatar avatar-lg bg-primary text-white">
                    <i class="ti ti-building-factory-2 fs-1"></i>
                </span>
            </a>
            <h1 class="h2 mt-3 mb-0">{{ $company['name'] ?? config('app.name') }}</h1>
            <p class="text-secondary">Enterprise Resource Planning</p>
        </div>

        @yield('content')

        <div class="text-center text-secondary mt-4">
            &copy; {{ date('Y') }} {{ $company['name'] ?? config('app.name') }}
        </div>
    </div>
</div>
</body>
</html>
