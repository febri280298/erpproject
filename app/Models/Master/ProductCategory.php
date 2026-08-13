<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = ['code', 'name', 'parent_id', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static array $searchable = ['code', 'name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function fullName(): string
    {
        return $this->parent ? $this->parent->name.' / '.$this->name : $this->name;
    }
}
