@php
    /**
     * Filter bar shared by every document index.
     *
     * @var array $statuses   status value => label
     * @var array $selects    extra dropdowns: [['name' => 'partner_id', 'label' => 'Pemasok', 'options' => …], …]
     */
    $statuses = $statuses ?? [];
    $selects = $selects ?? [];
    $searchPlaceholder = $searchPlaceholder ?? 'Cari nomor dokumen…';
    $withDates = $withDates ?? true;
@endphp

<div class="card-body border-bottom py-3">
    <form method="GET" class="row g-2 filter-bar align-items-end">
        <div class="col-auto">
            <label class="form-label small mb-1">Cari</label>
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ $searchPlaceholder }}">
            </div>
        </div>

        @if($withDates)
            <div class="col-auto">
                <label class="form-label small mb-1">Dari</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Sampai</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
        @endif

        @if(count($statuses) > 0)
            <div class="col-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @foreach($selects as $select)
            <div class="col-auto">
                <label class="form-label small mb-1">{{ $select['label'] }}</label>
                <select name="{{ $select['name'] }}" class="form-select">
                    <option value="">Semua</option>
                    @foreach($select['options'] as $value => $label)
                        <option value="{{ $value }}" @selected(request($select['name']) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach

        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary">Filter</button>
            @if(collect(request()->query())->filter()->isNotEmpty())
                <a href="{{ url()->current() }}" class="btn btn-link">Reset</a>
            @endif
        </div>
    </form>
</div>
