<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'license_id',
        'device_code',
        'device_guid',
        'api_key',
        'ip_address',
        'os_info',
        'status',
        'last_ping_at',
        'app_version',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeviceLog::class);
    }

    public function isOnline(): bool
    {
        return $this->last_ping_at && $this->last_ping_at->gt(now()->subMinutes(2));
    }
}
