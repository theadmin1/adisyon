<?php

namespace Tests\Feature;

use App\Console\Commands\SyncLocalDatabaseCommand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function test_cloud_check_is_attached_to_the_local_table_by_uuid_instead_of_server_id(): void
    {
        $branchId = 501;
        $tableUuid = (string) Str::uuid();
        $checkUuid = (string) Str::uuid();
        $payload = [
            'sync_resources' => [
                'dining_tables' => [[
                    '_source_id' => 1,
                    'sync_uuid' => $tableUuid,
                ]],
            ],
            'checks' => [[
                'id' => 9001,
                'sync_uuid' => $checkUuid,
                'dining_table_id' => 1,
                'check_number' => 'CHK-UUID-MAP',
                'status' => 'open',
                'subtotal' => 125,
                'discount_total' => 0,
                'total' => 125,
                'items' => [],
            ]],
        ];

        $enrichMethod = new ReflectionMethod(SyncLocalDatabaseCommand::class, 'enrichLegacyRelationshipUuids');
        $payload = $enrichMethod->invoke(app(SyncLocalDatabaseCommand::class), $payload);

        $this->assertSame($tableUuid, $payload['checks'][0]['dining_table_sync_uuid']);

        DB::table('branches')->insert([
            'id' => $branchId,
            'name' => 'Offline Şube',
            'code' => 'OFF-501',
            'is_active' => true,
        ]);
        DB::table('dining_tables')->insert([
            'id' => 13,
            'branch_id' => $branchId,
            'name' => 'Masa 1',
            'code' => 'M1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
            'sync_uuid' => $tableUuid,
            'is_synced' => true,
        ]);

        $syncMethod = new ReflectionMethod(SyncLocalDatabaseCommand::class, 'syncDataToSqlite');
        $syncMethod->invoke(
            app(SyncLocalDatabaseCommand::class),
            collect(),
            collect(),
            collect(),
            collect(),
            collect(),
            collect(),
            collect($payload['checks']),
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

        $this->assertDatabaseHas('checks', [
            'sync_uuid' => $checkUuid,
            'dining_table_id' => 13,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('dining_tables', [
            'id' => 13,
            'name' => 'Masa 1',
            'status' => 'occupied',
        ]);
    }
}
