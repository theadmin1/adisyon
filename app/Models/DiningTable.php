<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'hall_id',
        'name',
        'code',
        'capacity',
        'occupant_count',
        'status',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'status' => TableStatus::class,
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }
}
