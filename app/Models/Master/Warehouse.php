<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Inventory\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = ['code', 'name', 'address', 'phone', 'keeper_name', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name', 'keeper_name'];

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public static function defaultId(): ?int
    {
        return static::query()->where('is_default', true)->value('id')
            ?? static::query()->active()->value('id');
    }

    /** Only one warehouse may hold the default flag. */
    public function makeDefault(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->forceFill(['is_default' => true])->save();
    }
}
