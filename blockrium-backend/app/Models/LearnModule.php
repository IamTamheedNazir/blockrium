<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnModule extends Model
{
    protected $table = 'learn_modules';

    protected $fillable = [
        'key',
        'title',
        'description',
        'content',
        'youtube_url',
        'youtube_video_id',
        'video_title',
        'estimated_minutes',
        'reward_xp',
        'reward_brc',
        'passing_score',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reward_brc' => 'float',
    ];
}
