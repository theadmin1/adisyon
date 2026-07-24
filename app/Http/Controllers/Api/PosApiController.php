<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDeviceApiKey;
use App\Models\Check;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\PosTransaction;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Yeni Nesil ÖKC (yazarkasa POS) kart işlemleri API'si.
 *
 * Yazdırma kuyruğuyla aynı deseni izler, tek kritik farkla: ödeme işlemleri
 * OTOMATİK YENİDEN DENENMEZ. Terminal işlemi almış ama sonucu bildirilememiş
 * olabilir; körlemesine tekrar göndermek çift tahsilat riski taşır.
 */
class PosApiController extends Controller
{
    // ==================================================================
    // CİHAZ (C# servis) UÇLARI — X-Device-Api-Key zorunlu
    // ==================================================================

    /**
     * Bekleyen kart işlemlerini cihaza atomik olarak kilitleyerek verir.
     */
    public function getPendingTransactions(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $branchId = $device->branch_id;

        $transactions = DB::transaction(function () use ($branchId, $device) {
            // Cihaz sonuç bildirmeden zaman aşımına uğramış işlemler:
            // yeniden DENENMEZ, doğrudan 'failed' işaretlenir. Kasiyer
            // terminalin ekranından gerçek durumu görüp karar verir.
            PosTransaction::where('branch_id', $branchId)
                ->whereIn('status', PosTransaction::IN_FLIGHT_STATUSES)
                ->where('claimed_at', '<', now()->subSeconds(PosTransaction::CLAIM_TIMEOUT_SECONDS))
                ->update([
                    'status' => PosTransaction::STATUS_FAILED,
                    'claimed_at' => null,
                    'completed_at' => now(),
                    'error_message' => 'Zaman aşımı: terminal sonuç bildirmedi. '
                        . 'Ödemenin geçip geçmediğini terminal ekranından doğrulayın.',
                ]);

            // Kart işlemi müşteri etkileşimi gerektirdiği için aynı anda
            // yalnızca BİR işlem verilir; terminal tek kullanıcılıdır.
            $ids = PosTransaction::where('branch_id', $branchId)
                ->where('status', PosTransaction::STATUS_PENDING)
                ->where('attempts', '<', PosTransaction::MAX_ATTEMPTS)
                ->orderBy('id')
                ->limit(1)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return collect();
            }

            PosTransaction::whereIn('id', $ids)->update([
                'status' => PosTransaction::STATUS_CLAIMED,
                'claimed_at' => now(),
                'device_id' => $device->id,
                'attempts' => DB::raw('attempts + 1'),
            ]);

            return PosTransaction::whereIn('id', $ids)->get();
        });

