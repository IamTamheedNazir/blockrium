<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBoost extends Model
{
    protected $fillable = [
        'user_id',
        'boost_plan_id',
        'provider',
        'purchase_reference',
        'amount_paid',
        'currency',
        'boost_percent',
        'starts_at',
        'ends_at',
        'status',
        'payment_status',
        'metadata',
    ];
}
