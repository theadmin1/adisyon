<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductSubmissionItem extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'supplier_product_submission_id',
        'branch_id',
        'product_name',
        'supplier_sku',
        'barcode',
        'brand',
        'unit',
        'package_description',
        'unit_price',
        'tax_rate',
        'minimum_order_quantity',
        'delivery_days',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'minimum_order_quantity' => 'decimal:3',
        'delivery_days' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(SupplierProductSubmission::class, 'supplier_product_submission_id');
    }
}
