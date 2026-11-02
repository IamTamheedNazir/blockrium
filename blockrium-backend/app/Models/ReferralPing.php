<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralPing extends Model
{
    protected $fillable = [
        'sender_user_id',
        'recipient_user_id',
        'referral_id',
        'message_type',
        'status',
        'read_at',
    ];
}
