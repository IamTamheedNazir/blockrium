<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustCenterSection extends Model
{
    protected $table = 'trust_center_sections';

    protected $fillable = [
        'key',
        'title',
        'body',
        'sort_order',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
