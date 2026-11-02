<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardSeason extends Model
{
    protected $fillable = [
        'key',
        'title',
        'starts_at',
        'ends_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
