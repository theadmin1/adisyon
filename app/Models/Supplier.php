<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'tax_number', 'contact_person', 'phone', 'email', 'address', 'notes', 'is_active',
        'portal_enabled', 'portal_token_hash', 'portal_token', 'portal_code_hash', 'portal_code', 'portal_credentials_generated_at',
    ];

    protected $hidden = ['portal_token_hash', 'portal_token', 'portal_code_hash', 'portal_code'];

    protected $casts = [
        'is_active' => 'boolean',
        'portal_enabled' => 'boolean',
        'portal_token' => 'encrypted',
        'portal_code' => 'encrypted',
        'portal_credentials_generated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function productSubmissions(): HasMany
    {
        return $this->hasMany(SupplierProductSubmission::class);
    }
}
