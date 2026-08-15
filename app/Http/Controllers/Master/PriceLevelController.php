<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Master\PriceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceLevelController extends SimpleMasterController
{
    protected string $model = PriceLevel::class;

    protected string $routeName = 'price-levels';

    protected string $title = 'Tingkat Harga';

    protected string $permission = 'price-level';

    protected string $icon = 'ti ti-tag';

    protected array $withCount = ['prices'];

    protected function defaultSort(): string
    {
        return 'sort_order';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'sort_order', 'label' => 'Urut', 'type' => 'number', 'class' => 'w-1 text-num'],
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Tingkat'],
            ['key' => 'description', 'label' => 'Keterangan'],
            ['key' => 'prices_count', 'label' => 'Produk', 'type' => 'number', 'class' => 'text-num'],
            ['key' => 'is_default', 'label' => 'Default', 'type' => 'raw', 'class' => 'w-1',
                'render' => fn ($r) => $r->is_default ? '<span class="badge bg-blue-lt">Default</span>' : '—'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4', 'placeholder' => 'GROSIR'],
            ['name' => 'name', 'label' => 'Nama Tingkat', 'required' => true, 'col' => 'col-md-8', 'placeholder' => 'Grosir'],
            ['name' => 'description', 'label' => 'Keterangan', 'col' => 'col-md-8',
                'placeholder' => 'Untuk pembelian minimal 10 unit'],
            ['name' => 'sort_order', 'label' => 'Urutan Tampil', 'type' => 'number', 'col' => 'col-md-4', 'default' => 0],
            ['name' => 'is_default', 'label' => 'Tingkat default', 'type' => 'checkbox', 'default' => false, 'col' => 'col-md-6',
                'help' => 'Dipakai untuk customer yang belum diberi tingkat harga.'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true, 'col' => 'col-md-6'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('price_levels', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /** Ten tiers is the cap the product form is designed around. */
    public function store(Request $request): RedirectResponse
    {
        if (PriceLevel::atCapacity()) {
            return back()->withInput()->with('error',
                'Maksimal '.PriceLevel::MAX.' tingkat harga. Hapus atau nonaktifkan salah satu terlebih dahulu.');
        }

        return parent::store($request);
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
            return 'Tingkat harga default tidak dapat dihapus. Tetapkan tingkat lain sebagai default dulu.';
        }

        if ($record->prices()->exists()) {
            return 'Tingkat harga ini masih dipakai pada daftar harga produk.';
        }

        return $record->partners()->exists()
            ? 'Tingkat harga ini masih dipakai oleh customer.'
            : null;
    }
}
