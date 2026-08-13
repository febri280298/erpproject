<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Concerns\SimpleMasterController;
use App\Models\Master\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ProductCategoryController extends SimpleMasterController
{
    protected string $model = ProductCategory::class;

    protected string $routeName = 'categories';

    protected string $title = 'Kategori Produk';

    protected string $permission = 'category';

    protected string $icon = 'ti ti-category';

    protected array $with = ['parent'];

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Kode', 'type' => 'code', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'Nama Kategori'],
            ['key' => 'parent', 'label' => 'Induk', 'render' => fn ($r) => $r->parent?->name ?? '—'],
            ['key' => 'is_active', 'label' => 'Status', 'type' => 'bool', 'class' => 'w-1'],
        ];
    }

    protected function fields(?Model $record = null): array
    {
        $parents = ProductCategory::query()
            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
            ->orderBy('name')
            ->pluck('name', 'id');

        return [
            ['name' => 'code', 'label' => 'Kode', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'name', 'label' => 'Nama Kategori', 'required' => true, 'col' => 'col-md-8'],
            ['name' => 'parent_id', 'label' => 'Kategori Induk', 'type' => 'select', 'options' => $parents,
                'placeholder' => '— Tanpa induk —'],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
            ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
        ];
    }

    protected function rules(?Model $record = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('product_categories', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function deleteBlocker(Model $record): ?string
    {
        if ($record->products()->exists()) {
            return 'Kategori ini masih memiliki produk.';
        }

        return $record->children()->exists() ? 'Kategori ini masih memiliki sub-kategori.' : null;
    }
}
