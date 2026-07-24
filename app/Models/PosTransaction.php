<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yeni Nesil ÖKC (yazarkasa POS) üzerinde yapılan kart işlemi.
 *
 * Yaşam döngüsü yazdırma kuyruğuyla aynı deseni izler:
 * Laravel işi oluşturur -> cihaz servisi atomik olarak kilitler (claimed)
 * -> fiziki terminale iletir (sent) -> müşteri kart okutur (awaiting_card)
 * -> sonuç bildirilir (approved / declined / failed).
 */
class PosTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'check_id',
        'device_id',
        'payment_id',
        'type',
        'amount_minor',
        'currency',
        'installment',
        'payload',
        'status',
        'claimed_at',
        'attempts',
        'approval_code',
        'reference_number',
        'masked_pan',
        'card_scheme',
        'card_holder',
        'bank_name',
        'terminal_id',
        'merchant_id',
        'fiscal_receipt_no',
        'error_code',
        'error_message',
        'raw_response',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'raw_response' => 'array',
        'amount_minor' => 'integer',
        'installment' => 'integer',
        'attempts' => 'integer',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Cihazın üzerinde çalıştığı, henüz sonuçlanmamış durumlar.
     * Terminal yanıt vermeden cihaz çökerse iş bu aşamalarda takılı kalır.
     */
    public const IN_FLIGHT_STATUSES = ['claimed', 'sent', 'awaiting_card'];

    /** Sonuçlanmış, artık değişmeyecek durumlar. */
    public const FINAL_STATUSES = ['approved', 'declined', 'failed', 'cancelled'];

    /**
     * Kart işlemi müşteri etkileşimi gerektirdiği için yazdırmadan çok daha
     * uzun sürer (kart okutma, PIN girişi, banka onayı).
     */
    public const CLAIM_TIMEOUT_SECONDS = 180;

    /**
     * Ödeme işlemleri OTOMATİK YENİDEN DENENMEZ.
     * Terminal işlemi almış ama sonucu bildirilememiş olabilir; körlemesine
     * tekrar göndermek müşteriden İKİ KEZ tahsilat riski taşır.
     * Zaman aşımına uğrayan işlem 'failed' işaretlenir ve kasiyer karar verir.
     */
    public const MAX_ATTEMPTS = 1;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(Check::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** Kuruş cinsinden tutarı TL olarak döner. */
    public function amount(): float
    {
        return $this->amount_minor / 100;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    /** Kullanıcıya gösterilecek Türkçe durum metni. */
    public function statusText(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '⏳ ÖKC cihazına gönderiliyor...',
            self::STATUS_CLAIMED => '📥 Cihaz servisi işlemi aldı',
            'sent' => '💳 Terminale iletildi',
            'awaiting_card' => '💳 Müşterinin kart okutması bekleniyor...',
            self::STATUS_APPROVED => '✅ Ödeme onaylandı',
            self::STATUS_DECLINED => '❌ Ödeme reddedildi (' . ($this->error_message ?: 'Banka onay vermedi') . ')',
            self::STATUS_FAILED => '❌ İşlem tamamlanamadı (' . ($this->error_message ?: 'Terminal yanıt vermedi') . ')',
            self::STATUS_CANCELLED => '🚫 İşlem iptal edildi',
            default => $this->status,
        };
    }
}
