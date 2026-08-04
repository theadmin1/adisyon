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
        'is_active',
    ];

    protected $casts = [
        'branch_slugs' => 'array',
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function adminUrl(): string
    {
        return rtrim($this->base_url, '/').'/'.ltrim($this->admin_path, '/');
    }

    public function publicMenuUrl(Branch $branch): string
    {
        $branchSlug = $this->branch_slugs[(string) $branch->id] ?? $this->branch_slugs[$branch->id] ?? $branch->code;

        return rtrim($this->base_url, '/').'/menu/'.rawurlencode($this->company_slug).'/'.rawurlencode(strtolower((string) $branchSlug));
    }
}
