<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingApiLog extends Model
{
    protected $fillable = [
        'service',
        'action',
        'http_method',
        'url',
        'request_payload',
        'response_status',
        'response_body',
        'duration_ms',
        'context',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_body' => 'array',
        'context' => 'array',
    ];
}
