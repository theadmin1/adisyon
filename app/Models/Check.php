<?php

namespace App\Models;

use App\Enums\CheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'dining_table_id',
        'waiter_id',
        'check_number',
        'guest_count',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'opened_at',
        'closed_at',
        'kitchen_sent_at',
    ];

    protected $casts = [
        'status' => CheckStatus::class,
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'kitchen_sent_at' => 'datetime',
    ];

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
