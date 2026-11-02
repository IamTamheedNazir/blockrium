<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralMilestone extends Model
{
    protected $fillable = [
        'key',
        'title',
        'description',
        'required_active_referrals',
        'reward_amount',
        'reward_label',
        'reward_xp',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reward_amount' => 'decimal:4',
    ];
}
