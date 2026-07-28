<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckItem extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'check_id',
        'product_id',
        'added_by_staff_profile_id',
        'added_by_name',
        'product_name',
        'sync_uuid',
        'is_synced',
        'unit_price',
        'quantity',
        'total_price',
        'notes',
        'is_complimentary',
        'complimentary_reason',
        'is_cancelled',
        'cancelled_at',
        'kitchen_status',
        'sent_to_kitchen_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:3',
        'total_price' => 'decimal:2',
        'is_complimentary' => 'boolean',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'sent_to_kitchen_at' => 'datetime',
    ];

    protected $appends = [
        'product_sync_uuid',
    ];

    public function getProductSyncUuidAttribute(): ?string
    {
        return $this->product?->sync_uuid;
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function addedByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'added_by_staff_profile_id');
    }
}
