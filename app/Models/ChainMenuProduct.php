<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChainMenuProduct extends Model
{
    protected $fillable = ['organization_id', 'chain_menu_category_id', 'name', 'sku', 'base_price', 'discounted_price', 'kitchen_department', 'description', 'image_path', 'is_active'];
    protected $casts = ['base_price' => 'decimal:2', 'discounted_price' => 'decimal:2', 'is_active' => 'boolean'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function category(): BelongsTo { return $this->belongsTo(ChainMenuCategory::class, 'chain_menu_category_id'); }
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'chain_menu_product_branch')
            ->withPivot(['id', 'published_product_id', 'price_override', 'is_enabled', 'published_at'])->withTimestamps();
    }
}
