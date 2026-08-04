<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class CatalogVersion
{
    public static function current(int $branchId): int
    {
        return (int) Cache::get(self::key($branchId), 1);
    }

    public static function touch(int $branchId): void
    {
        if ($branchId <= 0) {
            return;
        }

        $key = self::key($branchId);
        Cache::add($key, 1, now()->addYear());
        Cache::increment($key);
    }

    private static function key(int $branchId): string
    {
        return "catalog_version:branch:{$branchId}";
    }
}
