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
        'pos_transaction_id',
        'amount',
        'approval_code',
        'masked_pan',
        'installment',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'installment' => 'integer',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    /** Kart ile ödendiyse ilgili ÖKC işlemi. */
    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }
}
