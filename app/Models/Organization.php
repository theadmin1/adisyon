<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'logo_path', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'organization_branch')->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(ChainMenuCategory::class);
    }

    public function menuProducts(): HasMany
    {
        return $this->hasMany(ChainMenuProduct::class);
    }
}
