<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppEngagementEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event_name',
        'area',
        'status',
        'source',
        'route',
        'endpoint',
        'duration_ms',
        'app_version',
        'build_number',
        'platform',
        'device_model',
        'os_version',
        'metadata',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];
}
