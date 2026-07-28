<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'purchase_order_id', 'branch_id', 'received_by_user_id', 'received_by_staff_profile_id',
        'receipt_number', 'supplier_invoice_number', 'supplier_invoice_date', 'received_by_name',
        'received_value', 'notes', 'received_at',
    ];

    protected $casts = [
        'supplier_invoice_date' => 'date',
        'received_value' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }
}
