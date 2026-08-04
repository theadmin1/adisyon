<?php

namespace App\Models;

use App\Enums\TableStatus;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'hall_id',
        'name',
        'code',
        'qr_token',
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

    public function activeCheck()
    {
        return $this->hasOne(Check::class)->where('status', 'open')->latestOfMany();
    }

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table): void {
            $table->qr_token ??= Str::lower(Str::random(32));
        });
    }
}
