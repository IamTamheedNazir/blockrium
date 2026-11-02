<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueCatEvent extends Model
{
    protected $table = 'revenuecat_events';

    protected $fillable = [
        'event_id',
        'user_id',
        'app_user_id',
        'event_type',
        'product_id',
        'entitlement_ids',
        'transaction_id',
        'original_transaction_id',
        'store',
        'price',
        'price_usd',
        'price_in_purchased_currency',
        'currency',
        'purchased_at',
        'expiration_at',
        'raw_payload',
        'processed_at',
    ];

    protected $casts = [
        'price' => 'float',
        'price_usd' => 'float',
        'price_in_purchased_currency' => 'float',
        'purchased_at' => 'datetime',
        'expiration_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
