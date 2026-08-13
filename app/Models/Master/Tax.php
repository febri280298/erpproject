<?php

namespace App\Models\Master;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use LogsActivity, Searchable;

    protected $table = 'taxes';

    protected $fillable = ['code', 'name', 'rate', 'is_default', 'is_active'];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name'];

    public static function defaultRate(): float
    {
        return (float) (static::query()->where('is_default', true)->value('rate') ?? 0);
    }
}
