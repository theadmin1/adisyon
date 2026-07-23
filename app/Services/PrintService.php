<?php

namespace App\Services;

use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Support\Str;

class PrintService
{
    /**
     * Mutfak Fişi Yazdırma İşi Oluştur (KDS / Mutfak Yazıcısı)
     */
    public function createKitchenSlip(Check $check, array $items = []): PrintJob
    {
        $branchId = $check->branch_id ?? 1;
        $tableName = $check->diningTable->name ?? 'Hızlı Satış (Tezgah)';
        $waiterName = $check->waiter->name ?? 'Kasiyer';
        $itemsToPrint = empty($items) ? $check->items : $items;

        $itemList = [];
        foreach ($itemsToPrint as $item) {
            $name = is_object($item) ? $item->product->name : ($item['product_name'] ?? 'Ürün');
            $qty = is_object($item) ? $item->quantity : ($item['quantity'] ?? 1);
            $notes = is_object($item) ? ($item->notes ?? '') : ($item['notes'] ?? '');

            $itemList[] = [
                'name' => $name,
                'quantity' => (float) $qty,
                'notes' => $notes,
            ];
        }

        $payload = [
            'header' => [
                'title' => 'MUTFAK SİPARİŞ FİŞİ',
                'table' => $tableName,
                'check_number' => $check->check_number,
                'waiter' => $waiterName,
                'time' => now()->format('d.m.Y H:i:s'),
            ],
            'items' => $itemList,
            'raw_text' => $this->generateKitchenRawText($tableName, $check->check_number, $waiterName, $itemList),
        ];

        $kitchenPrinter = Printer::where('branch_id', $branchId)
            ->where('type', 'kitchen')
            ->where('is_active', true)
            ->first();

        return PrintJob::create([
            'branch_id' => $branchId,
            'printer_id' => $kitchenPrinter?->id,
            'check_id' => $check->id,
            'job_type' => 'kitchen_slip',
            'printer_type' => 'kitchen',
            'title' => "Mutfak Fişi: {$tableName} (#{$check->check_number})",
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    /**
     * Adisyon / Hesap İste Fişi Yazdırma İşi Oluştur (Kasa Yazıcısı)
     */
    public function createCheckSlip(Check $check): PrintJob
    {
        $branchId = $check->branch_id ?? 1;
        $tableName = $check->diningTable->name ?? 'Hızlı Satış';
        $waiterName = $check->waiter->name ?? 'Kasiyer';

        $check->loadMissing('items.product', 'payments');

        $itemList = [];
        foreach ($check->items as $item) {
            $itemList[] = [
                'name' => $item->product->name ?? 'Ürün',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) ($item->unit_price * $item->quantity),
            ];
        }

        $payload = [
            'header' => [
                'restaurant_name' => 'ADİSYON RESTORAN & CAFÉ',
                'branch_name' => 'Ana Şube',
                'title' => 'HESAP ADİSYON FİŞİ',
                'table' => $tableName,
                'check_number' => $check->check_number,
                'waiter' => $waiterName,
                'time' => now()->format('d.m.Y H:i:s'),
            ],
            'items' => $itemList,
            'summary' => [
                'subtotal' => (float) $check->subtotal,
                'discount' => (float) $check->discount_total,
                'total' => (float) $check->total,
                'paid' => (float) $check->paid_total,
                'remaining' => (float) max(0, $check->total - $check->paid_total),
            ],
            'raw_text' => $this->generateCheckRawText($tableName, $check->check_number, $waiterName, $itemList, $check),
        ];

        $cashierPrinter = Printer::where('branch_id', $branchId)
            ->where('type', 'cashier')
            ->where('is_active', true)
            ->first();

        return PrintJob::create([
            'branch_id' => $branchId,
            'printer_id' => $cashierPrinter?->id,
            'check_id' => $check->id,
            'job_type' => 'check_slip',
            'printer_type' => 'cashier',
            'title' => "Hesap Fişi: {$tableName} (#{$check->check_number})",
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    /**
     * Mutfak Fişi İçin Formatlı Metin Üretir
     */
    private function generateKitchenRawText(string $table, string $checkNum, string $waiter, array $items): string
    {
        $line = str_repeat('-', 32) . "\n";
        $text = "================================\n";
        $text .= "        MUTFAK SİPARİŞİ         \n";
        $text .= "================================\n";
        $text .= "MASA: " . mb_strtoupper($table) . "\n";
        $text .= "ADİSYON NO: #" . $checkNum . "\n";
        $text .= "GARSON: " . $waiter . "\n";
        $text .= "TARIH: " . date('d.m.Y H:i') . "\n";
        $text .= $line;
        $text .= sprintf("%-4s %-26s\n", "ADET", "ÜRÜN ADI");
        $text .= $line;

        foreach ($items as $item) {
            $text .= sprintf("%-4s %-26s\n", $item['quantity'], mb_substr($item['name'], 0, 26));
            if (!empty($item['notes'])) {
                $text .= "  * Not: " . $item['notes'] . "\n";
            }
        }

        $text .= $line;
        $text .= "       AFİYET OLSUN!            \n\n\n";

        return $text;
    }

    /**
     * Kasa Hesap Fişi İçin Formatlı Metin Üretir
     */
    private function generateCheckRawText(string $table, string $checkNum, string $waiter, array $items, Check $check): string
    {
        $line = str_repeat('-', 32) . "\n";
        $text = "    ADİSYON RESTORAN POS       \n";
        $text .= "        HESAP FİŞİ              \n";
        $text .= $line;
        $text .= "Masa: " . $table . "\n";
        $text .= "Adisyon No: #" . $checkNum . "\n";
        $text .= "Tarih: " . date('d.m.Y H:i') . "\n";
        $text .= "Garson: " . $waiter . "\n";
        $text .= $line;
        $text .= sprintf("%-18s %-4s %-8s\n", "ÜRÜN", "ADET", "TUTAR");
        $text .= $line;

        foreach ($items as $item) {
            $name = mb_substr($item['name'], 0, 17);
            $text .= sprintf("%-18s %-4s ₺%-7.2f\n", $name, $item['quantity'], $item['total']);
        }

        $text .= $line;
        $text .= sprintf("%-23s ₺%-7.2f\n", "Ara Toplam:", $check->subtotal);
        if ($check->discount_total > 0) {
            $text .= sprintf("%-23s ₺%-7.2f\n", "İndirim:", $check->discount_total);
        }
        $text .= sprintf("%-23s ₺%-7.2f\n", "ÖDENECEK TOPLAM:", $check->total);

        if ($check->paid_total > 0) {
            $text .= sprintf("%-23s ₺%-7.2f\n", "Ödenen Tutar:", $check->paid_total);
            $text .= sprintf("%-23s ₺%-7.2f\n", "Kalan Bakiye:", max(0, $check->total - $check->paid_total));
        }

        $text .= $line;
        $text .= "    BİZİ TERCİH ETTİĞİNİZ İÇİN \n";
        $text .= "        TEŞEKKÜR EDERİZ!        \n\n\n";

        return $text;
    }
}
