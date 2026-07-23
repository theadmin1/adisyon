<?php

namespace App\Services;

use App\Models\Check;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Setting;
use App\Services\Printing\ReceiptLayout as L;

class PrintService
{
    /**
     * Mutfak Fişi Yazdırma İşi Oluştur (KDS / Mutfak Yazıcısı)
     *
     * @param  iterable|null  $items  null verilirse adisyondaki tüm iptal edilmemiş kalemler basılır.
     *                                Dizi/koleksiyon verilirse yalnızca o kalemler basılır.
     */
    public function createKitchenSlip(Check $check, ?iterable $items = null): PrintJob
    {
        $branchId = $check->branch_id ?? $this->defaultBranchId();
        $printer = $this->resolvePrinter($branchId, 'kitchen');
        $width = $printer?->effectiveCharWidth() ?? Printer::charWidthForPaper(80);

        $check->loadMissing('diningTable', 'waiter');

        $itemList = $this->normalizeItems(
            $items ?? $check->items()->where('is_cancelled', false)->get()
        );

        $tableName = $check->diningTable?->name ?: 'Hızlı Satış (Tezgah)';
        $waiterName = $check->waiter?->name ?: 'Kasiyer';

        $payload = [
            'header' => [
                'title' => 'MUTFAK SİPARİŞ FİŞİ',
                'table' => $tableName,
                'check_number' => $check->check_number,
                'waiter' => $waiterName,
                'time' => now()->format('d.m.Y H:i:s'),
            ],
            'items' => $itemList,
            'char_width' => $width,
            'raw_text' => $this->renderKitchenSlip($tableName, $check->check_number, $waiterName, $itemList, $width),
        ];

        return PrintJob::create([
            'branch_id' => $branchId,
            'printer_id' => $printer?->id,
            'check_id' => $check->id,
            'job_type' => 'kitchen_slip',
            'printer_type' => 'kitchen',
            'title' => "Mutfak Fişi: {$tableName} (#{$check->check_number})",
            'payload' => $payload,
            'status' => PrintJob::STATUS_PENDING,
        ]);
    }

    /**
     * Adisyon / Hesap Fişi Yazdırma İşi Oluştur (Kasa Yazıcısı)
     */
    public function createCheckSlip(Check $check): PrintJob
    {
        $branchId = $check->branch_id ?? $this->defaultBranchId();
        $printer = $this->resolvePrinter($branchId, 'cashier');
        $width = $printer?->effectiveCharWidth() ?? Printer::charWidthForPaper(80);

        $check->loadMissing('items', 'payments', 'diningTable', 'waiter');

        $tableName = $check->diningTable?->name ?: 'Hızlı Satış';
        $waiterName = $check->waiter?->name ?: 'Kasiyer';

        // İptal edilen kalemler müşteri hesabında GÖRÜNMEZ; ikramlar 0,00 olarak listelenir.
        // (Aksi halde satır toplamı adisyonun ara toplamıyla tutmaz.)
        $itemList = $this->normalizeItems(
            $check->items->where('is_cancelled', false)
        );

        $summary = [
            'subtotal' => (float) $check->subtotal,
            'discount' => (float) $check->discount_total,
            'total' => (float) $check->total,
            'paid' => (float) $check->payments->sum('amount'),
        ];
        $summary['remaining'] = max(0, $summary['total'] - $summary['paid']);

        $payload = [
            'header' => [
                'restaurant_name' => $this->setting('restaurant_name', 'AltF4 Adisyon & Restoran'),
                'receipt_title' => $this->setting('receipt_title', 'ADİSYON RESTORAN & KAFE'),
                'title' => 'HESAP ADİSYON FİŞİ',
                'table' => $tableName,
                'check_number' => $check->check_number,
                'waiter' => $waiterName,
                'time' => now()->format('d.m.Y H:i:s'),
            ],
            'items' => $itemList,
            'summary' => $summary,
            'char_width' => $width,
            'raw_text' => $this->renderCheckSlip($tableName, $check, $waiterName, $itemList, $summary, $width),
        ];

        return PrintJob::create([
            'branch_id' => $branchId,
            'printer_id' => $printer?->id,
            'check_id' => $check->id,
            'job_type' => 'check_slip',
            'printer_type' => 'cashier',
            'title' => "Hesap Fişi: {$tableName} (#{$check->check_number})",
            'payload' => $payload,
            'status' => PrintJob::STATUS_PENDING,
        ]);
    }

    // Not: Test fişi buradan üretilmez. Yazıcı sınaması, cihazın kendi
    // yazıcısına doğrudan bastığı için servis programının "Termal Yazıcılar"
    // ekranında yapılır (sunucuya uğramadan, anında sonuç verir).

    // ------------------------------------------------------------------
    // Fiş metni üretimi
    // ------------------------------------------------------------------

    private function renderKitchenSlip(string $table, string $checkNumber, string $waiter, array $items, int $width): string
    {
        $text = L::rule($width, '=')
            . L::center('MUTFAK SİPARİŞİ', $width)
            . L::rule($width, '=')
            . L::keyValue('MASA:', mb_strtoupper($table, 'UTF-8'), $width)
            . L::keyValue('ADİSYON:', '#' . $checkNumber, $width)
            . L::keyValue('GARSON:', $waiter, $width)
            . L::keyValue('SAAT:', now()->format('d.m.Y H:i'), $width)
            . L::rule($width);

        foreach ($items as $item) {
            $prefix = L::quantity($item['quantity']) . 'x ';
            $lines = L::wrap($item['name'], $width - mb_strlen($prefix));

            $text .= $prefix . array_shift($lines) . "\n";
            foreach ($lines as $continuation) {
                $text .= str_repeat(' ', mb_strlen($prefix)) . $continuation . "\n";
            }

            if (!empty($item['notes'])) {
                foreach (L::wrap('> ' . $item['notes'], $width, '   ') as $noteLine) {
                    $text .= $noteLine . "\n";
                }
            }

            if (!empty($item['is_complimentary'])) {
                $text .= '   > İKRAM' . "\n";
            }
        }

        $text .= L::rule($width)
            . L::center('AFİYET OLSUN!', $width)
            . "\n\n";

        return $text;
    }

