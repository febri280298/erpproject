<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Hr\Department;
use App\Models\Hr\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PositionController extends SimpleMasterController
{
    protected string $model = Position::class;

    protected string $routeName = 'positions';

    protected string $title = 'Jabatan';

    protected string $permission = 'position';

    protected string $icon = 'ti ti-id-badge-2';

    protected array $with = ['department'];

    protected array $withCount = ['employees'];

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Jabatan'],
            ['key' => 'department', 'label' => 'Departemen', 'render' => fn ($r) => $r->department?->name ?? '—'],
            ['key' => 'base_salary', 'label' => 'Gaji Pokok', 'type' => 'money', 'class' => 'text-num'],
            ['key' => 'employees_count', 'label' => 'Karyawan', 'type' => 'number', 'class' => 'text-num'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'name', 'label' => 'Nama Jabatan', 'required' => true, 'col' => 'col-md-8'],
            ['name' => 'department_id', 'label' => 'Departemen', 'type' => 'select',
                'options' => Department::active()->orderBy('name')->pluck('name', 'id')],
            ['name' => 'base_salary', 'label' => 'Gaji Pokok', 'type' => 'number', 'step' => '0.01', 'prefix' => 'Rp'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('positions', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function deleteBlocker(Model $record): ?string
    {
        return $record->employees()->exists() ? 'Jabatan ini masih dipakai oleh karyawan.' : null;
    }
}
