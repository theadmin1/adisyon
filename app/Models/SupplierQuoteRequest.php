<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierQuoteRequest extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'requested_by_user_id',
        'requested_by_staff_profile_id',
        'reviewed_by_user_id',
        'reviewed_by_staff_profile_id',
        'purchase_order_id',
        'request_number',
        'token_hash',
        'status',
        'requested_by_name',
        'message',
        'expires_at',
        'contact_name',
        'contact_email',
        'contact_phone',
        'expected_delivery_date',
        'supplier_notes',
        'submitted_ip',
        'submitted_user_agent',
        'submitted_at',
        'reviewed_by_name',
        'rejection_reason',
        'reviewed_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierQuoteItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
