<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'contact_email',
        'phone',
        'address',
        'is_active',
    ];

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }
}
