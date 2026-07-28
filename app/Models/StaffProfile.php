<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class StaffProfile extends Model
{
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'role',
        'pin_code',
        'pin_hash',
        'pin_length',
        'avatar_color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pin_length' => 'integer',
    ];

    protected $hidden = [
        'pin_code',
        'pin_hash',
        'pin_length',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function verifyPin(string $pin): bool
    {
        if ($this->pin_hash) {
            return Hash::check(trim($pin), $this->pin_hash);
        }

        return hash_equals(trim((string) $this->pin_code), trim($pin));
    }
}
