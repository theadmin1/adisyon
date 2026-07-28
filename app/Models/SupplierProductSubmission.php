<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProductSubmission extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'reviewed_by_user_id',
        'reviewed_by_staff_profile_id',
        'submission_number',
        'status',
        'contact_name',
        'contact_email',
        'contact_phone',
        'supplier_notes',
        'submitted_ip',
        'submitted_user_agent',
        'submitted_at',
        'reviewed_by_name',
        'review_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierProductSubmissionItem::class);
    }
}
