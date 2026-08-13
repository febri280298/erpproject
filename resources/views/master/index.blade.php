@extends('layouts.app')

@section('title', $title)
@section('pretitle', 'Data Master')

@section('actions')
    @can($permission.'.create')
        <a href="{{ route($routeName.'.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Tambah {{ $title }}
        </a>
    @endcan
@endsection

@section('content')
    <x-card flush>
        <div class="card-body border-bottom py-3">
            <form method="GET" class="row g-2 filter-bar align-items-center">
                <div class="col-auto">
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                        <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                               placeholder="Cari {{ strtolower($title) }}…">
                    </div>
                </div>
                @if($hasStatusFilter)
                    <div class="col-auto">
                        <select name="status" class="form-select" data-filter-submit>
                            <option value="">Semua status</option>
                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                        </select>
                    </div>
                @endif
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">Filter</button>
                    @if(request()->hasAny(['q', 'status']))
                        <a href="{{ route($routeName.'.index') }}" class="btn btn-link">Reset</a>
                    @endif
                </div>
                <div class="col text-end text-secondary">
                    {{ $records->total() }} data
                </div>
            </form>
        </div>

        @if($records->isEmpty())
            <div class="card-body">
                <x-empty :icon="$icon" :title="'Belum ada '.strtolower($title)"
                         message="Tambahkan data pertama untuk mulai menggunakannya di transaksi." />
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                    <tr>
                        @foreach($columns as $column)
                            <th class="{{ $column['class'] ?? '' }}">{{ $column['label'] }}</th>
                        @endforeach
                        <th class="w-1"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($records as $record)
                        <tr>
                            @foreach($columns as $column)
                                <td class="{{ $column['class'] ?? '' }}">
                                    @include('master.partials.cell', ['column' => $column, 'record' => $record])
                                </td>
                            @endforeach
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown"
                                            aria-label="Aksi">Aksi</button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @can($permission.'.edit')
                                            <a class="dropdown-item" href="{{ route($routeName.'.edit', $record) }}">
                                                <i class="ti ti-edit me-2"></i> Ubah
                                            </a>
                                        @endcan
                                        @can($permission.'.delete')
                                            <x-delete-form :action="route($routeName.'.destroy', $record)" label="Hapus" />
                                        @endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex align-items-center">
                {{ $records->links() }}
            </div>
        @endif
    </x-card>
@endsection
