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
     * Tüm 12 Sistem Modülü Tanımı
     */
    public static function availableModules(): array
    {
        return [
            'masalar' => ['name' => 'Masalar', 'icon' => 'fi-rr-room-service', 'color' => 'pink'],
            'hizli-satis' => ['name' => 'Hızlı Satış', 'icon' => 'fi-rr-bolt', 'color' => 'amber'],
            'online-siparis' => ['name' => 'Online Sipariş', 'icon' => 'fi-rr-shopping-cart', 'color' => 'sky'],
            'ayarlar' => ['name' => 'Ayarlar', 'icon' => 'fi-rr-settings', 'color' => 'purple'],
            'mutfak' => ['name' => 'Mutfak', 'icon' => 'fi-rr-restaurant', 'color' => 'emerald'],
            'kasa' => ['name' => 'Kasa', 'icon' => 'fi-rr-cash-register', 'color' => 'violet'],
            'urunler' => ['name' => 'Ürünler', 'icon' => 'fi-rr-box-open', 'color' => 'rose'],
            'kategoriler' => ['name' => 'Kategoriler', 'icon' => 'fi-rr-apps', 'color' => 'teal'],
            'subeler' => ['name' => 'Şubeler', 'icon' => 'fi-rr-shop', 'color' => 'blue'],
            'salonlar' => ['name' => 'Salonlar', 'icon' => 'fi-rr-objects-column', 'color' => 'cyan'],
            'kullanicilar' => ['name' => 'Kullanıcılar', 'icon' => 'fi-rr-users', 'color' => 'orange'],
            'raporlar' => ['name' => 'Raporlar', 'icon' => 'fi-rr-chart-pie-alt', 'color' => 'fuchsia'],
        ];
    }

    /**
     * Varsayılan Rol İzinleri Matrisi (Veritabanında kayıt yoksa kullanılır)
     */
    public static function defaultPermissions(): array
    {
        return [
            'Garson'   => ['masalar', 'hizli-satis', 'online-siparis', 'mutfak'],
            'Mutfak'   => ['mutfak', 'online-siparis'],
            'Kasa'     => ['kasa', 'masalar', 'hizli-satis', 'online-siparis', 'urunler', 'kategoriler', 'raporlar'],
            'Kaptan'   => ['masalar', 'hizli-satis', 'online-siparis', 'mutfak', 'kasa', 'salonlar', 'urunler'],
            'Şef'      => ['mutfak', 'online-siparis', 'urunler', 'kategoriler'],
            'Yönetici' => ['masalar', 'hizli-satis', 'online-siparis', 'ayarlar', 'mutfak', 'kasa', 'urunler', 'kategoriler', 'subeler', 'salonlar', 'kullanicilar', 'raporlar'],
            'Müdür'    => ['masalar', 'hizli-satis', 'online-siparis', 'ayarlar', 'mutfak', 'kasa', 'urunler', 'kategoriler', 'subeler', 'salonlar', 'kullanicilar', 'raporlar'],
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
