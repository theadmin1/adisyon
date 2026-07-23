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
        'claimed_at',
        'attempts',
        'error_message',
        'printed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'printed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Kuyruktaki iş durumları.
     * pending  -> kuyrukta, hiçbir cihaz almadı
     * claimed  -> bir cihaz kilitledi, yazdırma sürüyor (received/printing bu aşamanın alt adımları)
     * completed/failed -> bitti
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';

    /**
     * Bir cihazın üzerinde çalıştığı, henüz sonuçlanmamış durumlar.
     * Cihaz bu aşamalardan birinde çökerse iş burada takılı kalır; zaman aşımı
     * süpürmesi bu durumların TAMAMINI kapsamalıdır.
     */
    public const IN_FLIGHT_STATUSES = ['claimed', 'received', 'processing', 'printing'];

    /** Cihaz kilitlediği halde bu süre içinde sonuç bildirmezse iş kuyruğa geri döner (saniye). */
    public const CLAIM_TIMEOUT_SECONDS = 90;

    /** Bir iş en fazla kaç kez denenir (sonsuz tekrar baskı koruması). */
    public const MAX_ATTEMPTS = 3;

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
