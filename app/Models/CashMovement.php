<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'cash_shift_id',
        'branch_id',
        'created_by_user_id',
        'created_by_staff_profile_id',
        'created_by_name',
        'type',
        'amount',
        'reason',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'created_by_staff_profile_id');
    }
}
