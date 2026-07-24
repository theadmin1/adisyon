<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Check;
use App\Models\Payment;
use App\Models\PosTransaction;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Yeni Nesil ÖKC (yazarkasa POS) kart işlemlerini yönetir.
 *
 * PARA İŞLEMİ OLDUĞU İÇİN temel kural: aynı adisyon için aynı anda birden
 * fazla açık kart işlemi olamaz ve sonuç yalnızca bir kez Payment'a yazılır.
 * Aksi halde müşteriden iki kez tahsilat riski doğar.
 */
class PosService
{
    /**
     * Adisyon için kart ödeme işlemi başlatır (ÖKC kuyruğuna bırakır).
     *
     * @param  float  $amount  Tahsil edilecek tutar (TL). Kısmi ödeme olabilir.
     */
    public function startSale(Check $check, float $amount, int $installment = 0): PosTransaction
    {
        return DB::transaction(function () use ($check, $amount, $installment) {
            // Aynı adisyonda devam eden bir kart işlemi varsa yenisine izin verme.
            $inFlight = PosTransaction::where('check_id', $check->id)
                ->whereIn('status', array_merge(
                    [PosTransaction::STATUS_PENDING],
                    PosTransaction::IN_FLIGHT_STATUSES
                ))
                ->lockForUpdate()
                ->first();

            if ($inFlight) {
                throw new RuntimeException(
                    "Bu adisyon için devam eden bir kart işlemi var (#{$inFlight->id}). " .
                    'Önce onun sonuçlanmasını bekleyin veya iptal edin.'
                );
            }

            $remaining = $this->remainingBalance($check);

            if ($amount <= 0) {
                throw new RuntimeException('Tahsil edilecek tutar sıfırdan büyük olmalıdır.');
            }

            // Kalan bakiyeden fazla tahsilat engellenir (yuvarlama payı: 1 kuruş).
            if ($amount > $remaining + 0.01) {
                throw new RuntimeException(
                    'Tahsil edilecek tutar kalan bakiyeden fazla olamaz. ' .
                    'Kalan: ' . number_format($remaining, 2) . ' TL'
                );
            }

            return PosTransaction::create([
                'branch_id' => $check->branch_id ?? $this->defaultBranchId(),
                'check_id' => $check->id,
                'type' => 'sale',
                // Kuruşa çevrilir: ÖKC protokolleri tam sayı bekler, float
                // yuvarlama farkı tahsilat tutarını kaydırabilir.
                'amount_minor' => (int) round($amount * 100),
                'currency' => 'TRY',
                'installment' => max(0, $installment),
                'payload' => $this->buildPayload($check, $amount, $installment),
                'status' => PosTransaction::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Terminalden gelen sonucu işler ve onaylanmışsa Payment kaydını oluşturur.
     *
     * İdempotanttır: aynı işlem ikinci kez bildirilse bile tek Payment oluşur.
     *
     * @param  array  $result  Terminal yanıtı (approval_code, masked_pan, ...)
     */
    public function applyResult(PosTransaction $transaction, string $status, array $result = []): PosTransaction
    {
        return DB::transaction(function () use ($transaction, $status, $result) {
            // Kilitle ve tazele: eşzamanlı iki bildirim çift ödeme yaratmasın.
            $transaction = PosTransaction::whereKey($transaction->id)->lockForUpdate()->first();

            if ($transaction->isFinal()) {
                // Sonuç zaten işlenmiş; tekrar Payment oluşturma.
                return $transaction;
            }

            $transaction->fill([
                'status' => $status,
                'approval_code' => $result['approval_code'] ?? null,
                'reference_number' => $result['reference_number'] ?? null,
                'masked_pan' => $this->maskPan($result['masked_pan'] ?? null),
                'card_scheme' => $result['card_scheme'] ?? null,
                'card_holder' => $result['card_holder'] ?? null,
                'bank_name' => $result['bank_name'] ?? null,
                'terminal_id' => $result['terminal_id'] ?? null,
                'merchant_id' => $result['merchant_id'] ?? null,
                'fiscal_receipt_no' => $result['fiscal_receipt_no'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'error_message' => $result['error_message'] ?? null,
                'raw_response' => $result['raw_response'] ?? null,
                'claimed_at' => null,
            ]);

            if (in_array($status, PosTransaction::FINAL_STATUSES, true)) {
                $transaction->completed_at = now();
            }

            $transaction->save();

            if ($status === PosTransaction::STATUS_APPROVED) {
                $this->recordApprovedPayment($transaction, $result);
            }

            return $transaction->fresh();
        });
    }

    /**
     * Onaylanan kart işlemi için ödeme kaydı oluşturur.
     *
     * Terminal farklı bir tutar onayladıysa (kısmi onay) ödeme O tutarla
     * kaydedilir — kasa defteri her zaman gerçekten tahsil edilen parayı gösterir.
     */
    protected function recordApprovedPayment(PosTransaction $transaction, array $result): void
    {
        if ($transaction->payment_id) {
            return; // Zaten kaydedilmiş
        }

        $approvedMinor = isset($result['approved_amount_minor'])
            ? (int) $result['approved_amount_minor']
            : $transaction->amount_minor;

        $payment = Payment::create([
            'check_id' => $transaction->check_id,
            'payment_method' => 'kredi_karti',
            'pos_transaction_id' => $transaction->id,
            'amount' => $approvedMinor / 100,
            'approval_code' => $transaction->approval_code,
            'masked_pan' => $transaction->masked_pan,
            'installment' => $transaction->installment,
        ]);

        $transaction->forceFill(['payment_id' => $payment->id])->save();
    }

    /**
     * ÖKC'ye gönderilecek satış kalemleri ve KDV kırılımı.
     *
     * Mali fiş yasal bir belge olduğu için ürün adı, adet, birim fiyat ve
     * KDV oranı kalem bazında gönderilir.
     */
    protected function buildPayload(Check $check, float $amount, int $installment): array
    {
        $check->loadMissing('items', 'diningTable');

        $items = [];
        $vatBreakdown = [];

        foreach ($check->items->where('is_cancelled', false) as $item) {
            $rate = (float) ($item->vat_rate ?? 0);
            $lineTotal = (float) $item->total_price;
            $isComplimentary = (bool) $item->is_complimentary;

            $items[] = [
                'name' => $item->product_name ?: 'Ürün',
                'quantity' => (float) $item->quantity,
                'unit_price_minor' => (int) round((float) $item->unit_price * 100),
                'total_minor' => (int) round(($isComplimentary ? 0 : $lineTotal) * 100),
                'vat_rate' => $rate,
                'is_complimentary' => $isComplimentary,
            ];

            if (!$isComplimentary && $rate > 0) {
                $key = (string) $rate;
                $vat = $lineTotal - ($lineTotal / (1 + $rate / 100));

                $vatBreakdown[$key] ??= ['rate' => $rate, 'gross_minor' => 0, 'vat_minor' => 0];
                $vatBreakdown[$key]['gross_minor'] += (int) round($lineTotal * 100);
                $vatBreakdown[$key]['vat_minor'] += (int) round($vat * 100);
            }
        }

        return [
            'check_number' => $check->check_number,
            'table' => $check->diningTable?->name ?: 'Hızlı Satış',
            'merchant_name' => Setting::get('restaurant_name', 'AltF4 Adisyon'),
            'requested_amount_minor' => (int) round($amount * 100),
            'check_total_minor' => (int) round((float) $check->total * 100),
            'installment' => $installment,
            'items' => $items,
            'vat_breakdown' => array_values($vatBreakdown),
            // Kısmi ödemede ÖKC'ye tam adisyon kalemleri gönderilmemeli;
            // cihaz tarafı bu bayrağa göre mali fiş içeriğine karar verir.
            'is_partial' => (int) round($amount * 100) < (int) round((float) $check->total * 100),
        ];
    }

    /** Adisyonun henüz tahsil edilmemiş bakiyesi. */
    public function remainingBalance(Check $check): float
    {
        $paid = (float) $check->payments()->sum('amount');

        return round(max(0, (float) $check->total - $paid), 2);
    }

    /**
     * Kart numarasının yalnızca maskeli hali saklanır.
     * Tam PAN hiçbir koşulda veritabanına yazılmamalıdır (PCI-DSS).
     */
    protected function maskPan(?string $pan): ?string
    {
        if (empty($pan)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $pan) ?? '';

        // Terminal zaten maskeli gönderdiyse (içinde * varsa) olduğu gibi bırak.
        if (str_contains($pan, '*')) {
            return substr($pan, 0, 24);
        }

        if (strlen($digits) < 8) {
            return substr($pan, 0, 24);
        }

        return substr($digits, 0, 6) . str_repeat('*', max(0, strlen($digits) - 10)) . substr($digits, -4);
    }

    protected function defaultBranchId(): int
    {
        return (int) (Branch::query()->orderBy('id')->value('id') ?? 1);
    }
}
