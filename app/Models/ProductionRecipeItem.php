<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRecipeItem extends Model
{
    use HasFactory;

    protected $fillable = ['production_recipe_id', 'ingredient_product_id', 'quantity', 'unit'];
    protected $casts = ['quantity' => 'decimal:4'];

    public function recipe(): BelongsTo { return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id'); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Product::class, 'ingredient_product_id'); }
}
