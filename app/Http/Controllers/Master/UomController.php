<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Master\Uom;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UomController extends SimpleMasterController
{
    protected string $model = Uom::class;

    protected string $routeName = 'uoms';

    protected string $title = 'Satuan';

    protected string $permission = 'uom';

    protected string $icon = 'ti ti-ruler-measure';

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Satuan'],
            ['key' => 'products_count', 'label' => 'Dipakai', 'type' => 'number', 'class' => 'text-num'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected array $withCount = ['products'];

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4', 'placeholder' => 'PCS'],
            ['name' => 'name', 'label' => 'Nama Satuan', 'required' => true, 'col' => 'col-md-8', 'placeholder' => 'Pieces'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('uoms', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    protected function deleteBlocker(Model $record): ?string
    {
        return $record->products()->exists()
            ? 'Satuan ini masih dipakai oleh produk dan tidak dapat dihapus.'
            : null;
    }
}
