<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'total_price',
        'notes',
        'is_complimentary',
        'complimentary_reason',
        'is_cancelled',
        'cancelled_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'is_complimentary' => 'boolean',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
