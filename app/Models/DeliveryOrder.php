<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'channel',
        'platform_order_id',
        'order_number',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'address_note',
        'payment_method',
        'payment_status',
        'status',
        'courier_type',
        'courier_name',
        'courier_phone',
        'subtotal',
        'delivery_fee',
        'discount_total',
        'total',
        'items',
        'received_at',
        'accepted_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'received_at' => 'datetime',
        'accepted_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
