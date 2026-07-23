<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class RolePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_name',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Tüm 8 Sistem Modülü Tanımı
     */
    public static function availableModules(): array
    {
        return [
            'masalar' => ['name' => 'Masalar', 'icon' => 'fi-rr-room-service', 'color' => 'indigo'],
            'hizli-satis' => ['name' => 'Hızlı Satış', 'icon' => 'fi-rr-bolt', 'color' => 'amber'],
            'paket-servis' => ['name' => 'Paket Servis', 'icon' => 'fi-rr-box-alt', 'color' => 'sky'],
            'mutfak' => ['name' => 'Mutfak', 'icon' => 'fi-rr-restaurant', 'color' => 'emerald'],
            'urunler' => ['name' => 'Ürünler', 'icon' => 'fi-rr-box-open', 'color' => 'rose'],
            'stoklar' => ['name' => 'Stoklar', 'icon' => 'fi-rr-boxes', 'color' => 'cyan'],
            'raporlar' => ['name' => 'Raporlar', 'icon' => 'fi-rr-chart-pie-alt', 'color' => 'fuchsia'],
            'ayarlar' => ['name' => 'Ayarlar', 'icon' => 'fi-rr-settings', 'color' => 'purple'],
        ];
    }

    /**
     * Varsayılan Rol İzinleri Matrisi (Veritabanında kayıt yoksa kullanılır)
     */
    public static function defaultPermissions(): array
    {
        return [
            'Garson'   => ['masalar', 'hizli-satis', 'paket-servis', 'mutfak'],
            'Mutfak'   => ['mutfak', 'paket-servis'],
            'Kasa'     => ['masalar', 'hizli-satis', 'paket-servis', 'urunler', 'stoklar', 'raporlar'],
            'Kaptan'   => ['masalar', 'hizli-satis', 'paket-servis', 'mutfak', 'urunler', 'stoklar'],
            'Şef'      => ['mutfak', 'paket-servis', 'urunler', 'stoklar'],
            'Yönetici' => ['masalar', 'hizli-satis', 'paket-servis', 'mutfak', 'urunler', 'stoklar', 'raporlar', 'ayarlar'],
            'Müdür'    => ['masalar', 'hizli-satis', 'paket-servis', 'mutfak', 'urunler', 'stoklar', 'raporlar', 'ayarlar'],
        ];
    }

    /**
     * Veritabanından veya Varsayılandan Belirtilen Rol İçin Yetkileri Getirir
     */
    public static function getPermissionsForRole(string $roleName): array
    {
        if (Schema::hasTable('role_permissions')) {
            try {
                $record = static::where('role_name', $roleName)->first();
                if ($record && is_array($record->permissions)) {
                    return $record->permissions;
                }
            } catch (\Throwable $e) {
                // Fallback on error
            }
        }

        $defaults = static::defaultPermissions();
        return $defaults[$roleName] ?? $defaults['Yönetici'];
    }
}
