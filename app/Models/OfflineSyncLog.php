<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'branch_id',
        'sync_uuid',
        'payload_type',
        'status',
        'error_message',
        'details',
        'synced_at',
    ];

    protected $casts = [
        'details' => 'array',
        'synced_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
