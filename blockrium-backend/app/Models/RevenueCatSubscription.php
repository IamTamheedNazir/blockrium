<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueCatSubscription extends Model
{
    protected $table = 'revenuecat_subscriptions';

    protected $fillable = [
        'user_id',
        'app_user_id',
        'entitlement_id',
        'product_id',
        'plan_name',
        'status',
        'store',
        'environment',
        'transaction_id',
        'original_transaction_id',
        'price',
        'price_usd',
        'price_in_purchased_currency',
        'currency',
        'purchased_at',
        'expires_at',
        'last_event_at',
        'metadata',
    ];

    protected $casts = [
        'price' => 'float',
        'price_usd' => 'float',
        'price_in_purchased_currency' => 'float',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];
}
