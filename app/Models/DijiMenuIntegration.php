<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DijiMenuIntegration extends Model
{
    protected $fillable = [
        'organization_id',
        'base_url',
        'admin_path',
        'company_slug',
        'branch_slugs',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'branch_slugs' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function adminUrl(): string
    {
        return route('chain.menu.index');
    }

    public function publicMenuUrl(Branch $branch): string
    {
        $branchSlug = $this->branch_slugs[(string) $branch->id] ?? $this->branch_slugs[$branch->id] ?? $branch->code;

        return route('diji-menu.public', [
            'companySlug' => $this->company_slug,
            'branchSlug' => strtolower((string) $branchSlug),
        ]);
    }
}
