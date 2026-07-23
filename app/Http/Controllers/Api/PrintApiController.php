<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureDeviceApiKey;
use App\Models\Check;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintApiController extends Controller
{
    /**
     * Windows C# Servis Programı İçin Bekleyen Yazdırma İşlerini Listeler.
     *
     * İşler ATOMİK olarak "claimed" durumuna alınır (kilitlenir). Böylece:
     *  - Aynı şubede birden fazla cihaz varsa aynı fiş iki kez basılmaz.
     *  - Durum bildirimi ağ hatasıyla düşse bile iş sonsuz döngüde tekrar basılmaz.
     * Cihaz zaman aşımı süresince sonuç bildirmezse iş kuyruğa geri döner
     * (deneme hakkı dolana kadar).
     */
    public function getPendingJobs(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $branchId = $device->branch_id;

        $jobs = DB::transaction(function () use ($branchId, $device) {
            $staleBefore = now()->subSeconds(PrintJob::CLAIM_TIMEOUT_SECONDS);

            // 1) Deneme hakkı dolmuş takılı işleri kalıcı olarak başarısız yap.
            //    (claimed / received / processing / printing durumlarının hepsi kapsanır:
            //     cihaz baskı ortasında çökerse iş 'printing' üzerinde asılı kalırdı.)
            PrintJob::where('branch_id', $branchId)
                ->whereIn('status', PrintJob::IN_FLIGHT_STATUSES)
                ->where('claimed_at', '<', $staleBefore)
                ->where('attempts', '>=', PrintJob::MAX_ATTEMPTS)
                ->update([
                    'status' => 'failed',
                    'claimed_at' => null,
                    'error_message' => 'Cihaz zaman aşımı: yazdırma sonucu bildirilmedi.',
                ]);

            // 2) Cihazın alıp sonuç bildirmediği işleri kuyruğa geri al
            PrintJob::where('branch_id', $branchId)
                ->whereIn('status', PrintJob::IN_FLIGHT_STATUSES)
                ->where('claimed_at', '<', $staleBefore)
                ->update(['status' => PrintJob::STATUS_PENDING, 'claimed_at' => null]);

            // 3) Geçici hata almış işleri sınırlı sayıda yeniden dene
            PrintJob::where('branch_id', $branchId)
                ->where('status', 'failed')
                ->where('attempts', '<', PrintJob::MAX_ATTEMPTS)
                ->where('updated_at', '<', now()->subSeconds(15))
                ->update(['status' => PrintJob::STATUS_PENDING, 'claimed_at' => null]);

            // 4) Bekleyen işleri kilitleyerek bu cihaza ata
            $ids = PrintJob::where('branch_id', $branchId)
                ->where('status', PrintJob::STATUS_PENDING)
                ->orderBy('id')
                ->limit(10)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return collect();
            }

            PrintJob::whereIn('id', $ids)->update([
                'status' => PrintJob::STATUS_CLAIMED,
                'claimed_at' => now(),
                'device_id' => $device->id,
                'attempts' => DB::raw('attempts + 1'),
            ]);

            return PrintJob::with('printer')->whereIn('id', $ids)->orderBy('id')->get();
        });

        return response()->json([
            'success' => true,
            'count' => $jobs->count(),
            'jobs' => $jobs->map(fn (PrintJob $job) => $this->presentJob($job))->values(),
        ]);
    }

    /**
     * Tek bir işi atomik olarak kilitler (Direct Push akışı için).
     * Zaten başka bir cihaz tarafından alınmışsa 409 döner ve o cihaz basmaz.
     */
    public function claimJob(Request $request, PrintJob $job): JsonResponse
    {
        $device = $this->device($request);

        if ($job->branch_id !== $device->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu yazdırma işi cihazınızın şubesine ait değil.',
            ], 403);
        }

        $claimed = PrintJob::whereKey($job->id)
            ->where('status', PrintJob::STATUS_PENDING)
            ->update([
                'status' => PrintJob::STATUS_CLAIMED,
                'claimed_at' => now(),
                'device_id' => $device->id,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        if ($claimed === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu iş zaten başka bir cihaz tarafından alınmış veya tamamlanmış.',
                'status' => $job->fresh()->status,
            ], 409);
        }

        return response()->json([
            'success' => true,
            'job' => $this->presentJob($job->fresh('printer')),
        ]);
    }

    /**
     * C# Servis Programından Yazdırma Durumu Bildirimi (Status Lifecycle).
     * Durumlar: received, processing, printing, completed/printed, failed
     */
    public function updateJobStatus(Request $request, PrintJob $job): JsonResponse
    {
        $device = $this->device($request);

        if ($job->branch_id !== $device->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Bu yazdırma işi cihazınızın şubesine ait değil.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:received,processing,printing,completed,printed,failed',
            'error_message' => 'nullable|string|max:1000',
        ]);

        $newStatus = $validated['status'];
        $errorMessage = $validated['error_message'] ?? null;
        $isTerminal = in_array($newStatus, ['completed', 'printed'], true);

        $job->update([
            'status' => $newStatus,
            'error_message' => $errorMessage,
            'claimed_at' => $isTerminal || $newStatus === 'failed' ? null : $job->claimed_at,
            'printed_at' => $isTerminal ? now() : $job->printed_at,
        ]);

        // Cihaz artık middleware tarafından API Key ile doğrulandığı için log kaydı
        // her zaman doğru cihaza bağlanır.
        $statusLabels = [
            'received' => 'Yazdırma Talebi Alındı',
            'processing' => 'Fiş İşleniyor',
            'printing' => 'Cihaza Gönderiliyor',
            'completed' => 'Yazdırma Tamamlandı',
            'printed' => 'Fiş Yazdırıldı',
            'failed' => 'HATA: Yazdırma Başarısız (' . ($errorMessage ?: 'Yazıcı Hatası') . ')',
        ];

        DeviceLog::create([
            'device_id' => $device->id,
            'event_type' => 'PrintJobStatus',
            'ip_address' => $request->ip(),
            'request_payload' => [
                'job_id' => $job->id,
                'job_type' => $job->job_type,
                'status' => $newStatus,
                'error' => $errorMessage,
            ],
            'response_payload' => [
                'label' => $statusLabels[$newStatus] ?? $newStatus,
                'title' => $job->title,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Yazdırma talebi durumu güncellendi: {$newStatus}",
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'error_message' => $job->error_message,
                'attempts' => $job->attempts,
            ],
        ]);
    }

    /**
     * Web POS ekranı için fiş yazdırma durumunu anlık sorgulama.
     * (Oturum açmış kullanıcı tarafından çağrılır.)
     */
    public function getJobStatus(PrintJob $job): JsonResponse
    {
        $statusTexts = [
            'pending' => '⏳ Yazdırma talebi kuyrukta bekliyor...',
            'claimed' => '📥 Servis programı talebi aldı',
            'received' => '📥 Servis programı talebi aldı',
            'processing' => '⚙️ Fiş hazırlanıyor...',
            'printing' => '🖨️ Termal yazıcıya gönderiliyor...',
            'completed' => '✅ Fiş başarıyla yazdırıldı',
            'printed' => '✅ Fiş başarıyla yazdırıldı',
            'failed' => '❌ Fiş yazdırılamadı (' . ($job->error_message ?: 'Yazıcı yanıt vermiyor') . ')',
        ];

        return response()->json([
            'success' => true,
            'id' => $job->id,
            'status' => $job->status,
            'is_final' => in_array($job->status, ['completed', 'printed', 'failed'], true),
            'status_text' => $statusTexts[$job->status] ?? $job->status,
            'error_message' => $job->error_message,
            'printed_at' => $job->printed_at?->format('d.m.Y H:i:s'),
        ]);
    }

    /**
     * Web ekranından Mutfak Fişi yazdırma tetiklemesi.
     */
    public function printKitchenSlip(Request $request, Check $check, PrintService $printService): JsonResponse
    {
        $job = $printService->createKitchenSlip($check);

        return response()->json([
            'success' => true,
            'message' => 'Mutfak fişi yazdırma talebi oluşturuldu.',
            'job_id' => $job->id,
            'status' => $job->status,
        ]);
    }

    /**
     * Web ekranından Hesap Adisyon Fişi yazdırma tetiklemesi.
     */
    public function printCheckSlip(Request $request, Check $check, PrintService $printService): JsonResponse
    {
        $job = $printService->createCheckSlip($check);

        return response()->json([
            'success' => true,
            'message' => 'Adisyon hesap fişi yazdırma talebi oluşturuldu.',
            'job_id' => $job->id,
            'status' => $job->status,
        ]);
    }

    /**
     * Cihazın kendi şubesine tanımlı yazıcıları getirir.
     */
    public function getPrinters(Request $request): JsonResponse
    {
        $device = $this->device($request);

        $printers = Printer::where('branch_id', $device->branch_id)->get()->map(fn (Printer $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'connection_type' => $p->connection_type,
            'printer_target' => $p->printer_target,
            'paper_width' => $p->paper_width,
            'char_width' => $p->effectiveCharWidth(),
            'codepage' => $p->codepage,
            'is_active' => $p->is_active,
            'is_default' => $p->is_default,
        ]);

        return response()->json([
            'success' => true,
            'printers' => $printers,
        ]);
    }

    /**
     * Cihazın kendi şubesi için yazıcı tanımı ekler / günceller.
     * branch_id istekten DEĞİL, doğrulanan cihazdan alınır.
     */
    public function savePrinter(Request $request): JsonResponse
    {
        $device = $this->device($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:kitchen,cashier,bar',
            'connection_type' => 'required|string|in:windows_driver,network_tcp,serial_com,usb',
            'printer_target' => 'nullable|string|max:255',
            'paper_width' => 'required|integer|in:58,80',
            'char_width' => 'nullable|integer|min:24|max:96',
            'codepage' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $printer = Printer::updateOrCreate(
            [
                'branch_id' => $device->branch_id,
                'type' => $validated['type'],
                'name' => $validated['name'],
            ],
            $validated + ['branch_id' => $device->branch_id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Yazıcı tanımı başarıyla kaydedildi.',
            'printer' => $printer,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * EnsureDeviceApiKey middleware'inin doğruladığı cihaz.
     */
    private function device(Request $request): Device
    {
        $device = $request->attributes->get(EnsureDeviceApiKey::ATTRIBUTE);

        abort_unless($device instanceof Device, 401, 'Cihaz doğrulanamadı.');

        return $device;
    }

    private function presentJob(PrintJob $job): array
    {
        return [
            'id' => $job->id,
            'job_type' => $job->job_type,
            'printer_type' => $job->printer_type,
            'title' => $job->title,
            'status' => $job->status,
            'target_printer' => $job->printer?->printer_target ?: $job->printer?->name ?: '',
            'connection_type' => $job->printer?->connection_type ?? 'windows_driver',
            'paper_width' => $job->printer?->paper_width ?? 80,
            'char_width' => $job->printer?->effectiveCharWidth() ?? Printer::charWidthForPaper(80),
            'codepage' => $job->printer?->codepage ?? 'cp857',
            'payload' => $job->payload,
            'created_at' => $job->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
