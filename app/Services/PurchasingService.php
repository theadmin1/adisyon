<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchasingService
{
    /**
     * @param  array<int, array{product_id: int, quantity: mixed, unit_price: mixed, tax_rate?: mixed}>  $items
     */
    public function createOrder(
        User $user,
        Supplier $supplier,
        array $items,
        string $orderDate,
        ?string $expectedDeliveryDate,
        ?string $notes,
        ?int $branchId = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($user, $supplier, $items, $orderDate, $expectedDeliveryDate, $notes, $branchId): PurchaseOrder {
            $branchId ??= (int) $user->branch_id;
            if ((int) $supplier->branch_id !== $branchId || ! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => 'Geçerli ve aktif bir tedarikçi seçilmelidir.']);
            }

            $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $products = Product::forBranch($branchId)->whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count(array_unique($productIds))) {
                throw ValidationException::withMessages(['items' => 'Siparişte başka şubeye ait veya geçersiz ürün bulunuyor.']);
            }

            $order = PurchaseOrder::create([
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'created_by_user_id' => $user->id,
                'created_by_staff_profile_id' => $this->staffId(),
                'order_number' => 'SAT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'status' => 'draft',
                'created_by_name' => $this->actorName($user),
                'order_date' => $orderDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'notes' => $notes,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;
            foreach ($items as $item) {
                $product = $products->get((int) $item['product_id']);
                $quantity = round((float) $item['quantity'], 3);
                $unitPrice = round((float) $item['unit_price'], 4);
                $taxRate = round((float) ($item['tax_rate'] ?? 0), 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineTax = round($lineSubtotal * $taxRate / 100, 2);
                $lineTotal = round($lineSubtotal + $lineTax, 2);
                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;

                $order->items()->create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit ?: 'adet',
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_subtotal' => $lineSubtotal,
                    'line_tax' => $lineTax,
                    'line_total' => $lineTotal,
                ]);
            }

            $order->update([
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'total' => round($subtotal + $taxTotal, 2),
            ]);

            return $order->load(['supplier', 'items.product']);
        });
    }

    public function placeOrder(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'Yalnızca taslak sipariş onaylanabilir.']);
            }

            $locked->update(['status' => 'ordered', 'ordered_at' => now()]);

            return $locked->fresh();
        });
    }

    /**
     * @param  array<int|string, mixed>  $quantities
     */
    public function receive(
        PurchaseOrder $order,
        User $user,
        array $quantities,
        ?string $invoiceNumber,
        ?string $invoiceDate,
        ?string $notes
    ): PurchaseReceipt {
        return DB::transaction(function () use ($order, $user, $quantities, $invoiceNumber, $invoiceDate, $notes): PurchaseReceipt {
            $lockedOrder = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedOrder->status, ['ordered', 'partial'], true)) {
                throw ValidationException::withMessages(['order' => 'Mal kabul için siparişin onaylanmış ve açık olması gerekir.']);
            }

            $orderItems = $lockedOrder->items()->lockForUpdate()->get()->keyBy('id');
            $accepted = [];
            foreach ($quantities as $itemId => $quantityInput) {
                $quantity = round((float) $quantityInput, 3);
                if ($quantity <= 0) {
                    continue;
                }

                $item = $orderItems->get((int) $itemId);
                if (! $item) {
                    throw ValidationException::withMessages(['quantities' => 'Siparişe ait olmayan bir teslimat satırı gönderildi.']);
                }

                $remaining = round((float) $item->quantity - (float) $item->received_quantity, 3);
                if ($quantity > $remaining) {
                    throw ValidationException::withMessages([
                        "quantities.{$itemId}" => "{$item->product_name} için teslim miktarı kalan {$remaining} {$item->unit} miktarını aşamaz.",
                    ]);
                }
                $accepted[] = [$item, $quantity];
            }

            if ($accepted === []) {
                throw ValidationException::withMessages(['quantities' => 'En az bir ürün için teslim alınan miktar girilmelidir.']);
            }

            $receipt = PurchaseReceipt::create([
                'purchase_order_id' => $lockedOrder->id,
                'branch_id' => $lockedOrder->branch_id,
                'received_by_user_id' => $user->id,
                'received_by_staff_profile_id' => $this->staffId(),
                'receipt_number' => 'MK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'supplier_invoice_number' => $invoiceNumber,
                'supplier_invoice_date' => $invoiceDate,
                'received_by_name' => $this->actorName($user),
                'notes' => $notes,
                'received_at' => now(),
            ]);

            $receivedValue = 0.0;
            foreach ($accepted as [$item, $quantity]) {
                $lineSubtotal = round($quantity * (float) $item->unit_price, 2);
                $lineTotal = round($lineSubtotal * (1 + (float) $item->tax_rate / 100), 2);
                $receivedValue += $lineTotal;
                $item->increment('received_quantity', $quantity);

                $receipt->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'branch_id' => $lockedOrder->branch_id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $lineTotal,
                ]);

                $product = Product::forBranch((int) $lockedOrder->branch_id)
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($product->track_stock) {
                    $product->increment('stock_quantity', $quantity);
                }

                StockMovement::create([
                    'branch_id' => $lockedOrder->branch_id,
                    'product_id' => $product->id,
                    'purchase_receipt_id' => $receipt->id,
                    'sync_uuid' => (string) Str::uuid(),
                    'is_synced' => config('database.default') === 'mysql',
                    'type' => 'purchase_receipt',
                    'quantity' => $quantity,
                    'status' => 'completed',
                    'approved_by_user_id' => $user->id,
                    'approved_at' => now(),
                    'notes' => "Satın alma mal kabulü ({$lockedOrder->order_number} / {$receipt->receipt_number})",
                ]);
            }

            $receipt->update(['received_value' => round($receivedValue, 2)]);
            $lockedOrder->load('items');
            $complete = $lockedOrder->items->every(
                fn ($item): bool => (float) $item->received_quantity >= (float) $item->quantity
            );
            $lockedOrder->update([
                'status' => $complete ? 'received' : 'partial',
                'completed_at' => $complete ? now() : null,
            ]);

            return $receipt->load('items.product');
        });
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            $locked = PurchaseOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['draft', 'ordered'], true) || $locked->items()->where('received_quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages(['order' => 'Teslimat alınmış veya kapanmış sipariş iptal edilemez.']);
            }
            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return $locked->fresh();
        });
    }

    private function staffId(): ?int
    {
        $value = request()->session()->get('active_staff_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function actorName(User $user): string
    {
        return (string) (request()->session()->get('active_staff_name') ?: $user->name);
    }
}