        return response()->json([
            'success' => true,
            'count' => $transactions->count(),
            'transactions' => $transactions->map(fn (PosTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount_minor' => $tx->amount_minor,
                'currency' => $tx->currency,
                'installment' => $tx->installment,
                'payload' => $tx->payload,
                'created_at' => $tx->created_at?->format('Y-m-d H:i:s'),
            ])->values(),
        ]);
    }

    /**
     * Cihazdan ara durum bildirimi (sent, awaiting_card).
     * Kasiyer ekranında "kart bekleniyor" gösterebilmek için kullanılır.
     */
    public function updateTransactionStatus(Request $request, PosTransaction $transaction): JsonResponse
    {
        $device = $this->device($request);
        $this->authorizeDevice($transaction, $device);

        $validated = $request->validate([
            'status' => 'required|string|in:sent,awaiting_card',
        ]);

        // Sonuçlanmış işlemin durumu geri alınamaz.
        if ($transaction->isFinal()) {
            return response()->json([
                'success' => false,
                'message' => 'İşlem zaten sonuçlanmış.',
                'status' => $transaction->status,
            ], 409);
        }

        $transaction->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'status' => $transaction->status,
        ]);
    }

    /**
     * Terminalden gelen NİHAİ sonuç (onay / red / hata).
     * Onaylanmışsa ödeme kaydı burada oluşur.
     */
    public function submitResult(Request $request, PosTransaction $transaction, PosService $posService): JsonResponse
    {
        $device = $this->device($request);
        $this->authorizeDevice($transaction, $device);

        $validated = $request->validate([
            'status' => 'required|string|in:approved,declined,failed,cancelled',
            'approval_code' => 'nullable|string|max:64',
            'reference_number' => 'nullable|string|max:64',
            'masked_pan' => 'nullable|string|max:32',
            'card_scheme' => 'nullable|string|max:32',
            'card_holder' => 'nullable|string|max:64',
            'bank_name' => 'nullable|string|max:64',
            'terminal_id' => 'nullable|string|max:32',
            'merchant_id' => 'nullable|string|max:32',
            'fiscal_receipt_no' => 'nullable|string|max:64',
            'approved_amount_minor' => 'nullable|integer|min:0',
            'error_code' => 'nullable|string|max:32',
            'error_message' => 'nullable|string|max:500',
            'raw_response' => 'nullable|array',
        ]);

        $alreadyFinal = $transaction->isFinal();
        $transaction = $posService->applyResult($transaction, $validated['status'], $validated);

        DeviceLog::create([
            'device_id' => $device->id,
            'event_type' => 'PosTransaction',
            'ip_address' => $request->ip(),
            'request_payload' => [
                'transaction_id' => $transaction->id,
                'status' => $validated['status'],
                'amount_minor' => $transaction->amount_minor,
                'approval_code' => $validated['approval_code'] ?? null,
            ],
            'response_payload' => [
                'duplicate' => $alreadyFinal,
                'payment_id' => $transaction->payment_id,
            ],
        ]);

        return response()->json([
            'success' => true,
            'duplicate' => $alreadyFinal,
            'transaction' => [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'payment_id' => $transaction->payment_id,
            ],
        ]);
    }

    // ==================================================================
    // WEB POS (tarayıcı) UÇLARI — oturum + CSRF korumalı
    // ==================================================================

    /**
     * Kasiyer ekranından kart ödemesi başlatır.
     */
    public function startPayment(Request $request, Check $check, PosService $posService): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'installment' => 'nullable|integer|min:0|max:12',
        ]);

        try {
            $transaction = $posService->startSale(
                $check,
                (float) $validated['amount'],
                (int) ($validated['installment'] ?? 0)
            );
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kart ödemesi ÖKC cihazına gönderildi.',
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
            'status_text' => $transaction->statusText(),
            'amount' => number_format($transaction->amount(), 2),
        ]);
    }

    /**
     * Kasiyer ekranı için işlem durumunu sorgular (polling).
     */
    public function getTransactionStatus(PosTransaction $transaction): JsonResponse
    {
        return response()->json([
            'success' => true,
            'id' => $transaction->id,
            'status' => $transaction->status,
            'is_final' => $transaction->isFinal(),
            'is_approved' => $transaction->status === PosTransaction::STATUS_APPROVED,
            'status_text' => $transaction->statusText(),
            'amount' => number_format($transaction->amount(), 2),
            'approval_code' => $transaction->approval_code,
            'masked_pan' => $transaction->masked_pan,
            'card_scheme' => $transaction->card_scheme,
            'installment' => $transaction->installment,
            'fiscal_receipt_no' => $transaction->fiscal_receipt_no,
            'error_message' => $transaction->error_message,
        ]);
    }

    /**
     * Kasiyerin bekleyen işlemi iptal etmesi.
     * Yalnızca terminale HENÜZ iletilmemiş işlemler iptal edilebilir; kart
     * okutulmaya başlandıysa iptal terminal üzerinden yapılmalıdır.
     */
    public function cancelTransaction(PosTransaction $transaction): JsonResponse
    {
        if ($transaction->isFinal()) {
            return response()->json([
                'success' => false,
                'message' => 'İşlem zaten sonuçlanmış, iptal edilemez.',
            ], 422);
        }

        if ($transaction->status !== PosTransaction::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'İşlem terminale iletilmiş. İptali terminal ekranından yapmalısınız.',
            ], 422);
        }

        $transaction->update([
            'status' => PosTransaction::STATUS_CANCELLED,
            'completed_at' => now(),
            'error_message' => 'Kasiyer tarafından iptal edildi.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kart işlemi iptal edildi.',
        ]);
    }

    // ------------------------------------------------------------------

    private function device(Request $request): Device
    {
        $device = $request->attributes->get(EnsureDeviceApiKey::ATTRIBUTE);

        abort_unless($device instanceof Device, 401, 'Cihaz doğrulanamadı.');

        return $device;
    }

    /** İşlemin cihazın kendi şubesine ait olduğunu doğrular. */
    private function authorizeDevice(PosTransaction $transaction, Device $device): void
    {
        abort_if(
            $transaction->branch_id !== $device->branch_id,
            403,
            'Bu kart işlemi cihazınızın şubesine ait değil.'
        );
    }
}
