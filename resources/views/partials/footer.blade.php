<footer class="footer footer-transparent d-print-none">
    <div class="container-xl">
        <div class="row align-items-center flex-row-reverse py-3">
            <div class="col-lg-auto ms-lg-auto">
                <ul class="list-inline list-inline-dots mb-0 text-center text-lg-end">
                    <li class="list-inline-item text-secondary">
                        UI berbasis
                        <a href="https://tabler.io" target="_blank" rel="noopener" class="link-secondary">Tabler</a>
                    </li>
                    <li class="list-inline-item text-secondary">Laravel {{ app()->version() }}</li>
                    <li class="list-inline-item text-secondary">v{{ config('erp.version') }}</li>
                </ul>
            </div>
            <div class="col-12 col-lg-auto mt-2 mt-lg-0 text-center text-lg-start">
                <span class="text-secondary">&copy; {{ date('Y') }} {{ $company['name'] }}</span>
            </div>
        </div>
    </div>
</footer>
