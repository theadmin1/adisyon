<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_user_id',
        'actor_staff_profile_id',
        'branch_id',
        'actor_user_name',
        'actor_staff_name',
        'action',
        'category',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'request_method',
        'request_path',
        'route_name',
        'request_id',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'actor_staff_profile_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
