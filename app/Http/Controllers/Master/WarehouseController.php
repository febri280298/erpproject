<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends SimpleMasterController
{
    protected string $model = Warehouse::class;

    protected string $routeName = 'warehouses';

    protected string $title = 'Gudang';

    protected string $permission = 'warehouse';

    protected string $icon = 'ti ti-building-warehouse';

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Gudang'],
            ['key' => 'keeper_name', 'label' => 'Penanggung Jawab'],
            ['key' => 'phone', 'label' => 'Telepon'],
            ['key' => 'is_default', 'label' => 'Default', 'type' => 'raw', 'class' => 'w-1',
                'render' => fn ($r) => $r->is_default ? '<span class="badge bg-blue-lt">Default</span>' : '—'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4', 'placeholder' => 'GDG-01'],
            ['name' => 'name', 'label' => 'Nama Gudang', 'required' => true, 'col' => 'col-md-8'],
            ['name' => 'keeper_name', 'label' => 'Penanggung Jawab', 'col' => 'col-md-6'],
            ['name' => 'phone', 'label' => 'Telepon', 'col' => 'col-md-6'],
            ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea'],
            ['name' => 'is_default', 'label' => 'Gudang default', 'type' => 'checkbox', 'default' => false, 'col' => 'col-md-6'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true, 'col' => 'col-md-6'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:150'],
            'keeper_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function afterSave(Model $record, Request $request): void
    {
        if ($record->is_default) {
            $record->makeDefault();
        }
    }

    protected function deleteBlocker(Model $record): ?string
    {
        if ($record->is_default) {
            return 'Gudang default tidak dapat dihapus. Tetapkan gudang lain sebagai default terlebih dahulu.';
        }

        return $record->stocks()->where('quantity', '!=', 0)->exists()
            ? 'Gudang ini masih memiliki stok.'
            : null;
    }
}
