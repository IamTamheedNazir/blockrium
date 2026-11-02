<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserXpEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_key',
        'source_type',
        'source_id',
        'dedupe_key',
        'xp_amount',
        'metadata',
        'awarded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'awarded_at' => 'datetime',
    ];
}
