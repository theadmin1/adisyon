<?php

namespace App\Services\Printing;

/**
 * Termal fiş (ESC/POS) metin yerleşimi için çok baytlı (UTF-8) güvenli yardımcılar.
 *
 * Neden ayrı bir sınıf: sprintf("%-26s") baytla hizalar, karakterle değil.
 * "Köfte" UTF-8'de 5 karakter ama 6 bayttır; sprintf ile sütunlar kayar.
 * Buradaki tüm ölçümler mb_strlen ile karakter bazlıdır.
 */
class ReceiptLayout
{
    /**
     * Yazıcı kod sayfasında (CP857) karşılığı olmayan karakterlerin
     * ASCII/CP857 güvenli eşlenikleri. Türkçe harfler CP857'de mevcut
     * olduğu için dokunulmaz; yalnızca desteklenmeyenler çevrilir.
     */
    private const CHAR_FALLBACKS = [
        '₺' => 'TL',
        '€' => 'EUR',
        '₽' => 'RUB',
        '“' => '"',
        '”' => '"',
        '‘' => "'",
        '’' => "'",
        '–' => '-',
        '—' => '-',
        '…' => '...',
        '•' => '*',
        '₼' => 'AZN',
    ];

    /**
     * Yazıcının basamayacağı karakterleri güvenli eşlenikleriyle değiştirir.
     * Emoji ve diğer BMP dışı işaretler tamamen atılır (yazıcı bunları çöp bayt basar).
     */
    public static function printable(string $text): string
    {
        $text = strtr($text, self::CHAR_FALLBACKS);

        // Emoji / sembol blokları (yazıcı bunları çöp bayt basar) komşu boşluklarıyla
        // birlikte tek boşluğa indirgenir; yalnızca gerçekten temizlik olduysa
        // baştaki/sondaki artık boşluk kırpılır (girintiler bozulmasın diye).
        $cleaned = preg_replace(
            '/\s*[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}]+\s*/u',
            ' ',
            $text
        );

        if ($cleaned === null || $cleaned === $text) {
            return $text;
        }

        return trim($cleaned);
    }

    /**
     * Metni karakter bazlı sabit genişliğe hizalar (left | right | center).
     * Taşan metin kırpılır, eksik kalan boşlukla doldurulur.
     */
    public static function pad(string $text, int $width, string $align = 'left'): string
    {
        if ($width <= 0) {
            return '';
        }

        $text = self::printable($text);
        $len = mb_strlen($text);

        if ($len > $width) {
            return mb_substr($text, 0, $width);
        }

        $gap = $width - $len;

        return match ($align) {
            'right' => str_repeat(' ', $gap) . $text,
            'center' => str_repeat(' ', intdiv($gap, 2)) . $text . str_repeat(' ', $gap - intdiv($gap, 2)),
            default => $text . str_repeat(' ', $gap),
        };
    }

    /** Fiş genişliğinde ortalanmış tek satır. */
    public static function center(string $text, int $width): string
    {
        return rtrim(self::pad($text, $width, 'center')) . "\n";
    }

    /** Sola dayalı tek satır. */
    public static function left(string $text, int $width): string
    {
        return rtrim(self::pad($text, $width, 'left')) . "\n";
    }

    /** Ayraç çizgisi. */
    public static function rule(int $width, string $char = '-'): string
    {
        return str_repeat($char, max(1, $width)) . "\n";
    }

    /**
     * Solda etiket, sağda değer olacak şekilde tek satır.
     * Etiket uzunsa kırpılır, değer her zaman tam görünür.
     */
    public static function keyValue(string $label, string $value, int $width): string
    {
        $value = self::printable($value);
        $valueLen = mb_strlen($value);
        $labelWidth = max(1, $width - $valueLen - 1);

        return self::pad($label, $labelWidth, 'left') . ' ' . self::pad($value, $valueLen, 'right') . "\n";
    }

    /**
     * Uzun metni fiş genişliğine göre kelime kelime satırlara böler.
     *
     * @return string[]
     */
    public static function wrap(string $text, int $width, string $indent = ''): array
    {
        $text = self::printable($text);
        $usable = max(1, $width - mb_strlen($indent));
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            // Tek kelime satıra sığmıyorsa zorla böl
            while (mb_strlen($word) > $usable) {
                if ($current !== '') {
                    $lines[] = $indent . $current;
                    $current = '';
                }
                $lines[] = $indent . mb_substr($word, 0, $usable);
                $word = mb_substr($word, $usable);
            }

            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (mb_strlen($candidate) > $usable) {
                $lines[] = $indent . $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $indent . $current;
        }

        return $lines;
    }

    /** Türk Lirası biçimi: 1.234,56 (yazıcıda ₺ basılamadığı için sembol ayrı verilir). */
    public static function money(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.');
    }

    /** Adet: tam sayıysa "2", ondalıklıysa "1,5". */
    public static function quantity(float|int|string $qty): string
    {
        $qty = (float) $qty;

        return fmod($qty, 1.0) === 0.0
            ? (string) (int) $qty
            : rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
    }
}
