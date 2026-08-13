@extends('layouts.app')

@section('title', ($record ? 'Ubah' : 'Tambah').' '.$title)
@section('pretitle', 'Data Master')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if($method !== 'POST')
                    @method($method)
                @endif

                <x-card :title="$title">
                    <div class="row g-3">
                        @foreach($fields as $field)
                            @php
                                $type = $field['type'] ?? 'text';
                                $value = $record ? data_get($record, $field['name']) : ($field['default'] ?? null);
                            @endphp

                            @switch($type)
                                @case('select')
                                    <x-form.select
                                        :name="$field['name']"
                                        :label="$field['label']"
                                        :options="$field['options'] ?? []"
                                        :value="$value"
                                        :required="$field['required'] ?? false"
                                        :help="$field['help'] ?? null"
                                        :col="$field['col'] ?? 'col-md-6'"
                                        :placeholder="$field['placeholder'] ?? '— Pilih —'" />
                                    @break

                                @case('textarea')
                                    <x-form.textarea
                                        :name="$field['name']"
                                        :label="$field['label']"
                                        :value="$value"
                                        :required="$field['required'] ?? false"
                                        :help="$field['help'] ?? null"
                                        :col="$field['col'] ?? 'col-12'" />
                                    @break

                                @case('checkbox')
                                    <x-form.checkbox
                                        :name="$field['name']"
                                        :label="$field['label']"
                                        :value="$record ? (bool) $value : ($field['default'] ?? true)"
                                        :help="$field['help'] ?? null"
                                        :col="$field['col'] ?? 'col-md-6'" />
                                    @break

                                @default
                                    <x-form.input
                                        :name="$field['name']"
                                        :label="$field['label']"
                                        :type="$type"
                                        :value="$value"
                                        :required="$field['required'] ?? false"
                                        :help="$field['help'] ?? null"
                                        :col="$field['col'] ?? 'col-md-6'"
                                        :step="$field['step'] ?? null"
                                        :placeholder="$field['placeholder'] ?? null"
                                        :prefix="$field['prefix'] ?? null"
                                        :suffix="$field['suffix'] ?? null" />
                            @endswitch
                        @endforeach
                    </div>

                    <x-slot:footer>
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route($routeName.'.index') }}" class="btn btn-link">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-device-floppy me-1"></i> Simpan
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
    </div>
@endsection
