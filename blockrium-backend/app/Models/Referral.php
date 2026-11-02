<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'user_id', 'referral_code', 'referred_by', 'rewards'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\ResUser');
    }

    public function referredBy()
    {
        return $this->belongsTo('App\Models\ResUser', 'referred_by');
    }
}
