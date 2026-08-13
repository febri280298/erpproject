<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Master\Tax;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends SimpleMasterController
{
    protected string $model = Tax::class;

    protected string $routeName = 'taxes';

    protected string $title = 'Pajak';

    protected string $permission = 'tax';

    protected string $icon = 'ti ti-receipt-tax';

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Pajak'],
            ['key' => 'rate', 'label' => 'Tarif', 'type' => 'percent', 'class' => 'text-num'],
            ['key' => 'is_default', 'label' => 'Default', 'type' => 'raw', 'class' => 'w-1',
                'render' => fn ($r) => $r->is_default ? '<span class="badge bg-blue-lt">Default</span>' : '—'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4', 'placeholder' => 'PPN11'],
            ['name' => 'name', 'label' => 'Nama Pajak', 'required' => true, 'col' => 'col-md-8', 'placeholder' => 'PPN 11%'],
            ['name' => 'rate', 'label' => 'Tarif', 'type' => 'number', 'step' => '0.0001', 'required' => true,
                'col' => 'col-md-4', 'suffix' => '%'],
            ['name' => 'is_default', 'label' => 'Jadikan pajak default', 'type' => 'checkbox', 'default' => false, 'col' => 'col-md-4'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true, 'col' => 'col-md-4'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('taxes', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /** Only one tax may be flagged as the default. */
    protected function afterSave(Model $record, Request $request): void
    {
        if ($record->is_default) {
            Tax::where('id', '!=', $record->id)->update(['is_default' => false]);
        }
    }
}
