<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = ['organization_id','source_branch_id','target_branch_id','created_by_user_id','approved_by_user_id','received_by_user_id','transfer_number','status','notes','approved_at','shipped_at','received_at','cancelled_at'];
    protected $casts = ['approved_at'=>'datetime','shipped_at'=>'datetime','received_at'=>'datetime','cancelled_at'=>'datetime'];
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function sourceBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'source_branch_id'); }
    public function targetBranch(): BelongsTo { return $this->belongsTo(Branch::class, 'target_branch_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function items(): HasMany { return $this->hasMany(StockTransferItem::class); }
}
