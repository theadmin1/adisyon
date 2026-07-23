<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'license_key',
        'device_token',
        'status',
        'expires_at',
        'max_devices',
        'restrictions',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'restrictions' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function isValid(): bool
    {
        if ($this->status !== 'Active') {
            return false;
        }

        if ($this->expires_at) {
            try {
                $expiresTime = $this->expires_at instanceof \Carbon\Carbon
                    ? $this->expires_at
                    : \Carbon\Carbon::parse($this->expires_at);

                if ($expiresTime->isPast()) {
                    return false;
                }
            } catch (\Throwable $e) {}
        }

        return true;
    }
}
