<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_id',
        'payment_method',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }
}
