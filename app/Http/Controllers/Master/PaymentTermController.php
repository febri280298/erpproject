<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Master\PaymentTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PaymentTermController extends SimpleMasterController
{
    protected string $model = PaymentTerm::class;

    protected string $routeName = 'payment-terms';

    protected string $title = 'Termin Pembayaran';

    protected string $permission = 'payment-term';

    protected string $icon = 'ti ti-calendar-dollar';

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Termin'],
            ['key' => 'days', 'label' => 'Jatuh Tempo', 'type' => 'raw', 'class' => 'text-num',
                'render' => fn ($r) => $r->days.' hari'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4', 'placeholder' => 'NET30'],
            ['name' => 'name', 'label' => 'Nama Termin', 'required' => true, 'col' => 'col-md-8', 'placeholder' => 'Net 30 hari'],
            ['name' => 'days', 'label' => 'Jumlah Hari', 'type' => 'number', 'required' => true, 'col' => 'col-md-4',
                'suffix' => 'hari', 'help' => 'Isi 0 untuk pembayaran tunai.'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true, 'col' => 'col-md-4'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('payment_terms', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ];
    }
}
