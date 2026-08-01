<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionWorkflowItem extends Model
{
    use HasFactory;

    protected $fillable = ['production_workflow_id', 'product_id', 'product_name', 'stock_unit', 'recipe_quantity', 'recipe_unit', 'required_quantity', 'consumed_quantity', 'stock_before', 'stock_after'];
    protected $casts = ['recipe_quantity' => 'decimal:4', 'required_quantity' => 'decimal:4', 'consumed_quantity' => 'decimal:4', 'stock_before' => 'decimal:4', 'stock_after' => 'decimal:4'];

    public function workflow(): BelongsTo { return $this->belongsTo(ProductionWorkflow::class, 'production_workflow_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
