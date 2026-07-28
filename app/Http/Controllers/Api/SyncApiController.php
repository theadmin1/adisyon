<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DeliveryIntegration;
use App\Models\DeliveryOrder;
use App\Models\DeviceLog;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\OfflineSyncLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BidirectionalSyncService;
use App\Services\Checks\CheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncApiController extends Controller
{
    /**
     * Cihazdan gelen çevrimdışı (offline) adisyon, fiş, ödeme ve stok hareketlerini senkronize eder.
     */
    public function pushOfflineData(
        Request $request,
        BidirectionalSyncService $bidirectionalSync
    ): JsonResponse {
        $device = $request->attributes->get('device') ?? $request->attributes->get('validated_device');

        // ⚠️ DİKKAT: Laravel validate() YALNIZCA kurallarda tanımlı alanları $validated'a koyar.
        // Burada tanımlanmayan bir alan (örn. is_cancelled) istemciden gelse bile sessizce DÜŞER.
        // Bu yüzden aşağıdaki işleme kodunun okuduğu HER alanın burada bir kuralı olmak zorundadır.
        // (Geçmişte is_cancelled ve price eksikti: iptal edilen kalemler silinmek yerine ekleniyor,
        // offline eklenen ürünler sunucuya fiyatsız (0) gidiyordu.)
        $validated = $request->validate([
            'batch_id' => 'required|string',
            'checks' => 'nullable|array',
            'checks.*.sync_uuid' => 'required|string',
            'checks.*.dining_table_id' => 'nullable|integer',
            'checks.*.dining_table_sync_uuid' => 'nullable|uuid',
            'checks.*.user_id' => 'nullable|integer',
            'checks.*.waiter_id' => 'nullable|integer',
            'checks.*.staff_profile_id' => 'nullable|integer',
            'checks.*.waiter_staff_profile_sync_uuid' => 'nullable|uuid',
            'checks.*.waiter_name' => 'nullable|string|max:255',
            'checks.*.customer_notes' => 'nullable|string|max:2000',
            'checks.*.check_number' => 'nullable|string',
            'checks.*.guest_count' => 'nullable|integer|min:1',
            'checks.*.subtotal' => 'nullable|numeric',
            'checks.*.total' => 'nullable|numeric',
            'checks.*.total_amount' => 'required|numeric',
            'checks.*.discount_amount' => 'nullable|numeric',
            'checks.*.discount_total' => 'nullable|numeric',
            'checks.*.status' => 'required|string',
            'checks.*.created_at' => 'nullable|string',
            'checks.*.items_complete' => 'nullable|boolean',
            'checks.*.items' => 'nullable|array',
            'checks.*.items.*.sync_uuid' => 'required|string',
            'checks.*.items.*.product_id' => 'nullable|integer',
            'checks.*.items.*.product_sync_uuid' => 'nullable|string',
            'checks.*.items.*.added_by_staff_profile_sync_uuid' => 'nullable|uuid',
            'checks.*.items.*.added_by_name' => 'nullable|string|max:255',
            'checks.*.items.*.product_name' => 'required|string',
            'checks.*.items.*.unit_price' => 'required|numeric',
            'checks.*.items.*.quantity' => 'required|numeric',
            'checks.*.items.*.total_price' => 'required|numeric',
            'checks.*.items.*.notes' => 'nullable|string',
            'checks.*.items.*.is_complimentary' => 'nullable|boolean',
            'checks.*.items.*.complimentary_reason' => 'nullable|string|max:500',
            'checks.*.items.*.status' => 'nullable|string',
            'checks.*.items.*.kitchen_status' => 'nullable|string',
            'checks.*.items.*.is_cancelled' => 'nullable|boolean',
            'check_items' => 'nullable|array',
            'check_items.*.sync_uuid' => 'required|string',
            'check_items.*.check_sync_uuid' => 'nullable|string',
            'check_items.*.dining_table_id' => 'nullable|integer',
            'check_items.*.dining_table_sync_uuid' => 'nullable|uuid',
            'check_items.*.product_id' => 'nullable|integer',
            'check_items.*.product_sync_uuid' => 'nullable|string',
            'check_items.*.added_by_staff_profile_sync_uuid' => 'nullable|uuid',
            'check_items.*.added_by_name' => 'nullable|string|max:255',
            'check_items.*.product_name' => 'required|string',
            'check_items.*.unit_price' => 'required|numeric',
            'check_items.*.quantity' => 'required|numeric',
            'check_items.*.total_price' => 'required|numeric',
            'check_items.*.notes' => 'nullable|string',
            'check_items.*.is_complimentary' => 'nullable|boolean',
            'check_items.*.complimentary_reason' => 'nullable|string|max:500',
            'check_items.*.status' => 'nullable|string',
            'check_items.*.is_cancelled' => 'nullable|boolean',
            'payments' => 'nullable|array',
            'payments.*.sync_uuid' => 'required|string',
            'payments.*.check_sync_uuid' => 'nullable|string',
            'payments.*.amount' => 'required|numeric',
            'payments.*.payment_method' => 'required|string',
            'payments.*.created_at' => 'nullable|string',
            'stock_movements' => 'nullable|array',
            'stock_movements.*.sync_uuid' => 'required|string',
            'stock_movements.*.product_id' => 'required|integer',
            'stock_movements.*.product_sync_uuid' => 'nullable|string',
            'stock_movements.*.type' => 'required|string',
            'stock_movements.*.quantity' => 'required|numeric',
            'stock_movements.*.status' => 'nullable|string|in:completed,pending_approval,approved,rejected',
            'stock_movements.*.notes' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*.sync_uuid' => 'required|string',
            'categories.*.name' => 'required|string',
            'categories.*.slug' => 'nullable|string',
            'categories.*.sort_order' => 'nullable|integer',
            'categories.*.is_active' => 'nullable|boolean',
            'products' => 'nullable|array',
            'products.*.sync_uuid' => 'required|string',
            'products.*.name' => 'required|string',
            'products.*.category_id' => 'nullable|integer',
            'products.*.category_sync_uuid' => 'nullable|string',
            'products.*.branch_id' => 'nullable|integer',
            'products.*.slug' => 'nullable|string',
            'products.*.sku' => 'nullable|string',
            'products.*.price' => 'nullable|numeric',
            'products.*.discounted_price' => 'nullable|numeric',
            'products.*.stock_quantity' => 'nullable|numeric',
            'products.*.min_stock_level' => 'nullable|numeric',
            'products.*.unit' => 'nullable|string',
            'products.*.track_stock' => 'nullable|boolean',
            'products.*.description' => 'nullable|string',
            'products.*.kitchen_department' => 'nullable|string',
            'products.*.is_active' => 'nullable|boolean',
            'deleted_products' => 'nullable|array',
            'deleted_categories' => 'nullable|array',
            'sync_resources' => 'nullable|array',
            'deleted_resources' => 'nullable|array',
            'deleted_resources.*.resource' => 'required|string|max:100',
            'deleted_resources.*.sync_uuid' => 'required|uuid',
        ]);

        $branchId = (int) $device->branch_id;
        $syncedUuids = [];

        try {
            DB::transaction(function () use (
                $validated,
                $branchId,
                &$syncedUuids,
                $bidirectionalSync
            ) {
                // Halls/tables/staff must exist before a newly-created offline
                // check can resolve its UUID relationships on the server.
                $syncedUuids = array_values(array_unique(array_merge(
                    $syncedUuids,
                    $bidirectionalSync->applyPush(
                        $branchId,
                        $validated['sync_resources'] ?? [],
                        []
                    )
                )));

                $resolveDiningTableId = function (array $checkData) use ($branchId): ?int {
                    if (! empty($checkData['dining_table_sync_uuid'])) {
                        return DiningTable::forBranch($branchId)
                            ->where('sync_uuid', $checkData['dining_table_sync_uuid'])
                            ->value('id');
                    }

                    $tableId = $checkData['dining_table_id'] ?? null;

                    return $tableId && DiningTable::forBranch($branchId)->whereKey($tableId)->exists()
                        ? (int) $tableId
                        : null;
                };

                // 1. Categories Senkronizasyonu (İLK SIRADA)
                if (! empty($validated['categories'])) {
                    foreach ($validated['categories'] as $catData) {
                        $syncUuid = $catData['sync_uuid'];
                        Category::updateOrCreate(
                            ['sync_uuid' => $syncUuid, 'branch_id' => $branchId],
                            [
                                'branch_id' => $branchId,
                                'name' => $catData['name'],
                                'slug' => $catData['slug'] ?? Str::slug($catData['name']),
                                'sort_order' => $catData['sort_order'] ?? 0,
                                'is_active' => $catData['is_active'] ?? true,
                                'is_synced' => true,
                            ]
                        );
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 2. Products Senkronizasyonu (İKİNCİ SIRADA - FK için önce ürünler yüklenmeli)
                if (! empty($validated['products'])) {
                    foreach ($validated['products'] as $pData) {
                        $syncUuid = $pData['sync_uuid'];
                        $pName = $pData['name'] ?? null;

                        $existingProd = Product::forBranch($branchId)->where('sync_uuid', $syncUuid)->first();
                        if (! $existingProd && ! empty($pName)) {
                            $existingProd = Product::forBranch($branchId)->where('name', $pName)->first();
                        }
                        if (! $existingProd && ! empty($pData['id'])) {
                            $existingProd = Product::forBranch($branchId)->find($pData['id']);
                        }

                        $matchCriteria = $existingProd ? ['id' => $existingProd->id] : ['sync_uuid' => $syncUuid];

                        $catId = $pData['category_id'] ?? null;
                        if (! empty($pData['category_sync_uuid'])) {
                            $cat = Category::forBranch($branchId)->where('sync_uuid', $pData['category_sync_uuid'])->first();
                            if ($cat) {
                                $catId = $cat->id;
                            }
                        }
                        if (! $catId || ! Category::forBranch($branchId)->where('id', $catId)->exists()) {
                            $catId = Category::forBranch($branchId)->first()?->id;
                        }
                        if (! $catId) {
                            throw new \RuntimeException('Cihaz şubesi için geçerli kategori bulunamadı.');
                        }

                        $productValues = [
                            'sync_uuid' => $syncUuid,
                            'category_id' => $catId,
                            'branch_id' => $branchId,
                            'name' => $pData['name'],
                            'slug' => $pData['slug'] ?? Str::slug($pData['name']),
                            'sku' => $pData['sku'] ?? null,
                            'price' => $pData['price'] ?? 0,
                            'discounted_price' => $pData['discounted_price'] ?? null,
                            'min_stock_level' => $pData['min_stock_level'] ?? 0,
                            'unit' => $pData['unit'] ?? 'adet',
                            'track_stock' => $pData['track_stock'] ?? false,
                            'description' => $pData['description'] ?? null,
                            'kitchen_department' => $pData['kitchen_department'] ?? null,
                            'is_active' => $pData['is_active'] ?? true,
                            'is_synced' => true,
                        ];
                        if (! $existingProd) {
                            $productValues['stock_quantity'] = $pData['stock_quantity'] ?? 0;
                        }

                        Product::updateOrCreate($matchCriteria, $productValues);
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 3. Checks & Items Senkronizasyonu
                if (! empty($validated['checks'])) {
                    foreach ($validated['checks'] as $cData) {
                        $syncUuid = $cData['sync_uuid'];

                        $existingCheck = null;
                        if (! empty($syncUuid)) {
                            $existingCheck = Check::forBranch($branchId)->where('sync_uuid', $syncUuid)->first();
                        }
                        if (! $existingCheck && ! empty($cData['check_number'])) {
                            $existingCheck = Check::forBranch($branchId)->where('check_number', $cData['check_number'])->first();
                        }

                        $totalAmount = $cData['total_amount'] ?? $cData['total'] ?? 0;
                        $discountAmount = $cData['discount_amount'] ?? $cData['discount_total'] ?? 0;
                        $subtotal = $totalAmount + $discountAmount;
                        $status = $cData['status'] ?? 'open';

                        $diningTableId = $resolveDiningTableId($cData);
                        $waiterStaffProfileId = ! empty($cData['waiter_staff_profile_sync_uuid'])
                            ? StaffProfile::forBranch($branchId)
                                ->where('sync_uuid', $cData['waiter_staff_profile_sync_uuid'])
                                ->value('id')
                            : null;

                        if ($existingCheck) {
                            $existingCheck->update([
                                'dining_table_id' => $diningTableId ?? $existingCheck->dining_table_id,
                                'waiter_staff_profile_id' => $waiterStaffProfileId ?? $existingCheck->waiter_staff_profile_id,
                                'waiter_name' => $cData['waiter_name'] ?? $existingCheck->waiter_name,
                                'customer_notes' => $cData['customer_notes'] ?? $existingCheck->customer_notes,
                                'guest_count' => $cData['guest_count'] ?? $existingCheck->guest_count,
                                'subtotal' => $subtotal,
                                'discount_total' => $discountAmount,
                                'total' => $totalAmount,
                                'status' => $status,
                                'synced_at' => now(),
                            ]);
                            $check = $existingCheck;
                        } else {
                            $waiterId = $cData['waiter_id'] ?? $cData['user_id'] ?? $cData['staff_profile_id'] ?? null;
                            // ✅ FK güvenliği: waiter_id MySQL users tablosunda yoksa null yap
                            if ($waiterId && ! User::where('id', $waiterId)->where('branch_id', $branchId)->exists()) {
                                $waiterId = null;
                            }
                            // ✅ FK güvenliği: dining_table_id MySQL dining_tables tablosunda yoksa null yap
                            $checkNumber = $cData['check_number'] ?? ('CHK-'.strtoupper(substr(md5($syncUuid), 0, 8)));

                            $check = Check::create([
                                'branch_id' => $branchId,
                                'dining_table_id' => $diningTableId,
                                'waiter_id' => $waiterId,
                                'waiter_staff_profile_id' => $waiterStaffProfileId,
                                'waiter_name' => $cData['waiter_name'] ?? null,
                                'customer_notes' => $cData['customer_notes'] ?? null,
                                'check_number' => $checkNumber,
                                'sync_uuid' => $syncUuid,
                                'is_synced' => true,
                                'status' => $status,
                                'guest_count' => $cData['guest_count'] ?? 1,
                                'subtotal' => $subtotal,
                                'discount_total' => $discountAmount,
                                'total' => $totalAmount,
                                'opened_at' => $cData['created_at'] ?? now(),
                                'created_at' => $cData['created_at'] ?? now(),
                            ]);
                        }

                        if (! empty($cData['items'])) {
                            foreach ($cData['items'] as $iData) {
                                $itemSyncUuid = $iData['sync_uuid'];
                                $existingItem = CheckItem::forBranch($branchId)->where('sync_uuid', $itemSyncUuid)->first();

                                if (! empty($iData['is_cancelled'])) {
                                    if ($existingItem) {
                                        $existingItem->delete();
                                    }
                                    $syncedUuids[] = $itemSyncUuid;

                                    continue;
                                }

                                $prodId = $iData['product_id'] ?? null;
                                if (! empty($iData['product_sync_uuid'])) {
                                    $p = Product::forBranch($branchId)->where('sync_uuid', $iData['product_sync_uuid'])->first();
                                    if ($p) {
                                        $prodId = $p->id;
                                    }
                                }
                                if (! $prodId || ! Product::forBranch($branchId)->where('id', $prodId)->exists()) {
                                    $prodId = Product::forBranch($branchId)->first()?->id;
                                }
                                if (! $prodId) {
                                    throw new \RuntimeException('Cihaz şubesi için geçerli ürün bulunamadı.');
                                }

                                $addedByStaffProfileId = ! empty($iData['added_by_staff_profile_sync_uuid'])
                                    ? StaffProfile::forBranch($branchId)
                                        ->where('sync_uuid', $iData['added_by_staff_profile_sync_uuid'])
                                        ->value('id')
                                    : null;

                                if ($existingItem) {
                                    $existingItem->update([
                                        'added_by_staff_profile_id' => $addedByStaffProfileId ?? $existingItem->added_by_staff_profile_id,
                                        'added_by_name' => $iData['added_by_name'] ?? $existingItem->added_by_name,
                                        'notes' => $iData['notes'] ?? $existingItem->notes,
                                        'is_complimentary' => $iData['is_complimentary'] ?? $existingItem->is_complimentary,
                                        'complimentary_reason' => $iData['complimentary_reason'] ?? $existingItem->complimentary_reason,
                                        'quantity' => $iData['quantity'],
                                        'total_price' => $iData['total_price'],
                                        'kitchen_status' => $iData['status'] ?? $iData['kitchen_status'] ?? $existingItem->kitchen_status,
                                        'is_synced' => true,
                                    ]);
                                } else {
                                    CheckItem::create([
                                        'branch_id' => $branchId,
                                        'check_id' => $check->id,
                                        'sync_uuid' => $itemSyncUuid,
                                        'product_id' => $prodId,
                                        'added_by_staff_profile_id' => $addedByStaffProfileId,
                                        'added_by_name' => $iData['added_by_name'] ?? null,
                                        'product_name' => $iData['product_name'],
                                        'unit_price' => $iData['unit_price'],
                                        'quantity' => $iData['quantity'],
                                        'total_price' => $iData['total_price'],
                                        'notes' => $iData['notes'] ?? null,
                                        'is_complimentary' => $iData['is_complimentary'] ?? false,
                                        'complimentary_reason' => $iData['complimentary_reason'] ?? null,
                                        'kitchen_status' => $iData['status'] ?? $iData['kitchen_status'] ?? 'pending',
                                        'is_synced' => true,
                                    ]);
                                }
                                $syncedUuids[] = $itemSyncUuid;
                            }
                        }

                        // İstemci "items eksiksiz" işareti gönderdiyse: payload'da olmayan sunucu
                        // kalemlerini temizle. Bu, sync protokolü dışında kalan uuid'siz eski
                        // kalıntıları ve izi kaybolmuş silme artıklarını süpürür (hortlama önlenir).
                        // İşareti göndermeyen istemciler (ör. C# cihaz servisi) etkilenmez.
                        if (! empty($cData['items_complete'])) {
                            $keepUuids = collect($cData['items'] ?? [])->pluck('sync_uuid')->filter()->values()->all();
                            CheckItem::where('check_id', $check->id)
                                ->where(function ($q) use ($keepUuids) {
                                    $q->whereNull('sync_uuid');
                                    if (! empty($keepUuids)) {
                                        $q->orWhereNotIn('sync_uuid', $keepUuids);
                                    } else {
                                        $q->orWhereNotNull('sync_uuid');
                                    }
                                })->delete();
                        }

                        (new CheckService)->recalculateTotals($check);
                        if ($check->dining_table_id) {
                            $activeStatuses = Check::forBranch($branchId)
                                ->where('dining_table_id', $check->dining_table_id)
                                ->whereIn('status', ['open', 'awaiting_payment'])
                                ->pluck('status')
                                ->map(fn ($status) => $status instanceof \BackedEnum ? $status->value : $status);
                            $tableStatus = $activeStatuses->contains('awaiting_payment')
                                ? 'awaiting_payment'
                                : ($activeStatuses->contains('open') ? 'occupied' : 'available');
                            DB::table('dining_tables')
                                ->where('id', $check->dining_table_id)
                                ->update(['status' => $tableStatus]);
                        }
                        $syncedUuids[] = $syncUuid;
                    }
                }

                // 4. Stock Movements Senkronizasyonu
                if (! empty($validated['stock_movements'])) {
                    foreach ($validated['stock_movements'] as $sData) {
                        $syncUuid = $sData['sync_uuid'];
                        $existingStock = StockMovement::forBranch($branchId)->where('sync_uuid', $syncUuid)->first();
                        if ($existingStock) {
                            $syncedUuids[] = $syncUuid;
                            if (! empty($sData['product_sync_uuid'])) {
                                $syncedUuids[] = $sData['product_sync_uuid'];
                            }

                            continue;
                        }

                        $smProdId = null;
                        $pSyncUuid = $sData['product_sync_uuid'] ?? null;
                        if (! empty($pSyncUuid)) {
                            $p = Product::forBranch($branchId)->where('sync_uuid', $pSyncUuid)->first();
                            if ($p) {
                                $smProdId = $p->id;
                            }
                        }
                        if (! $smProdId && ! empty($sData['product_id'])) {
                            $p = Product::forBranch($branchId)->where('id', $sData['product_id'])->first();
                            if ($p) {
                                $smProdId = $p->id;
                                if (! empty($pSyncUuid) && empty($p->sync_uuid)) {
                                    $p->update(['sync_uuid' => $pSyncUuid]);
                                }
                            }
                        }
                        if (! $smProdId) {
                            $smProdId = Product::forBranch($branchId)->first()?->id;
                        }
                        if (! $smProdId) {
                            throw new \RuntimeException('Stok hareketi için şubeye ait ürün bulunamadı.');
                        }

                        $movementStatus = $sData['status'] ?? 'completed';
                        StockMovement::create([
                            'branch_id' => $branchId,
                            'sync_uuid' => $syncUuid,
                            'product_id' => $smProdId,
                            'type' => $sData['type'],
                            'quantity' => $sData['quantity'],
                            'status' => $movementStatus,
                            'notes' => $sData['notes'] ?? null,
                            'is_synced' => true,
                        ]);

                        $product = Product::forBranch($branchId)->whereKey($smProdId)->lockForUpdate()->first();
                        if ($product && in_array($movementStatus, ['completed', 'approved'], true)) {
                            $type = $sData['type'];
                            $qty = (float) $sData['quantity'];
                            if (in_array($type, ['sale_deduction', 'manual_subtraction'], true)) {
                                $product->decrement('stock_quantity', $qty);
                            } elseif (in_array($type, ['manual_addition', 'return_approved'], true)) {
                                $product->increment('stock_quantity', $qty);
                            }
                        }
                        $syncedUuids[] = $syncUuid;
                        if (! empty($pSyncUuid)) {
                            $syncedUuids[] = $pSyncUuid;
                        }
                    }
                }

                // 5. Payments Senkronizasyonu
                if (! empty($validated['payments'])) {
                    foreach ($validated['payments'] as $pData) {
                        $syncUuid = $pData['sync_uuid'];
                        $check = null;
                        $existingPayment = Payment::forBranch($branchId)->where('sync_uuid', $syncUuid)->first();
                        if ($existingPayment) {
                            $existingPayment->update([
                                'amount' => $pData['amount'],
                                'payment_method' => $pData['payment_method'],
                                'is_synced' => true,
                            ]);
                            $syncedUuids[] = $syncUuid;

                            continue;
                        }

                        $checkId = null;
                        if (! empty($pData['check_sync_uuid'])) {
                            $check = Check::forBranch($branchId)->where('sync_uuid', $pData['check_sync_uuid'])->first();
                            $checkId = $check?->id;
                        }

                        if (! $checkId) {
                            throw new \RuntimeException('Ödeme için şubeye ait adisyon bulunamadı.');
                        }

                        Payment::create([
                            'branch_id' => $branchId,
                            'check_id' => $checkId,
                            'sync_uuid' => $syncUuid,
                            'amount' => $pData['amount'],
                            'payment_method' => $pData['payment_method'],
                            'is_synced' => true,
                            'created_at' => $pData['created_at'] ?? now(),
                        ]);

                        if ($check) {
                            $paidSoFar = Payment::forBranch($branchId)->where('check_id', $check->id)->sum('amount');
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
                if (! empty($validated['deleted_products'])) {
                    foreach ($validated['deleted_products'] as $delItem) {
                        $delUuid = is_array($delItem) ? ($delItem['sync_uuid'] ?? null) : $delItem;
                        $delName = is_array($delItem) ? ($delItem['name'] ?? null) : null;
                        if (empty($delUuid) && empty($delName)) {
                            continue;
                        }

                        $matchQuery = fn () => Product::forBranch($branchId)->where(function ($q) use ($delUuid, $delName) {
                            if (! empty($delUuid)) {
                                $q->where('sync_uuid', $delUuid);
                            }
                            if (! empty($delName)) {
                                $q->orWhere('name', $delName);
                            }
                        });

                        $matchQuery()->delete();

                        // Silme sonrası eşleşen kayıt kalmadıysa sunucu bu ürün için temizdir.
                        if ($delUuid && $matchQuery()->doesntExist()) {
                            $syncedUuids[] = $delUuid;
                        }
                    }
                }
                if (! empty($validated['deleted_categories'])) {
                    foreach ($validated['deleted_categories'] as $delItem) {
                        $delUuid = is_array($delItem) ? ($delItem['sync_uuid'] ?? null) : $delItem;
                        $delName = is_array($delItem) ? ($delItem['name'] ?? null) : null;
                        if (empty($delUuid) && empty($delName)) {
                            continue;
                        }

                        $matchQuery = fn () => Category::forBranch($branchId)->where(function ($q) use ($delUuid, $delName) {
                            if (! empty($delUuid)) {
                                $q->where('sync_uuid', $delUuid);
                            }
                            if (! empty($delName)) {
                                $q->orWhere('name', $delName);
                            }
                        });

                        $matchQuery()->delete();

                        if ($delUuid && $matchQuery()->doesntExist()) {
                            $syncedUuids[] = $delUuid;
                        }
                    }
                }

                $syncedUuids = array_values(array_unique(array_merge(
                    $syncedUuids,
                    $bidirectionalSync->applyPush(
                        $branchId,
                        [],
                        $validated['deleted_resources'] ?? []
                    )
                )));
            });

            if ($device) {
                DeviceLog::create([
                    'device_id' => $device->id,
                    'log_type' => 'INFO',
                    'message' => 'Çevrimdışı veri senkronizasyonu tamamlandı. Toplam '.count($syncedUuids).' öge aktarıldı.',
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
            Log::error('Çevrimdışı Veri Senkronizasyon Hatası: '.$ex->getMessage(), [
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
                'message' => 'Senkronizasyon sırasında sunucu hatası oluştu.',
            ], 500);
        }
    }

    /**
     * Uzak MySQL sunucusundaki usta verileri (Users, Products, Categories, Halls, Tables, Active Checks) cihazın yerel veritabanı için dışa aktarır.
     */
    public function pullSyncData(
        Request $request,
        BidirectionalSyncService $bidirectionalSync
    ): JsonResponse {
        $device = $request->attributes->get('device');
        $branchId = (int) $device->branch_id;

        try {
            foreach (Product::forBranch($branchId)
                ->where(fn ($query) => $query->whereNull('sync_uuid')->orWhere('sync_uuid', ''))
                ->get() as $p) {
                $p->update(['sync_uuid' => (string) Str::uuid()]);
            }
            foreach (Category::forBranch($branchId)
                ->where(fn ($query) => $query->whereNull('sync_uuid')->orWhere('sync_uuid', ''))
                ->get() as $c) {
                $c->update(['sync_uuid' => (string) Str::uuid()]);
            }

            $users = User::where('branch_id', $branchId)
                ->where('is_admin', false)
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'branch_id' => $branchId,
                    'name' => $user->name,
                    'email' => $user->email,
                    'restaurant_id' => $user->restaurant_id,
                    'password_hash' => $user->getRawOriginal('password'),
                    'is_admin' => false,
                ]);
            $halls = Hall::forBranch($branchId)->get();
            $tables = DiningTable::forBranch($branchId)->get();
            $categories = Category::forBranch($branchId)->get();
            $products = Product::forBranch($branchId)->get();
            $checks = Check::forBranch($branchId)->with('items.product')->get();
            $payments = Payment::forBranch($branchId)->get();
            $stockMovements = Schema::hasTable('stock_movements')
                ? StockMovement::forBranch($branchId)->get()
                : [];
            $deliveryOrders = Schema::hasTable('delivery_orders')
                ? DeliveryOrder::forBranch($branchId)->get()
                : [];
            $deliveryIntegrations = Schema::hasTable('delivery_integrations')
                ? DeliveryIntegration::forBranch($branchId)
                    ->get(['id', 'branch_id', 'channel', 'store_name', 'store_id', 'is_active', 'auto_accept', 'created_at', 'updated_at'])
                : [];
            $staffProfiles = StaffProfile::forBranch($branchId)
                ->get()
                ->map(fn ($profile) => [
                    'id' => $profile->id,
                    'branch_id' => $profile->branch_id,
                    'name' => $profile->name,
                    'role' => $profile->role,
                    'pin_hash' => $profile->getRawOriginal('pin_hash'),
                    'pin_length' => $profile->getRawOriginal('pin_length') ?? 4,
                    'avatar_color' => $profile->avatar_color,
                    'is_active' => $profile->is_active,
                ]);
            $settings = Setting::forBranch($branchId)
                ->whereNotIn('key', ['DeviceApiKey', 'RestaurantLoginPassword'])
                ->get();
            $bidirectionalPayload = $bidirectionalSync->buildPullPayload($branchId);

            return response()->json([
                'success' => true,
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'branch' => $device->branch->only(['id', 'name', 'code', 'is_active']),
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
                    'sync_resources' => $bidirectionalPayload['resources'],
                    'sync_manifest' => $bidirectionalPayload['manifest'],
                ],
            ]);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Usta veri indirme sırasında sunucu hatası oluştu.',
            ], 500);
        }
    }
}