    private function renderCheckSlip(string $table, Check $check, string $waiter, array $items, array $summary, int $width): string
    {
        $currency = $this->currencyText();

        $text = L::center($this->setting('receipt_title', 'ADİSYON RESTORAN & KAFE'), $width);

        if ($phone = $this->setting('restaurant_phone', '')) {
            $text .= L::center($phone, $width);
        }

        $text .= L::rule($width, '=')
            . L::center('HESAP FİŞİ', $width)
            . L::rule($width, '=')
            . L::keyValue('Masa:', $table, $width)
            . L::keyValue('Adisyon No:', '#' . $check->check_number, $width)
            . L::keyValue('Garson:', $waiter, $width)
            . L::keyValue('Tarih:', now()->format('d.m.Y H:i'), $width)
            . L::rule($width);

        foreach ($items as $item) {
            // 1. satır: ürün adı (gerekirse sarmalanır)
            foreach (L::wrap($item['name'], $width) as $nameLine) {
                $text .= $nameLine . "\n";
            }

            // 2. satır: "  2 x 85,00" solda, satır toplamı sağda
            $detail = '  ' . L::quantity($item['quantity']) . ' x ' . L::money($item['unit_price']);

            if (!empty($item['is_complimentary'])) {
                $text .= L::keyValue($detail, 'İKRAM', $width);
            } else {
                $text .= L::keyValue($detail, L::money($item['total']), $width);
            }
        }

        $text .= L::rule($width)
            . L::keyValue('Ara Toplam', L::money($summary['subtotal']) . ' ' . $currency, $width);

        if ($summary['discount'] > 0) {
            $text .= L::keyValue('İndirim', '-' . L::money($summary['discount']) . ' ' . $currency, $width);
        }

        $text .= L::rule($width)
            . L::keyValue('TOPLAM', L::money($summary['total']) . ' ' . $currency, $width);

        if ($summary['paid'] > 0) {
            $text .= L::keyValue('Ödenen', L::money($summary['paid']) . ' ' . $currency, $width);

            if ($summary['remaining'] > 0) {
                $text .= L::keyValue('KALAN', L::money($summary['remaining']) . ' ' . $currency, $width);
            }
        }

        $text .= L::rule($width);

        foreach (L::wrap($this->setting('receipt_footer', 'Bizi tercih ettiğiniz için teşekkür ederiz!'), $width) as $footerLine) {
            $text .= L::center($footerLine, $width);
        }

        return $text . "\n\n";
    }

    // ------------------------------------------------------------------
    // Yardımcılar
    // ------------------------------------------------------------------

    /**
     * CheckItem modellerini veya dizilerini tek tip fiş kalemi dizisine çevirir.
     *
     * Ürün adı için ilişkiden değil, check_items.product_name anlık görüntüsünden
     * okunur: ürün sonradan silinse/yeniden adlandırılsa bile fiş doğru kalır
     * ve ilişki başına ek sorgu (N+1) oluşmaz.
     */
    private function normalizeItems(iterable $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (is_object($item)) {
                $name = $item->product_name ?: ($item->product?->name ?? 'Ürün');
                $quantity = (float) $item->quantity;
                $unitPrice = (float) $item->unit_price;
                $total = $item->total_price !== null ? (float) $item->total_price : $unitPrice * $quantity;
                $notes = $item->notes;
                $isComplimentary = (bool) $item->is_complimentary;
            } else {
                $name = $item['product_name'] ?? $item['name'] ?? 'Ürün';
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $total = isset($item['total_price'])
                    ? (float) $item['total_price']
                    : (float) ($item['total'] ?? $unitPrice * $quantity);
                $notes = $item['notes'] ?? null;
                $isComplimentary = (bool) ($item['is_complimentary'] ?? false);
            }

            $normalized[] = [
                'name' => $name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $isComplimentary ? 0.0 : $total,
                'notes' => $notes,
                'is_complimentary' => $isComplimentary,
            ];
        }

        return $normalized;
    }

    /**
     * Şubenin ilgili tipteki aktif yazıcısını bulur.
     * Tip eşleşmesi yoksa şubenin varsayılan yazıcısına düşer.
     */
    private function resolvePrinter(int $branchId, string $type): ?Printer
    {
        return Printer::where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [$type])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    private function defaultBranchId(): int
    {
        return (int) (\App\Models\Branch::query()->orderBy('id')->value('id') ?? 1);
    }

    private function setting(string $key, string $default = ''): string
    {
        $value = Setting::get($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Fişte basılacak para birimi metni.
     * ₺ (U+20BA) CP857/ISO-8859-9 kod sayfalarında yoktur, yazıcı "?" basar;
     * bu yüzden yazdırma için ayrı bir ASCII karşılık kullanılır.
     */
    private function currencyText(): string
    {
        $symbol = $this->setting('receipt_currency_text', 'TL');

        return $symbol !== '' ? L::printable($symbol) : 'TL';
    }
}
