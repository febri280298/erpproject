<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Hr\LeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class LeaveTypeController extends SimpleMasterController
{
    protected string $model = LeaveType::class;

    protected string $routeName = 'leave-types';

    protected string $title = 'Jenis Cuti';

    protected string $permission = 'leave';

    protected string $icon = 'ti ti-beach';

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Jenis Cuti'],
            ['key' => 'max_days', 'label' => 'Kuota / Tahun', 'type' => 'raw', 'class' => 'text-num',
                'render' => fn ($r) => $r->max_days.' hari'],
            ['key' => 'is_paid', 'label' => 'Dibayar', 'type' => 'raw', 'class' => 'w-1',
                'render' => fn ($r) => $r->is_paid
                    ? '<span class="badge bg-green-lt">Dibayar</span>'
                    : '<span class="badge bg-secondary-lt">Tidak dibayar</span>'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4', 'placeholder' => 'TAHUNAN'],
            ['name' => 'name', 'label' => 'Jenis Cuti', 'required' => true, 'col' => 'col-md-8'],
            ['name' => 'max_days', 'label' => 'Kuota per Tahun', 'type' => 'number', 'required' => true,
                'col' => 'col-md-4', 'suffix' => 'hari'],
            ['name' => 'is_paid', 'label' => 'Cuti dibayar', 'type' => 'checkbox', 'default' => true, 'col' => 'col-md-4'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true, 'col' => 'col-md-4'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_types', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'max_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function deleteBlocker(Model $record): ?string
    {
        return $record->leaves()->exists() ? 'Jenis cuti ini sudah dipakai pada pengajuan cuti.' : null;
    }
}
