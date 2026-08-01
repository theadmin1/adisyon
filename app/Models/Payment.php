<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'check_id',
        'payment_method',
        'client_reference',
        'amount',
        'sync_uuid',
        'is_synced',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_synced' => 'boolean',
    ];

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }
}
