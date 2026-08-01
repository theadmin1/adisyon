<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRecipe extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'output_product_id', 'created_by_user_id', 'name', 'base_servings', 'instructions', 'is_active'];

    protected $casts = ['base_servings' => 'decimal:3', 'is_active' => 'boolean'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function outputProduct(): BelongsTo { return $this->belongsTo(Product::class, 'output_product_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function items(): HasMany { return $this->hasMany(ProductionRecipeItem::class); }
    public function workflows(): HasMany { return $this->hasMany(ProductionWorkflow::class); }
}
