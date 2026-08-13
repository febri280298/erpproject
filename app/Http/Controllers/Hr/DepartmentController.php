<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Hr\Department;
use App\Models\Hr\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class DepartmentController extends SimpleMasterController
{
    protected string $model = Department::class;

    protected string $routeName = 'departments';

    protected string $title = 'Departemen';

    protected string $permission = 'department';

    protected string $icon = 'ti ti-sitemap';

    protected array $with = ['manager'];

    protected array $withCount = ['employees'];

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Departemen'],
            ['key' => 'manager', 'label' => 'Manajer', 'render' => fn ($r) => $r->manager?->name ?? '—'],
            ['key' => 'employees_count', 'label' => 'Karyawan', 'type' => 'number', 'class' => 'text-num'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'name', 'label' => 'Nama Departemen', 'required' => true, 'col' => 'col-md-8'],
            ['name' => 'manager_id', 'label' => 'Manajer', 'type' => 'select',
                'options' => Employee::active()->orderBy('name')->pluck('name', 'id')],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
            ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:150'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function deleteBlocker(Model $record): ?string
    {
        return $record->employees()->exists() ? 'Departemen ini masih memiliki karyawan.' : null;
    }
}
