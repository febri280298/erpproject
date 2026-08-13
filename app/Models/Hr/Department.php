<?php

namespace App\Models\Hr;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = ['code', 'name', 'manager_id', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static array $searchable = ['code', 'name'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }
}
