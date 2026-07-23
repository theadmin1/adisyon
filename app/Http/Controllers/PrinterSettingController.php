<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Services\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ayarlar > Yazıcılar sekmesi: termal fiş yazıcılarının tanımlanması,
 * test fişi gönderimi ve yazdırma kuyruğunun izlenmesi.
 */
class PrinterSettingController extends Controller
{
    private const VALIDATION_MESSAGES = [
        'printer_target.required' => 'Ağ, seri port ve USB bağlantılarında hedef adres zorunludur.',
    ];

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['branch_id'] = $this->branchId();

        DB::transaction(function () use ($validated) {
            $printer = Printer::create($validated);
            $this->syncDefault($printer);
        });

        return back()->with('success', "'{$validated['name']}' yazıcısı eklendi.");
    }

    public function update(Request $request, Printer $printer): RedirectResponse
    {
        $validated = $this->validated($request, $printer);

        DB::transaction(function () use ($printer, $validated) {
            $printer->update($validated);
            $this->syncDefault($printer);
        });

        return back()->with('success', "'{$printer->name}' yazıcısı güncellendi.");
    }

    public function destroy(Printer $printer): RedirectResponse
    {
        $name = $printer->name;
        $printer->delete();

        return back()->with('success', "'{$name}' yazıcısı silindi.");
    }

    /**
     * Yazıcıya örnek bir test fişi gönderir (kuyruğa iş bırakır).
     */
    public function test(Printer $printer, PrintService $printService): JsonResponse
    {
        if (!$printer->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Bu yazıcı pasif durumda. Önce aktifleştirin.',
            ], 422);
        }

        $job = $printService->createTestSlip($printer);

        return response()->json([
            'success' => true,
            'message' => 'Test fişi kuyruğa alındı. Cihaz servisi birkaç saniye içinde basacak.',
            'job_id' => $job->id,
        ]);
    }

    /**
     * Başarısız olmuş bir yazdırma işini kuyruğa geri koyar.
     */
    public function requeue(PrintJob $job): JsonResponse
    {
        $job->update([
            'status' => PrintJob::STATUS_PENDING,
            'claimed_at' => null,
            'attempts' => 0,
            'error_message' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => "#{$job->id} numaralı fiş yeniden kuyruğa alındı.",
            'job_id' => $job->id,
        ]);
    }

    // ------------------------------------------------------------------

    private function validated(Request $request, ?Printer $printer = null): array
    {
        // Windows sürücüsünde hedef boş bırakılabilir (varsayılan yazıcı kullanılır),
        // diğer bağlantı tiplerinde adres zorunludur.
        $targetRules = $request->input('connection_type') === 'windows_driver'
            ? ['nullable', 'string', 'max:255']
            : ['required', 'string', 'max:255'];

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:kitchen,cashier,bar',
            'connection_type' => 'required|string|in:windows_driver,network_tcp,serial_com,usb',
            'printer_target' => $targetRules,
            'paper_width' => 'required|integer|in:58,80',
            'char_width' => 'nullable|integer|min:24|max:96',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ], self::VALIDATION_MESSAGES);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_default'] = $request->boolean('is_default');
        $data['codepage'] = 'cp857';

        // Boş bırakılırsa kağıt genişliğinden türetilsin
        if (empty($data['char_width'])) {
            $data['char_width'] = null;
        }

        return $data;
    }

    /**
     * Bir şubede yalnızca tek bir varsayılan yazıcı olabilir.
     */
    private function syncDefault(Printer $printer): void
    {
        if (!$printer->is_default) {
            return;
        }

        Printer::where('branch_id', $printer->branch_id)
            ->whereKeyNot($printer->id)
            ->update(['is_default' => false]);
    }

    /**
     * Kullanıcı tablosunda henüz şube bağı olmadığı için sistemdeki ilk şube kullanılır.
     * (QuickSaleController ile aynı yaklaşım.)
     */
    private function branchId(): int
    {
        return (int) (Branch::query()->orderBy('id')->value('id') ?? 1);
    }
}
