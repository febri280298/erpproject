<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `search()` scope driven by the model's `$searchable` column list.
 */
trait Searchable
{
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $columns = static::$searchable ?? ['name'];

        return $query->where(function (Builder $inner) use ($columns, $term) {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
