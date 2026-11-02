<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLearnProgress extends Model
{
    protected $table = 'user_learn_progress';

    protected $fillable = [
        'user_id',
        'module_id',
        'status',
        'score',
        'answers',
        'completed_at',
        'reward_claimed_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'completed_at' => 'datetime',
        'reward_claimed_at' => 'datetime',
    ];
}
