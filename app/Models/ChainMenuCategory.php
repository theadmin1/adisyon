<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChainMenuCategory extends Model
{
    protected $fillable = ['organization_id', 'name', 'slug', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function products(): HasMany { return $this->hasMany(ChainMenuProduct::class); }
}
