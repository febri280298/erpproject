@extends('layouts.app')

@section('title', $role->exists ? 'Ubah Peran' : 'Tambah Peran')
@section('pretitle', 'Pengaturan')

@section('content')
    {{--
        Permission matrix: rows are what the user works on, columns are what
        they may do with it. Checkboxes carry data-* markers so the row / column
        / section toggles can flip them without a per-checkbox Alpine model.
    --}}
    <form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}"
          x-data="permissionMatrix">
        @csrf
        @if($role->exists) @method('PUT') @endif

        <x-card title="Nama Peran">
            <div class="row g-3">
                <x-form.input name="name" label="Nama Peran" :value="$role->name" required col="col-md-6"
                              placeholder="Supervisor Gudang" />
                <div class="col-md-6 d-flex align-items-end">
                    <div class="text-secondary small">
                        Centang izin di tabel bawah. <strong>Lihat</strong> membuka menunya,
                        <strong>Posting</strong> mengesahkan dokumen ke stok/jurnal,
                        <strong>Setujui</strong> memberi wewenang persetujuan.
                    </div>
                </div>
            </div>
        </x-card>

        <x-card title="Hak Akses" flush class="mt-3">
            <x-slot:actions>
                <span class="text-secondary small me-2">
                    Terpilih: <strong x-text="count"></strong> izin
                </span>
                <button type="button" class="btn btn-sm" @click="toggleAll(true)">Centang Semua</button>
                <button type="button" class="btn btn-sm btn-ghost-secondary" @click="toggleAll(false)">Kosongkan</button>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-vcenter card-table table-selectable">
                    <thead>
                    <tr>
                        <th style="min-width:18rem">Modul / Data</th>
                        @foreach($actions as $action => $actionLabel)
                            <th class="text-center" style="width:7rem">
                                {{ $actionLabel }}
                                <button type="button" class="btn btn-sm btn-ghost-secondary d-block mx-auto mt-1 py-0 px-1"
                                        style="font-size:.65rem"
                                        @click="toggleColumn('{{ $action }}')">semua</button>
                            </th>
                        @endforeach
                        <th class="text-center" style="width:5rem">Baris</th>
                    </tr>
                    </thead>

                    @foreach($groups as $groupLabel => $group)
                        <tbody>
                        <tr class="table-light">
                            <td colspan="{{ count($actions) + 1 }}" class="fw-bold">
                                {{ $groupLabel }}
                                @if(! $group['enabled'])
                                    <span class="badge bg-secondary-lt ms-1" title="Modul sedang dimatikan di Pengaturan → Modul">
                                        modul nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input m-0"
                                       @change="toggleGroup('{{ $groupLabel }}', $event.target.checked)"
                                       title="Centang seluruh baris di bagian ini">
                            </td>
                        </tr>

                        @foreach($group['rows'] as $row)
                            <tr class="{{ $group['enabled'] ? '' : 'opacity-75' }}">
                                <td>{{ $row['label'] }}</td>

                                @foreach($actions as $action => $actionLabel)
                                    <td class="text-center">
                                        @if(in_array($action, $row['available'], true))
                                            <input type="checkbox" class="form-check-input m-0"
                                                   name="permissions[]"
                                                   value="{{ $row['subject'].'.'.$action }}"
                                                   data-perm
                                                   data-action="{{ $action }}"
                                                   data-subject="{{ $row['subject'] }}"
                                                   data-group="{{ $groupLabel }}"
                                                   @change="sync()"
                                                   @checked(in_array($row['subject'].'.'.$action, old('permissions', $assigned), true))>
                                        @else
                                            <span class="text-secondary">—</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input m-0"
                                           @change="toggleRow('{{ $row['subject'] }}', $event.target.checked)"
                                           title="Centang semua aksi untuk baris ini">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>

            <x-slot:footer>
                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('roles.index') }}" class="btn btn-link">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy me-1"></i> Simpan Peran
                    </button>
                </div>
            </x-slot:footer>
        </x-card>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('permissionMatrix', () => ({
                count: 0,

                init() {
                    this.sync();
                },

                boxes(selector = '[data-perm]') {
                    return Array.from(this.$root.querySelectorAll(selector));
                },

                sync() {
                    this.count = this.boxes().filter((el) => el.checked).length;
                },

                set(list, checked) {
                    list.forEach((el) => { el.checked = checked; });
                    this.sync();
                },

                toggleAll(checked) {
                    this.set(this.boxes(), checked);
                },

                toggleRow(subject, checked) {
                    this.set(this.boxes(`[data-perm][data-subject="${subject}"]`), checked);
                },

                toggleGroup(group, checked) {
                    this.set(this.boxes(`[data-perm][data-group="${group}"]`), checked);
                },

                /* Column header flips the whole column, using the first box as the reference state. */
                toggleColumn(action) {
                    const list = this.boxes(`[data-perm][data-action="${action}"]`);
                    const allChecked = list.length > 0 && list.every((el) => el.checked);
                    this.set(list, ! allChecked);
                },
            }));
        });
    </script>
@endpush
