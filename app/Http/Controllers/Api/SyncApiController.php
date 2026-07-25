<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DeviceLog;
use App\Models\OfflineSyncLog;
use App\Models\Payment;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncApiController extends Controller
{
    /**
     * Cihazdan gelen çevrimdışı (offline) adisyon, fiş, ödeme ve stok hareketlerini senkronize eder.
     */
    public function pushOfflineData(Request $request): JsonResponse
    {
        $device = $request->attributes->get('validated_device');

        $validated = $request->validate([
            'batch_id' => 'required|string',
            'checks' => 'nullable|array',
            'checks.*.sync_uuid' => 'required|string',
            'checks.*.dining_table_id' => 'nullable|integer',
            'checks.*.user_id' => 'nullable|integer',
            'checks.*.staff_profile_id' => 'nullable|integer',
            'checks.*.total_amount' => 'required|numeric',
            'checks.*.discount_amount' => 'nullable|numeric',
            'checks.*.status' => 'required|string',
            'checks.*.created_at' => 'nullable|string',
            'checks.*.items' => 'nullable|array',
            'checks.*.items.*.sync_uuid' => 'required|string',
            'checks.*.items.*.product_id' => 'required|integer',
            'checks.*.items.*.product_name' => 'required|string',
            'checks.*.items.*.unit_price' => 'required|numeric',
            'checks.*.items.*.quantity' => 'required|integer',
            'checks.*.items.*.total_price' => 'required|numeric',
            'checks.*.items.*.status' => 'nullable|string',
            'payments' => 'nullable|array',
            'payments.*.sync_uuid' => 'required|string',
            'payments.*.check_sync_uuid' => 'nullable|string',
            'payments.*.amount' => 'required|numeric',
            'payments.*.payment_method' => 'required|string',
            'payments.*.created_at' => 'nullable|string',
            'stock_movements' => 'nullable|array',
            'stock_movements.*.sync_uuid' => 'required|string',
            'stock_movements.*.product_id' => 'required|integer',
            'stock_movements.*.type' => 'required|string',
            'stock_movements.*.quantity' => 'required|integer',
        ]);

        $syncedUuids = [];
        $failedCount = 0;

        try {
            DB::transaction(function () use ($validated, $device, &$syncedUuids, &$failedCount) {
                // 1. Checks & Items Senkronizasyonu
                if (!empty($validated['checks'])) {
                    foreach ($validated['checks'] as $cData) {
                        $syncUuid = $cData['sync_uuid'];

                        // Idempotency: Zaten kaydedilmişse atla ama syncedUuids'e ekle
                        $existingCheck = Check::where('sync_uuid', $syncUuid)->first();
                        if ($existingCheck) {
                            $syncedUuids[] = $syncUuid;
                            continue;
                        }

                        $check = Check::create([
                            'branch_id' => $device ? $device->branch_id : 1,
                            'sync_uuid' => $syncUuid,
                            'dining_table_id' => $cData['dining_table_id'] ?? null,
                            'user_id' => $cData['user_id'] ?? null,
                            'staff_profile_id' => $cData['staff_profile_id'] ?? null,
                            'total_amount' => $cData['total_amount'],
                            'discount_amount' => $cData['discount_amount'] ?? 0,
                            'status' => $cData['status'],
                            'is_synced' => true,
                            'synced_at' => now(),
                            'created_at' => $cData['created_at'] ?? now(),
                        ]);

                        if (!empty($cData['items'])) {
                            foreach ($cData['items'] as $iData) {
                                CheckItem::create([
                                    'check_id' => $check->id,
                                    'sync_uuid' => $iData['sync_uuid'],
                                    'product_id' => $iData['product_id'],
                                    'product_name' => $iData['product_name'],
                                    'unit_price' => $iData['unit_price'],
                                    'quantity' => $iData['quantity'],
                                    'total_price' => $iData['total_price'],
                                    'status' => $iData['status'] ?? 'pending',
                                    'is_synced' => true,
                                ]);
                            }
                        }

                        $syncedUuids[] = $syncUuid;

                        OfflineSyncLog::create([
                            'device_id' => $device?->id,
                            'branch_id' => $device?->branch_id,
                            'sync_uuid' => $syncUuid,
                            'payload_type' => 'check',
                            'status' => 'success',
                            'details' => ['amount' => $cData['total_amount'], 'status' => $cData['status']],
                            'synced_at' => now(),
                        ]);
                    }
                }

                // 2. Payments Senkronizasyonu
                if (!empty($validated['payments'])) {
                    foreach ($validated['payments'] as $pData) {
                        $syncUuid = $pData['sync_uuid'];

                        $existingPayment = Payment::where('sync_uuid', $syncUuid)->first();
                        if ($existingPayment) {
                            $syncedUuids[] = $syncUuid;
                            continue;
                        }

                        $checkId = null;
                        if (!empty($pData['check_sync_uuid'])) {
                            $check = Check::where('sync_uuid', $pData['check_sync_uuid'])->first();
                            $checkId = $check?->id;
                        }

                        Payment::create([
                            'check_id' => $checkId,
                            'sync_uuid' => $syncUuid,
                            'amount' => $pData['amount'],
                            'payment_method' => $pData['payment_method'],
                            'is_synced' => true,
                            'created_at' => $pData['created_at'] ?? now(),
                        ]);

                        $syncedUuids[] = $syncUuid;

                        OfflineSyncLog::create([
                            'device_id' => $device?->id,
                            'branch_id' => $device?->branch_id,
                            'sync_uuid' => $syncUuid,
                            'payload_type' => 'payment',
                            'status' => 'success',
                            'details' => ['amount' => $pData['amount'], 'method' => $pData['payment_method']],
                            'synced_at' => now(),
                        ]);
                    }
                }

                // 3. Stock Movements Senkronizasyonu
                if (!empty($validated['stock_movements'])) {
                    foreach ($validated['stock_movements'] as $sData) {
                        $syncUuid = $sData['sync_uuid'];

                        $existingStock = StockMovement::where('sync_uuid', $syncUuid)->first();
                        if ($existingStock) {
                            $syncedUuids[] = $syncUuid;
                            continue;
                        }

                        StockMovement::create([
                            'branch_id' => $device ? $device->branch_id : 1,
                            'sync_uuid' => $syncUuid,
                            'product_id' => $sData['product_id'],
                            'type' => $sData['type'],
                            'quantity' => $sData['quantity'],
                            'status' => 'approved',
                            'is_synced' => true,
                        ]);

                        $syncedUuids[] = $syncUuid;
                    }
                }
            });

            if ($device) {
                DeviceLog::create([
                    'device_id' => $device->id,
                    'log_type' => 'INFO',
                    'message' => "Çevrimdışı veri senkronizasyonu tamamlandı. Toplam " . count($syncedUuids) . " öge aktarıldı.",
                    'details' => ['synced_count' => count($syncedUuids), 'batch_id' => $validated['batch_id']],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Çevrimdışı veriler başarıyla senkronize edildi.',
                'synced_uuids' => $syncedUuids,
                'synced_count' => count($syncedUuids),
            ]);

        } catch (\Throwable $ex) {
            Log::error('Çevrimdışı Veri Senkronizasyon Hatası: ' . $ex->getMessage(), [
                'trace' => $ex->getTraceAsString(),
            ]);

            if ($device) {
                OfflineSyncLog::create([
                    'device_id' => $device->id,
                    'branch_id' => $device->branch_id,
                    'sync_uuid' => $validated['batch_id'] ?? 'BATCH_ERR',
                    'payload_type' => 'batch',
                    'status' => 'error',
                    'error_message' => $ex->getMessage(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Senkronizasyon sırasında hata oluştu: ' . $ex->getMessage(),
            ], 500);
        }
    }

    /**
     * Uzak MySQL sunucusundaki usta verileri (Users, Products, Categories, Halls, Tables, Active Checks) cihazın yerel veritabanı için dışa aktarır.
     */
    public function pullSyncData(Request $request): JsonResponse
    {
        try {
            $users = \App\Models\User::all();
            $staffProfiles = \App\Models\StaffProfile::all();
            $halls = \App\Models\Hall::all();
            $categories = \App\Models\Category::all();
            $products = \App\Models\Product::all();

            $openStatuses = [\App\Enums\CheckStatus::Open, \App\Enums\CheckStatus::AwaitingPayment, 'open', 'awaiting_payment'];
            $checks = \App\Models\Check::with('items')->whereIn('status', $openStatuses)->get();
            $openCheckTableIds = $checks->pluck('dining_table_id')->filter()->unique()->toArray();

            $tables = \App\Models\DiningTable::all()->map(function ($table) use ($openCheckTableIds) {
                return [
                    'id' => $table->id,
                    'branch_id' => $table->branch_id ?? 1,
                    'hall_id' => $table->hall_id,
                    'name' => $table->name,
                    'code' => $table->code,
                    'capacity' => $table->capacity,
                    'occupant_count' => $table->occupant_count,
                    'status' => in_array($table->id, $openCheckTableIds) ? 'occupied' : 'available',
                    'is_active' => $table->is_active,
                    'notes' => $table->notes,
                    'created_at' => $table->created_at,
                    'updated_at' => $table->updated_at,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'users' => $users,
                    'staff_profiles' => $staffProfiles,
                    'halls' => $halls,
                    'tables' => $tables,
                    'categories' => $categories,
                    'products' => $products,
                    'checks' => $checks,
                ]
            ]);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Usta veri indirme hatası: ' . $ex->getMessage()
            ], 500);
        }
    }
}
