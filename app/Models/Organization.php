<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'logo_path', 'logo_light_path', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (Str::startsWith($this->logo_path, ['data:', 'http://', 'https://'])) {
            return $this->logo_path;
        }

        return asset(ltrim($this->logo_path, '/'));
    }

    public function getLightLogoUrlAttribute(): ?string
    {
        if (! $this->logo_light_path) {
            return null;
        }

        if (Str::startsWith($this->logo_light_path, ['data:', 'http://', 'https://'])) {
            return $this->logo_light_path;
        }

        return asset(ltrim($this->logo_light_path, '/'));
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
