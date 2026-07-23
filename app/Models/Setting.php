<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'group',
        'key',
        'value',
    ];

    /**
     * Belirtilen anahtarın değerini getirir
     */
    public static function get(string $key, mixed $default = null, ?int $branchId = null): mixed
    {
        try {
            $query = static::where('key', $key);
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            $setting = $query->first();

            if (!$setting) {
                return $default;
            }

            // JSON decoding if applicable
            $decoded = json_decode($setting->value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Belirtilen anahtarın değerini kaydeder veya günceller
     */
    public static function set(string $key, mixed $value, string $group = 'general', ?int $branchId = null): static
    {
        $stringValue = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        return static::updateOrCreate(
            [
                'key' => $key,
                'branch_id' => $branchId,
            ],
            [
                'group' => $group,
                'value' => $stringValue,
            ]
        );
    }

    /**
     * Tüm ayarları anahtar-değer dizisi olarak getirir
     */
    public static function getAllAsArray(?int $branchId = null): array
    {
        try {
            $query = static::query();
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            
            return $query->get()->pluck('value', 'key')->map(function ($val) {
                $decoded = json_decode($val, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
            })->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
