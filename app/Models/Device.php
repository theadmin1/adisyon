<?php

namespace App\Models;

use Carbon\Carbon;
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
        'api_key_hash',
        'ip_address',
        'os_info',
        'status',
        'last_ping_at',
        'app_version',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'api_key_hash',
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
        if (empty($this->last_ping_at)) {
            return false;
        }

        try {
            $pingTime = $this->last_ping_at instanceof Carbon
                ? $this->last_ping_at
                : Carbon::parse($this->last_ping_at);

            return $pingTime->gt(now()->subMinutes(2));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function lastPingFormatted(): string
    {
        if (empty($this->last_ping_at)) {
            return 'Hiç sinyal yok';
        }

        try {
            $pingTime = $this->last_ping_at instanceof Carbon
                ? $this->last_ping_at
                : Carbon::parse($this->last_ping_at);

            return $pingTime->diffForHumans();
        } catch (\Throwable $e) {
            return (string) $this->last_ping_at;
        }
    }
}
