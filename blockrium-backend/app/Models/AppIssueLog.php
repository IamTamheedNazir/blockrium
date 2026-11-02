<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppIssueLog extends Model
{
    protected $fillable = [
        'user_id',
        'severity',
        'source',
        'action',
        'endpoint',
        'method',
        'http_status',
        'error_code',
        'message',
        'app_version',
        'build_number',
        'platform',
        'device_model',
        'os_version',
        'request_duration_ms',
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
