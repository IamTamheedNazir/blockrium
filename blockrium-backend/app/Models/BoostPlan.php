<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoostPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'boost_percent',
        'duration_days',
        'price',
        'currency',
        'provider',
        'provider_product_id',
        'provider_entitlement_id',
        'status',
        'sort_order',
    ];
}
