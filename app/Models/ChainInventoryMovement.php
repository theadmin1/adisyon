<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChainInventoryMovement extends Model
{
    protected $fillable = ['organization_id','chain_menu_product_id','branch_id','type','quantity','unit','stock_before','stock_after','created_by_user_id','notes'];
    protected $casts = ['quantity'=>'decimal:3','stock_before'=>'decimal:3','stock_after'=>'decimal:3'];
    public function product(): BelongsTo { return $this->belongsTo(ChainMenuProduct::class,'chain_menu_product_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class,'created_by_user_id'); }
}
