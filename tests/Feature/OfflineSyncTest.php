<?php

namespace Tests\Feature;

use App\Console\Commands\SyncLocalDatabaseCommand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_synced_categories_are_visible_to_the_authenticated_branch(): void
    {
        $branchId = 501;
        $categoryUuid = (string) Str::uuid();
        $productUuid = (string) Str::uuid();

        $method = new ReflectionMethod(SyncLocalDatabaseCommand::class, 'syncDataToSqlite');
        $method->invoke(
            app(SyncLocalDatabaseCommand::class),
            collect([[
                'id' => 601,
                'name' => 'Offline Kasa',
                'email' => 'offline@example.test',
                'restaurant_id' => 'REST-OFFLINE',
                'password_hash' => bcrypt('secret-password'),
                'is_admin' => false,
            ]]),
            collect(),
            collect(),
            collect(),
            collect([[
                'id' => 701,
                'sync_uuid' => $categoryUuid,
                'name' => 'İçecekler',
                'slug' => 'icecekler',
                'sort_order' => 1,
                'is_active' => true,
            ]]),
            collect([[
                'id' => 801,
                'sync_uuid' => $productUuid,
                'category_id' => 701,
                'category_sync_uuid' => $categoryUuid,
                'name' => 'Limonata',
                'slug' => 'limonata',
                'price' => 75,
                'stock_quantity' => 10,
                'track_stock' => true,
                'is_active' => true,
            ]]),
            collect(),
            collect(),
            collect(),
            collect(),
            collect(),
            collect(),
            [
                'id' => $branchId,
                'name' => 'Offline Şube',
                'code' => 'OFF-501',
                'is_active' => true,
            ],
        );

        $user = User::where('restaurant_id', 'REST-OFFLINE')->firstOrFail();
        Auth::login($user);

        $category = Category::with('products')->sole();

        $this->assertSame($branchId, $category->branch_id);
        $this->assertSame('İçecekler', $category->name);
        $this->assertSame('Limonata', $category->products->sole()->name);
    }
}
