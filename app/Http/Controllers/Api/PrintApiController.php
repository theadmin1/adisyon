<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\Device;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Services\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintApiController extends Controller
{
    /**
     * Windows C# Servis Programı İçin Bekleyen Yazdırma İşlerini Listeler (Polling)
     */
    public function getPendingJobs(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id', 1);

        // Cihaz Kimliği / API Key ile şube doğrulama (opsiyonel)
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
            ->where('status', 'pending')
            ->orderBy('id', 'asc')
            ->take(10)
            ->get();

        // Yazdırma durumunu 'printing' yapalım ki tekrar çekilmesin
        foreach ($pendingJobs as $job) {
            $job->update(['status' => 'printing']);
        }

        return response()->json([
            'success' => true,
            'count' => $pendingJobs->count(),
            'jobs' => $pendingJobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'job_type' => $job->job_type,
                    'printer_type' => $job->printer_type,
                    'title' => $job->title,
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
     * C# Servis Programından Yazdırma Sonucunu Güncelleme
     */
    public function updateJobStatus(Request $request, PrintJob $job): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:printed,failed,pending',
            'error_message' => 'nullable|string',
        ]);

        $job->update([
            'status' => $validated['status'],
            'error_message' => $validated['error_message'] ?? null,
            'printed_at' => $validated['status'] === 'printed' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Yazdırma görevi durumu güncellendi: {$job->status}",
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
            'message' => 'Mutfak fişi yazdırma kuyruğuna eklendi.',
            'job_id' => $job->id,
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
            'message' => 'Adisyon hesap fişi yazdırma kuyruğuna eklendi.',
            'job_id' => $job->id,
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
