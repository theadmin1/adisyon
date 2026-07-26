<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DeviceLog;
use App\Models\OfflineSyncLog;
use App\Models\Payment;
use App\Models\Product;
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
        $device = $request->attributes->get('device') ?? $request->attributes->get('validated_device');

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
            'checks.*.items.*.quantity' => 'required|numeric',
            'checks.*.items.*.total_price' => 'required|numeric',
            'checks.*.items.*.status' => 'nullable|string',
            'check_items' => 'nullable|array',
            'check_items.*.sync_uuid' => 'required|string',
            'check_items.*.check_sync_uuid' => 'nullable|string',
            'check_items.*.dining_table_id' => 'nullable|integer',
            'check_items.*.product_id' => 'required|integer',
            'check_items.*.product_name' => 'required|string',
            'check_items.*.unit_price' => 'required|numeric',
            'check_items.*.quantity' => 'required|numeric',
            'check_items.*.total_price' => 'required|numeric',
            'check_items.*.status' => 'nullable|string',
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
            'stock_movements.*.quantity' => 'required|numeric',
            'stock_movements.*.notes' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*.sync_uuid' => 'required|string',
            'categories.*.name' => 'required|string',
            'products' => 'nullable|array',
            'products.*.sync_uuid' => 'required|string',
            'products.*.name' => 'required|string',
            'deleted_products' => 'nullable|array',
            'deleted_categories' => 'nullable|array',
        ]);

        $branchId = $device ? $device->branch_id : 1;
        $syncedUuids = [];

        try {
            DB::transaction(function () use ($validated, $device, $branchId, &$syncedUuids) {
                
                // 1. Categories Senkronizasyonu (İLK SIRADA)
                if (!empty($validated['categories'])) {
                    foreach ($validated['categories'] as $catData) {
                        $syncUuid = $catData['sync_uuid'];
                        Category::updateOrCreate(
                            ['sync_uuid' => $syncUuid],
                            [
                                'name' => $catData['name'],
                                'slug' => $catData['slug'] ?? \Illuminate\Support\Str::slug($catData['name']),
                                'sort_order' => $catData['sort_order'] ?? 0,
                                'is_active' => $catData['is_active'] ?? true,
                                'is_synced' => true,
                            ]
                        );
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 2. Products Senkronizasyonu (İKİNCİ SIRADA - FK için önce ürünler yüklenmeli)
                if (!empty($validated['products'])) {
                    foreach ($validated['products'] as $pData) {
                        $syncUuid = $pData['sync_uuid'];
                        $catId = $pData['category_id'] ?? null;
                        if (!empty($pData['category_sync_uuid'])) {
                            $cat = Category::where('sync_uuid', $pData['category_sync_uuid'])->first();
                            if ($cat) $catId = $cat->id;
                        }
                        if (!$catId || !Category::where('id', $catId)->exists()) {
                            $catId = Category::first()?->id ?? 1;
                        }

                        Product::updateOrCreate(
                            ['sync_uuid' => $syncUuid],
                            [
                                'category_id' => $catId,
                                'branch_id' => $pData['branch_id'] ?? $branchId,
                                'name' => $pData['name'],
                                'slug' => $pData['slug'] ?? \Illuminate\Support\Str::slug($pData['name']),
                                'sku' => $pData['sku'] ?? null,
                                'price' => $pData['price'] ?? 0,
                                'discounted_price' => $pData['discounted_price'] ?? null,
                                'stock_quantity' => $pData['stock_quantity'] ?? 0,
                                'min_stock_level' => $pData['min_stock_level'] ?? 0,
                                'unit' => $pData['unit'] ?? 'adet',
                                'track_stock' => $pData['track_stock'] ?? false,
                                'description' => $pData['description'] ?? null,
                                'kitchen_department' => $pData['kitchen_department'] ?? null,
                                'is_active' => $pData['is_active'] ?? true,
                                'is_synced' => true,
                            ]
                        );
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 3. Checks & Items Senkronizasyonu
                if (!empty($validated['checks'])) {
                    foreach ($validated['checks'] as $cData) {
                        $syncUuid = $cData['sync_uuid'];

                        $existingCheck = null;
                        if (!empty($syncUuid)) {
                            $existingCheck = Check::where('sync_uuid', $syncUuid)->first();
                        }
                        if (!$existingCheck && !empty($cData['dining_table_id'])) {
                            $existingCheck = Check::where('dining_table_id', $cData['dining_table_id'])
                                ->where('status', 'open')
                                ->first();
                        }

                        $totalAmount = $cData['total_amount'] ?? $cData['total'] ?? 0;
                        $discountAmount = $cData['discount_amount'] ?? $cData['discount_total'] ?? 0;
                        $subtotal = $totalAmount + $discountAmount;
                        $status = $cData['status'] ?? 'open';
                        
                        if ($existingCheck) {
                            $existingCheck->update([
                                'subtotal' => max($subtotal, $existingCheck->subtotal),
                                'discount_total' => $discountAmount,
                                'total' => max($totalAmount, $existingCheck->total),
                                'status' => $status,
                                'synced_at' => now(),
                            ]);
                            $check = $existingCheck;
                        } else {
                            $waiterId = $cData['waiter_id'] ?? $cData['user_id'] ?? $cData['staff_profile_id'] ?? null;
                            $checkNumber = $cData['check_number'] ?? ('CHK-' . strtoupper(substr(md5($syncUuid), 0, 8)));

                            $check = Check::create([
                                'branch_id' => $branchId,
                                'dining_table_id' => $cData['dining_table_id'] ?? null,
                                'waiter_id' => $waiterId,
                                'check_number' => $checkNumber,
                                'sync_uuid' => $syncUuid,
                                'is_synced' => true,
                                'status' => $status,
                                'subtotal' => $subtotal,
                                'discount_total' => $discountAmount,
                                'total' => $totalAmount,
                                'opened_at' => $cData['created_at'] ?? now(),
                                'created_at' => $cData['created_at'] ?? now(),
                            ]);
                        }

                        if (!empty($cData['items'])) {
                            foreach ($cData['items'] as $iData) {
                                $itemSyncUuid = $iData['sync_uuid'];
                                $existingItem = CheckItem::where('sync_uuid', $itemSyncUuid)->first();
                                
                                if (!empty($iData['is_cancelled'])) {
                                    if ($existingItem) {
                                        $existingItem->delete();
                                    }
                                    $syncedUuids[] = $itemSyncUuid;
                                    continue;
                                }

                                $prodId = $iData['product_id'] ?? null;
                                if (!empty($iData['product_sync_uuid'])) {
                                    $p = Product::where('sync_uuid', $iData['product_sync_uuid'])->first();
                                    if ($p) $prodId = $p->id;
                                }
                                if (!$prodId || !Product::where('id', $prodId)->exists()) {
                                    $prodId = Product::first()?->id ?? 1;
                                }

                                if ($existingItem) {
                                    $existingItem->update([
                                        'quantity' => $iData['quantity'],
                                        'total_price' => $iData['total_price'],
                                        'kitchen_status' => $iData['status'] ?? $iData['kitchen_status'] ?? $existingItem->kitchen_status,
                                        'is_synced' => true,
                                    ]);
                                } else {
                                    CheckItem::create([
                                        'check_id' => $check->id,
                                        'sync_uuid' => $itemSyncUuid,
                                        'product_id' => $prodId,
                                        'product_name' => $iData['product_name'],
                                        'unit_price' => $iData['unit_price'],
                                        'quantity' => $iData['quantity'],
                                        'total_price' => $iData['total_price'],
                                        'kitchen_status' => $iData['status'] ?? $iData['kitchen_status'] ?? 'pending',
                                        'is_synced' => true,
                                    ]);
                                }
                                $syncedUuids[] = $itemSyncUuid;
                            }
                        }

                        (new \App\Services\Checks\CheckService())->recalculateTotals($check);
                        if ($check->dining_table_id) {
                            $tableStatus = ($status === 'open') ? 'occupied' : 'available';
                            DB::table('dining_tables')->where('id', $check->dining_table_id)->update(['status' => $tableStatus]);
                        }
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 4. Stock Movements Senkronizasyonu
                if (!empty($validated['stock_movements'])) {
                    foreach ($validated['stock_movements'] as $sData) {
                        $syncUuid = $sData['sync_uuid'];
                        $existingStock = StockMovement::where('sync_uuid', $syncUuid)->first();
                        if ($existingStock) {
                            $syncedUuids[] = $syncUuid;
                            continue;
                        }

                        $smProdId = $sData['product_id'] ?? null;
                        if (!empty($sData['product_sync_uuid'])) {
                            $p = Product::where('sync_uuid', $sData['product_sync_uuid'])->first();
                            if ($p) $smProdId = $p->id;
                        }
                        if (!$smProdId || !Product::where('id', $smProdId)->exists()) {
                            $smProdId = Product::first()?->id ?? 1;
                        }

                        StockMovement::create([
                            'branch_id' => $device ? $device->branch_id : 1,
                            'sync_uuid' => $syncUuid,
                            'product_id' => $smProdId,
                            'type' => $sData['type'],
                            'quantity' => $sData['quantity'],
                            'status' => 'approved',
                            'notes' => $sData['notes'] ?? null,
                            'is_synced' => true,
                        ]);

                        $product = Product::find($smProdId);
                        if ($product) {
                            $type = $sData['type'];
                            $qty = (float) $sData['quantity'];
                            if (in_array($type, ['sale_deduction', 'manual_subtraction'])) {
                                $product->decrement('stock_quantity', $qty);
                            } elseif (in_array($type, ['manual_addition', 'return_approved'])) {
                                $product->increment('stock_quantity', $qty);
                            }
                        }
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 5. Payments Senkronizasyonu
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

                        if ($check) {
                            $paidSoFar = Payment::where('check_id', $check->id)->sum('amount');
                            if ($paidSoFar >= $check->total) {
                                $check->update(['status' => 'closed', 'closed_at' => now()]);
                                if ($check->dining_table_id) {
                                    DB::table('dining_tables')->where('id', $check->dining_table_id)->update(['status' => 'available']);
                                }
                            }
                        }
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 6. Silinen Ürün ve Kategorilerin MySQL Sunucusunda Silinmesi.
                // ⚠️ record_id (cihazın YEREL id'si) sunucu id'siyle karıştırılmaz — yanlış kaydı silerdi.
                // Eşleşme yalnızca sync_uuid + name üzerinden yapılır.
                // synced_uuids'e YALNIZCA gerçekten silinen ya da zaten sunucuda bulunmayan (temiz) uuid eklenir;
                // böylece istemci silmeyi yanlışlıkla "onaylanmış" saymaz.
                if (!empty($validated['deleted_products'])) {
                    foreach ($validated['deleted_products'] as $delItem) {
                        $delUuid = is_array($delItem) ? ($delItem['sync_uuid'] ?? null) : $delItem;
                        $delName = is_array($delItem) ? ($delItem['name'] ?? null) : null;
                        if (empty($delUuid) && empty($delName)) continue;

                        $matchQuery = fn() => Product::where(function($q) use ($delUuid, $delName) {
                            if (!empty($delUuid)) $q->where('sync_uuid', $delUuid);
                            if (!empty($delName)) $q->orWhere('name', $delName);
                        });

                        $matchQuery()->delete();

                        // Silme sonrası eşleşen kayıt kalmadıysa sunucu bu ürün için temizdir.
                        if ($delUuid && $matchQuery()->doesntExist()) {
                            $syncedUuids[] = $delUuid;
                        }
                    }
                }
                if (!empty($validated['deleted_categories'])) {
                    foreach ($validated['deleted_categories'] as $delItem) {
                        $delUuid = is_array($delItem) ? ($delItem['sync_uuid'] ?? null) : $delItem;
                        $delName = is_array($delItem) ? ($delItem['name'] ?? null) : null;
                        if (empty($delUuid) && empty($delName)) continue;

                        $matchQuery = fn() => Category::where(function($q) use ($delUuid, $delName) {
                            if (!empty($delUuid)) $q->where('sync_uuid', $delUuid);
                            if (!empty($delName)) $q->orWhere('name', $delName);
                        });

                        $matchQuery()->delete();

                        if ($delUuid && $matchQuery()->doesntExist()) {
                            $syncedUuids[] = $delUuid;
                        }
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
        $device = $request->attributes->get('device') ?? $request->attributes->get('validated_device');
        $branchId = $device ? $device->branch_id : 1;

        try {
            if (\App\Models\Category::count() === 0 || \App\Models\Product::count() === 0) {
                try {
                    (new \Database\Seeders\DatabaseSeeder())->run();
                } catch (\Throwable $e) {}
            }

            $users = \App\Models\User::all();
            $halls = \App\Models\Hall::all();
            $tables = \App\Models\DiningTable::all();
            $categories = \App\Models\Category::all();
            $products = \App\Models\Product::all();
            $checks = \App\Models\Check::with('items')->get();
            $payments = \App\Models\Payment::all();
            $stockMovements = \Illuminate\Support\Facades\Schema::hasTable('stock_movements') ? \App\Models\StockMovement::all() : [];
            $deliveryOrders = \Illuminate\Support\Facades\Schema::hasTable('delivery_orders') ? \App\Models\DeliveryOrder::all() : [];
            $deliveryIntegrations = \Illuminate\Support\Facades\Schema::hasTable('delivery_integrations') ? \App\Models\DeliveryIntegration::all() : [];
            $staffProfiles = \App\Models\StaffProfile::all();
            $settings = \App\Models\Setting::all();

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
                    'payments' => $payments,
                    'stock_movements' => $stockMovements,
                    'delivery_orders' => $deliveryOrders,
                    'delivery_integrations' => $deliveryIntegrations,
                    'settings' => $settings,
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
