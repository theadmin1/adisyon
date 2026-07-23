<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'printer_id',
        'device_id',
        'check_id',
        'job_type',
        'printer_type',
        'title',
        'payload',
        'status',
        'error_message',
        'printed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'printed_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }
}
