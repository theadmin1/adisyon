<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionWorkflow extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'production_recipe_id', 'created_by_user_id', 'completed_by_user_id', 'workflow_number', 'recipe_name', 'planned_servings', 'status', 'scheduled_for', 'started_at', 'completed_at', 'cancelled_at', 'notes'];

    protected $casts = [
        'planned_servings' => 'decimal:3',
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function recipe(): BelongsTo { return $this->belongsTo(ProductionRecipe::class, 'production_recipe_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by_user_id'); }
    public function items(): HasMany { return $this->hasMany(ProductionWorkflowItem::class); }
}
