{{-- Compact recent-documents table used on the dashboard. --}}
@if($rows->isEmpty())
    <div class="card-body">
        <x-empty icon="ti ti-file-off" title="Belum ada data" :message="$emptyMessage" />
    </div>
@else
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
            <tr>
                <th>Nomor</th>
                <th>Mitra</th>
                <th>Tanggal</th>
                <th class="text-num">Total</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td><a href="{{ route($routeName.'.show', $row) }}">{{ $row->{$numberField} }}</a></td>
                    <td class="text-truncate" style="max-width:12rem">{{ $row->{$partnerField}?->name ?? '—' }}</td>
                    <td>{{ fdate($row->date) }}</td>
                    <td class="text-num">{{ rupiah($row->total) }}</td>
                    <td><x-status :value="$row->status" /></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
