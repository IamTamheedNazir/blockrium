<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralMilestoneClaim extends Model
{
    protected $fillable = [
        'user_id',
        'referral_milestone_id',
        'milestone_key',
        'reward_amount',
        'reward_label',
        'claimed_at',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:4',
        'claimed_at' => 'datetime',
    ];
}
