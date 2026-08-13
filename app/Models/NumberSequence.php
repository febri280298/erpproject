<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = [
        'module',
        'prefix',
        'reset_period',
        'padding',
        'next_number',
        'period_year',
        'period_month',
    ];

    protected $casts = [
        'padding' => 'integer',
        'next_number' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
    ];
}
