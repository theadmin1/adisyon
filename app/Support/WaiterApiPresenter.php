<?php

namespace App\Support;

use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DiningTable;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StaffProfile;

class WaiterApiPresenter
{
    public static function staff(StaffProfile $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'role' => $staff->role,
            'avatar_color' => $staff->avatar_color,
        ];
    }

    public static function table(DiningTable $table): array
    {
        $activeOrder = $table->relationLoaded('checks') ? $table->checks->first() : null;

        return [
            'id' => $table->id,
            'hall_id' => $table->hall_id,
            'name' => $table->name,
            'code' => $table->code,
            'capacity' => (int) $table->capacity,
            'occupant_count' => (int) $table->occupant_count,
            'status' => self::enumValue($table->status),
            'is_active' => (bool) $table->is_active,
            'active_order' => $activeOrder ? self::orderSummary($activeOrder) : null,
        ];
    }

    public static function product(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'image' => $product->image_url,
            'price' => (float) $product->price,
            'discounted_price' => $product->discounted_price !== null ? (float) $product->discounted_price : null,
            'effective_price' => $product->effective_price,
            'unit' => $product->unit,
            'kitchen_department' => $product->kitchen_department,
            'send_to_kitchen' => (bool) $product->send_to_kitchen,
            'track_stock' => (bool) $product->track_stock,
            'stock_quantity' => (float) $product->stock_quantity,
            'is_available' => (bool) $product->is_active
                && (! $product->track_stock || (float) $product->stock_quantity > 0),
        ];
    }

    public static function orderSummary(Check $check): array
    {
        $paid = $check->relationLoaded('payments')
            ? (float) $check->payments->sum('amount')
            : (float) ($check->paid_total ?? 0);

        return [
            'id' => $check->id,
            'check_number' => $check->check_number,
            'client_reference' => $check->client_reference,
            'status' => self::enumValue($check->status),
            'table_id' => $check->dining_table_id,
            'table_name' => $check->diningTable?->name,
            'hall_name' => $check->diningTable?->hall?->name,
            'waiter_id' => $check->waiter_staff_profile_id,
            'waiter_name' => $check->waiter_name,
            'guest_count' => (int) $check->guest_count,
            'total' => (float) $check->total,
            'paid' => $paid,
            'remaining' => max(0, round((float) $check->total - $paid, 2)),
            'opened_at' => $check->opened_at?->toIso8601String(),
            'kitchen_sent_at' => $check->kitchen_sent_at?->toIso8601String(),
        ];
    }

    public static function order(Check $check): array
    {
        $summary = self::orderSummary($check);
        $payments = $check->relationLoaded('payments')
            ? $check->payments->map(fn (Payment $payment) => self::payment($payment))->values()->all()
            : [];
        $items = $check->relationLoaded('items')
            ? $check->items->map(fn (CheckItem $item) => self::item($item))->values()->all()
            : [];

        return [
            ...$summary,
            'customer_notes' => $check->customer_notes,
            'subtotal' => (float) $check->subtotal,
            'discount_total' => (float) $check->discount_total,
            'tax_total' => (float) $check->tax_total,
            'closed_at' => $check->closed_at?->toIso8601String(),
            'items' => $items,
            'payments' => $payments,
        ];
    }

    public static function item(CheckItem $item): array
    {
        return [
            'id' => $item->id,
            'order_id' => $item->check_id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total_price' => (float) $item->total_price,
            'notes' => $item->notes,
            'kitchen_status' => $item->kitchen_status,
            'sent_to_kitchen_at' => $item->sent_to_kitchen_at?->toIso8601String(),
            'is_cancelled' => (bool) $item->is_cancelled,
            'added_by' => $item->added_by_name,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    public static function payment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'method' => $payment->payment_method,
            'client_reference' => $payment->client_reference,
            'amount' => (float) $payment->amount,
            'created_at' => $payment->created_at?->toIso8601String(),
        ];
    }

    private static function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
