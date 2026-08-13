@php
    /** Date-range filter card reused across every report page. */
    $extra = $extra ?? null;
@endphp

<x-card class="mb-3">
    <form method="GET" class="row g-2 align-items-end">
        {{ $extra }}
        <div class="col-md-3">
            <label class="form-label" for="from">Dari</label>
            <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="to">Sampai</label>
            <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn w-100 d-print-none" onclick="window.print()">
                <i class="ti ti-printer me-1"></i> Cetak
            </button>
        </div>
    </form>
</x-card>
