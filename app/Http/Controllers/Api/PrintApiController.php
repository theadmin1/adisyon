<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintApiController extends Controller
{
    /**
     * Windows C# Servis Programı İçin Bekleyen Yazdırma İşlerini Listeler (Polling / WebSocket Fetch)
     */
    public function getPendingJobs(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id', 1);

        // Cihaz Kimliği / API Key ile şube doğrulama
        $apiKey = $request->header('X-Device-Api-Key') ?? $request->query('api_key');
        if ($apiKey) {
            $device = Device::where('api_key', $apiKey)->first();
            if ($device) {
                $branchId = $device->branch_id;
                $device->update(['last_ping_at' => now(), 'status' => 'Online']);
            }
        }

        $pendingJobs = PrintJob::with('printer')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['pending'])
            ->orderBy('id', 'asc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $pendingJobs->count(),
            'jobs' => $pendingJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_type' => $job->job_type,
                    'printer_type' => $job->printer_type,
                    'title' => $job->title,
                    'status' => $job->status,
                    'target_printer' => $job->printer->printer_target ?? $job->printer->name ?? 'Default POS Printer',
                    'connection_type' => $job->printer->connection_type ?? 'windows_driver',
                    'paper_width' => $job->printer->paper_width ?? 80,
                    'payload' => $job->payload,
                    'created_at' => $job->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * C# Servis Programından Yazdırma Talebi Bildirimi Alındı / Yazdırıldı / Hata Bildirimi (Status Lifecycle)
     * Durumlar: 'received' (Talebi Aldı), 'printing' (Yazdırılıyor), 'completed' / 'printed' (Tamamlandı), 'failed' (Hatalı)
     */
    public function updateJobStatus(Request $request, PrintJob $job): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:received,processing,printing,completed,printed,failed,pending',
            'error_message' => 'nullable|string',
            'device_guid' => 'nullable|string',
        ]);

        $newStatus = $validated['status'];
        $errorMessage = $validated['error_message'] ?? null;

        $job->update([
            'status' => $newStatus,
            'error_message' => $errorMessage,
            'printed_at' => in_array($newStatus, ['completed', 'printed']) ? now() : $job->printed_at,
        ]);

        // Cihaz Log Bildirimi Oluştur
        $deviceGuid = $validated['device_guid'] ?? $request->header('X-Device-Guid');
        $device = $deviceGuid ? Device::where('device_guid', $deviceGuid)->first() : null;

        if ($device) {
            $statusLabels = [
                'received' => '📥 Yazdırma Talebi Alındı',
                'processing' => '⚙️ Fiş İşleniyor',
                'printing' => '🖨️ Cihaza Gönderiliyor',
                'completed' => '✅ Yazdırma Tamamlandı',
                'printed' => '✅ Fiş Yazdırıldı',
                'failed' => '❌ HATA: Yazdırma Başarısız (' . ($errorMessage ?: 'Yazıcı Hatası') . ')',
            ];
            $label = $statusLabels[$newStatus] ?? $newStatus;

            DeviceLog::create([
                'device_id' => $device->id,
                'log_type' => $newStatus === 'failed' ? 'Error' : 'Info',
                'message' => "Fiş Yazdırma Bildirimi [#{$job->id} - {$job->title}]: {$label}",
                'details' => json_encode([
                    'job_id' => $job->id,
                    'job_type' => $job->job_type,
                    'status' => $newStatus,
                    'error' => $errorMessage,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Yazdırma talebi durumu güncellendi: {$newStatus}",
            'job' => [
                'id' => $job->id,
                'status' => $job->status,
                'error_message' => $job->error_message,
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Web POS / Kullanıcı Ekranı İçin Fiş Yazdırma Durumunu Anlık Sorgulama (Real-Time Status Check)
     */
    public function getJobStatus(PrintJob $job): JsonResponse
    {
        $statusTexts = [
            'pending' => '⏳ Yazdırma Talebi Kuyrukta Bekliyor...',
            'received' => '📥 Servis Programı Talebi Aldı',
            'processing' => '⚙️ Cihaz Hazırlanıyor...',
            'printing' => '🖨️ Termal Yazıcıya Gönderiliyor...',
            'completed' => '✅ Fiş Başarıyla Yazdırıldı',
            'printed' => '✅ Fiş Başarıyla Yazdırıldı',
            'failed' => '❌ HATA: Fiş Yazdırılamadı (' . ($job->error_message ?: 'Yazıcı yanıt vermiyor') . ')',
        ];

        return response()->json([
            'success' => true,
            'id' => $job->id,
            'status' => $job->status,
            'status_text' => $statusTexts[$job->status] ?? $job->status,
            'error_message' => $job->error_message,
            'printed_at' => $job->printed_at ? $job->printed_at->format('d.m.Y H:i:s') : null,
        ]);
    }

    /**
     * Manuel / Web Ekranından Mutfak Fişi Yazdırma Tetiklemesi
     */
    public function printKitchenSlip(Request $request, Check $check, PrintService $printService): JsonResponse
    {
        $job = $printService->createKitchenSlip($check);

        return response()->json([
            'success' => true,
            'message' => 'Mutfak fişi yazdırma talebi oluşturuldu.',
            'job_id' => $job->id,
            'status' => $job->status,
            'payload' => $job->payload,
        ]);
    }

    /**
     * Manuel / Web Ekranından Hesap Adisyon Fişi Yazdırma Tetiklemesi
     */
    public function printCheckSlip(Request $request, Check $check, PrintService $printService): JsonResponse
    {
        $job = $printService->createCheckSlip($check);

        return response()->json([
            'success' => true,
            'message' => 'Adisyon hesap fişi yazdırma talebi oluşturuldu.',
            'job_id' => $job->id,
            'status' => $job->status,
            'payload' => $job->payload,
        ]);
    }

    /**
     * Şube Yazıcı Tanımlarını Getir
     */
    public function getPrinters(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id', 1);
        $printers = Printer::where('branch_id', $branchId)->get();

        return response()->json([
            'success' => true,
            'printers' => $printers,
        ]);
    }

    /**
     * Yeni Yazıcı Tanımı Ekle / Güncelle
     */
    public function savePrinter(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:kitchen,cashier,bar',
            'connection_type' => 'required|string|in:windows_driver,network_tcp,serial_com,usb',
            'printer_target' => 'nullable|string',
            'paper_width' => 'required|integer|in:58,80',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $printer = Printer::updateOrCreate(
            [
                'branch_id' => $validated['branch_id'],
                'type' => $validated['type'],
                'name' => $validated['name'],
            ],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Yazıcı tanımı başarıyla kaydedildi.',
            'printer' => $printer,
        ]);
    }
}
