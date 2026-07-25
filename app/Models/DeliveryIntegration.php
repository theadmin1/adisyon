<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'channel',
        'store_name',
        'store_id',
        'api_key',
        'api_secret',
        'is_active',
        'auto_accept',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_accept' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
