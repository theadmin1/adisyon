<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id','source_product_id','target_product_id','product_name','sku','quantity','unit'];
    protected $casts = ['quantity'=>'decimal:3'];
    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function sourceProduct(): BelongsTo { return $this->belongsTo(Product::class, 'source_product_id'); }
    public function targetProduct(): BelongsTo { return $this->belongsTo(Product::class, 'target_product_id'); }
}
