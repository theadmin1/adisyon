<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'restaurant_id',
        'branch_id',
        'organization_id',
        'password',
        'is_admin',
        'chain_role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'password' => 'hashed',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function chainBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'chain_user_branch')->withTimestamps();
    }

    public function isChainUser(): bool
    {
        return ! $this->isAdminUser() && $this->organization_id !== null && $this->chain_role !== null;
    }

    public function accessibleChainBranchIds(): array
    {
        if (! $this->isChainUser()) {
            return [];
        }

        $assigned = $this->chainBranches()->pluck('branches.id')->all();

        if ($assigned !== []) {
            return $assigned;
        }

        return $this->organization->branches()->pluck('branches.id')->all();
    }

    public function isAdminUser(): bool
    {
        return (bool) $this->is_admin;
    }
}
