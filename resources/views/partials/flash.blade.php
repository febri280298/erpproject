@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-dismiss>
        <div class="d-flex">
            <div><i class="ti ti-check me-2"></i></div>
            <div>{{ session('success') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-alert-triangle me-2"></i></div>
            <div>{{ session('error') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <div class="d-flex">
            <div><i class="ti ti-info-circle me-2"></i></div>
            <div>{{ session('warning') }}</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if($errors->any() && $errors->count() > 1)
    <div class="alert alert-danger" role="alert">
        <h4 class="alert-title">Periksa kembali isian berikut</h4>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
