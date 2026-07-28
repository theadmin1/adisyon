<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'open_branch_key',
        'opened_by_user_id',
        'opened_by_staff_profile_id',
        'closed_by_user_id',
        'closed_by_staff_profile_id',
        'shift_number',
        'status',
        'opened_by_name',
        'closed_by_name',
        'opening_cash',
        'cash_sales',
        'cash_in_total',
        'cash_out_total',
        'expected_cash',
        'counted_cash',
        'difference',
        'payment_totals',
        'denomination_counts',
        'opening_note',
        'closing_note',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'cash_in_total' => 'decimal:2',
        'cash_out_total' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'payment_totals' => 'array',
        'denomination_counts' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function openedByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'opened_by_staff_profile_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function closedByStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'closed_by_staff_profile_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
