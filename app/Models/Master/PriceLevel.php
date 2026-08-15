<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named sale tier (Eceran, Grosir, Proyek…). Capped at MAX so the product
 * form stays readable.
 */
class PriceLevel extends Model
{
    use LogsActivity, Searchable;

    public const MAX = 10;

    protected $fillable = ['code', 'name', 'description', 'sort_order', 'is_default', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name'];

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public static function defaultId(): ?int
    {
        return static::query()->where('is_default', true)->value('id')
            ?? static::query()->active()->ordered()->value('id');
    }

    /** Only one tier may be the fallback used when a customer has none. */
    public function makeDefault(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }

    public static function atCapacity(): bool
    {
        return static::query()->count() >= self::MAX;
    }
}
