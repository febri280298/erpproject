<?php

namespace App\Models\Hr;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use LogsActivity, Searchable;

    protected $fillable = ['code', 'name', 'department_id', 'base_salary', 'is_active'];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static array $searchable = ['code', 'name'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
