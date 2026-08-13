<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    use LogsActivity, Searchable;

    protected $table = 'uoms';

    protected $fillable = ['code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static array $searchable = ['code', 'name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
