<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('authenticated_branch', function (Builder $builder): void {
            $user = Auth::user();

            if ($user && ! $user->isAdminUser() && $user->branch_id) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('branch_id'),
                    $user->branch_id
                );
            }
        });

        static::creating(function ($model): void {
            if ($model->branch_id) {
                return;
            }

            $user = Auth::user();

            if ($user && ! $user->isAdminUser() && $user->branch_id) {
                $model->branch_id = $user->branch_id;
            }
        });
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->withoutGlobalScope('authenticated_branch')
            ->where($query->getModel()->qualifyColumn('branch_id'), $branchId);
    }
}
