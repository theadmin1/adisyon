<?php

namespace App\Support;

use App\Models\Setting;

class PaymentMethods
{
    /** @return array<string, array{label:string,short_label:string,description:string,icon:string,color:string,setting:string,default:bool}> */
    public static function catalog(): array
    {
        return [
            'nakit' => ['label' => 'Nakit', 'short_label' => 'NAKİT', 'description' => 'Nakit para ile tahsilat', 'icon' => 'fi-rr-money-bill-wave', 'color' => 'emerald', 'setting' => 'enable_cash', 'default' => true],
            'kredi_karti' => ['label' => 'Kredi / Banka Kartı', 'short_label' => 'K. KARTI', 'description' => 'POS cihazı ile tahsilat', 'icon' => 'fi-rr-credit-card', 'color' => 'indigo', 'setting' => 'enable_card', 'default' => true],
            'yemek_karti' => ['label' => 'Sodexo / Pluxee', 'short_label' => 'SODEXO', 'description' => 'Sodexo veya Pluxee yemek kartı', 'icon' => 'fi-rr-ticket', 'color' => 'purple', 'setting' => 'enable_sodexo', 'default' => true],
            'multinet' => ['label' => 'Multinet', 'short_label' => 'MULTİNET', 'description' => 'Multinet yemek kartı', 'icon' => 'fi-rr-receipt', 'color' => 'amber', 'setting' => 'enable_multinet', 'default' => true],
            'ticket' => ['label' => 'Ticket Restaurant', 'short_label' => 'TICKET', 'description' => 'Ticket Restaurant yemek kartı', 'icon' => 'fi-rr-ticket', 'color' => 'orange', 'setting' => 'enable_ticket', 'default' => true],
            'sancaktepe_personel_kart' => ['label' => 'Sancaktepe Personel Kart', 'short_label' => 'SANCAKTEPE', 'description' => 'Personel kartı ile indirimli tahsilat', 'icon' => 'fi-rr-id-badge', 'color' => 'cyan', 'setting' => 'enable_sancaktepe_personel_card', 'default' => true],
            'istanbulkart' => ['label' => 'İstanbulkart', 'short_label' => 'İSTANBULKART', 'description' => 'İstanbulkart ile ödeme', 'icon' => 'fi-rr-credit-card', 'color' => 'sky', 'setting' => 'enable_istanbulkart', 'default' => true],
            'cari' => ['label' => 'Açık Hesap', 'short_label' => 'CARİ', 'description' => 'Cari veya borç kaydı', 'icon' => 'fi-rr-user', 'color' => 'slate', 'setting' => 'enable_open_account', 'default' => true],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function active(?int $branchId): array
    {
        $settings = Setting::getAllAsArray($branchId);

        return array_filter(self::catalog(), static function (array $method) use ($settings): bool {
            $value = $settings[$method['setting']] ?? ($method['default'] ? '1' : '0');

            return (string) $value === '1';
        });
    }

    /** @return list<string> */
    public static function activeIds(?int $branchId): array
    {
        return array_keys(self::active($branchId));
    }

    /** @return list<string> */
    public static function settingKeys(): array
    {
        return array_values(array_unique(array_column(self::catalog(), 'setting')));
    }
}
