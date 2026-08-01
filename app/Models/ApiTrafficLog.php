<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiTrafficLog extends Model
{
    protected $fillable = [
        'request_id',
        'branch_id',
        'user_id',
        'staff_profile_id',
        'restaurant_id',
        'user_name',
        'staff_name',
        'method',
        'path',
        'route_name',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'request_headers',
        'request_payload',
        'response_payload',
        'request_size',
        'response_size',
        'occurred_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'occurred_at' => 'datetime',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'request_size' => 'integer',
        'response_size' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
