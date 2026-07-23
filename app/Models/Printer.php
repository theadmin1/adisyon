<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'type',
        'connection_type',
        'printer_target',
        'paper_width',
        'char_width',
        'codepage',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'paper_width' => 'integer',
        'char_width' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Fişin satır genişliği (karakter sayısı).
     * Elle girilmemişse kağıt genişliğinden türetilir: 58mm => 32, 80mm => 48.
     */
    public function effectiveCharWidth(): int
    {
        if ($this->char_width && $this->char_width >= 20) {
            return (int) $this->char_width;
        }

        return static::charWidthForPaper($this->paper_width);
    }

    public static function charWidthForPaper(?int $paperWidth): int
    {
        return ((int) $paperWidth) === 58 ? 32 : 48;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
