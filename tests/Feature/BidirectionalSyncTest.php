<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\License;
use App\Services\BidirectionalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BidirectionalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_offline_model_changes_are_marked_dirty_and_deletions_create_tombstones(): void
    {
        $branch = $this->branch();
        $hall = Hall::create([
            'branch_id' => $branch->id,
            'name' => 'Bahçe',
            'code' => 'BHC',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertNotNull($hall->sync_uuid);
        $this->assertFalse((bool) $hall->is_synced);

        $hall->update(['name' => 'Kış Bahçesi']);
        $this->assertFalse((bool) $hall->fresh()->is_synced);

        $syncUuid = $hall->sync_uuid;
        $hall->delete();

        $this->assertDatabaseHas('deleted_records', [
            'type' => 'halls',
            'sync_uuid' => $syncUuid,
            'is_synced' => false,
        ]);
    }

    public function test_offline_add_update_and_delete_payload_is_applied_with_relationships(): void
    {
        $branch = $this->branch();
        $hall = Hall::create([
            'branch_id' => $branch->id,
            'name' => 'Teras',
            'code' => 'TRS',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $table = DiningTable::create([
            'branch_id' => $branch->id,
            'hall_id' => $hall->id,
            'name' => 'T1',
            'code' => 'T1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $service = app(BidirectionalSyncService::class);
        $changes = $service->collectLocalChanges($branch->id);

        $this->assertCount(1, $changes['resources']['halls']);
        $this->assertCount(1, $changes['resources']['dining_tables']);
        $this->assertSame(
            $hall->sync_uuid,
            $changes['resources']['dining_tables'][0]['_relations']['hall_id']
        );

        // Remove the local rows with query builder to emulate an empty server,
        // then apply the exact device payload to that server database.
        DB::table('dining_tables')->whereKey($table->id)->delete();
        DB::table('halls')->whereKey($hall->id)->delete();

        $synced = $service->applyPush(
            $branch->id,
            $changes['resources'],
            [],
            'sqlite'
        );

        $serverHall = DB::table('halls')->where('sync_uuid', $hall->sync_uuid)->first();
        $serverTable = DB::table('dining_tables')->where('sync_uuid', $table->sync_uuid)->first();
        $this->assertNotNull($serverHall);
        $this->assertNotNull($serverTable);
        $this->assertSame($serverHall->id, $serverTable->hall_id);
        $this->assertContains($hall->sync_uuid, $synced);
        $this->assertContains($table->sync_uuid, $synced);

        $service->applyPush($branch->id, [], [
            ['resource' => 'dining_tables', 'sync_uuid' => $table->sync_uuid],
            ['resource' => 'halls', 'sync_uuid' => $hall->sync_uuid],
        ], 'sqlite');

        $this->assertDatabaseMissing('dining_tables', ['sync_uuid' => $table->sync_uuid]);
        $this->assertDatabaseMissing('halls', ['sync_uuid' => $hall->sync_uuid]);
    }

    public function test_online_snapshot_adds_updates_and_removes_local_records(): void
    {
        $branch = $this->branch();
        $hallUuid = (string) Str::uuid();
        $tableUuid = (string) Str::uuid();
        $service = app(BidirectionalSyncService::class);

        $resources = [
            'halls' => [[
                '_source_id' => 501,
                'sync_uuid' => $hallUuid,
                '_relations' => [],
                'name' => 'Online Salon',
                'code' => 'ONL',
                'sort_order' => 1,
                'is_active' => true,
            ]],
            'dining_tables' => [[
                '_source_id' => 601,
                'sync_uuid' => $tableUuid,
                '_relations' => ['hall_id' => $hallUuid],
                'name' => 'Online Masa',
                'code' => 'OM1',
                'capacity' => 2,
                'status' => 'available',
                'is_active' => true,
            ]],
        ];

        $service->applyPull($branch->id, $resources, [
            'halls' => [$hallUuid],
            'dining_tables' => [$tableUuid],
        ]);

        $localHall = DB::table('halls')->where('sync_uuid', $hallUuid)->first();
        $localTable = DB::table('dining_tables')->where('sync_uuid', $tableUuid)->first();
        $this->assertSame('Online Salon', $localHall->name);
        $this->assertSame($localHall->id, $localTable->hall_id);
        $this->assertTrue((bool) $localHall->is_synced);

        $resources['halls'][0]['name'] = 'Online Salon Güncel';
        $service->applyPull($branch->id, $resources, [
            'halls' => [$hallUuid],
            'dining_tables' => [$tableUuid],
        ]);
        $this->assertDatabaseHas('halls', [
            'sync_uuid' => $hallUuid,
            'name' => 'Online Salon Güncel',
        ]);

        $service->applyPull($branch->id, [], [
            'halls' => [],
            'dining_tables' => [],
        ]);
        $this->assertDatabaseMissing('dining_tables', ['sync_uuid' => $tableUuid]);
        $this->assertDatabaseMissing('halls', ['sync_uuid' => $hallUuid]);
    }

    public function test_authenticated_sync_api_accepts_generic_changes_and_returns_them_on_pull(): void
    {
        $branch = $this->branch();
        $apiKey = Str::random(64);
        $license = License::create([
            'branch_id' => $branch->id,
            'license_key' => 'LIC-'.Str::upper(Str::random(20)),
            'status' => 'Active',
            'expires_at' => now()->addYear(),
            'max_devices' => 2,
        ]);
        Device::create([
            'branch_id' => $branch->id,
            'license_id' => $license->id,
            'device_code' => 'TEST-KASA',
            'device_guid' => (string) Str::uuid(),
            'api_key_hash' => hash('sha256', $apiKey),
            'status' => 'Offline',
        ]);

        $hallUuid = (string) Str::uuid();
        $tableUuid = (string) Str::uuid();
        $checkUuid = (string) Str::uuid();
        $push = $this->withHeader('X-Device-Api-Key', $apiKey)->postJson('/api/v1/sync/push', [
            'batch_id' => 'BATCH-API-TEST',
            'checks' => [[
                'sync_uuid' => $checkUuid,
                'dining_table_id' => 8002,
                'dining_table_sync_uuid' => $tableUuid,
                'check_number' => 'CHK-OFFLINE-UUID',
                'total_amount' => 0,
                'status' => 'open',
                'items' => [],
            ]],
            'sync_resources' => [
                'halls' => [[
                    '_source_id' => 8001,
                    'sync_uuid' => $hallUuid,
                    '_relations' => [],
                    'name' => 'API Salonu',
                    'code' => 'API',
                    'sort_order' => 10,
                    'is_active' => true,
                ]],
                'dining_tables' => [[
                    '_source_id' => 8002,
                    'sync_uuid' => $tableUuid,
                    '_relations' => ['hall_id' => $hallUuid],
                    'name' => 'API Masa 1',
                    'code' => 'API-M1',
                    'capacity' => 4,
                    'status' => 'available',
                    'is_active' => true,
                ]],
            ],
            'deleted_resources' => [],
        ]);

        $push->assertOk()
            ->assertJsonPath('success', true);
        $this->assertContains($hallUuid, $push->json('synced_uuids'));
        $this->assertContains($tableUuid, $push->json('synced_uuids'));
        $this->assertContains($checkUuid, $push->json('synced_uuids'));
        $this->assertDatabaseHas('halls', [
            'branch_id' => $branch->id,
            'sync_uuid' => $hallUuid,
            'name' => 'API Salonu',
        ]);
        $serverTable = DiningTable::where('sync_uuid', $tableUuid)->firstOrFail();
        $this->assertNotSame(8002, $serverTable->id);
        $this->assertDatabaseHas('checks', [
            'branch_id' => $branch->id,
            'sync_uuid' => $checkUuid,
            'dining_table_id' => $serverTable->id,
            'status' => 'open',
        ]);
        $this->assertSame('occupied', $serverTable->fresh()->status->value);

        $pull = $this->withHeader('X-Device-Api-Key', $apiKey)->getJson('/api/v1/sync/pull');
        $pull->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'sync_uuid' => $hallUuid,
                'name' => 'API Salonu',
            ]);
    }

    public function test_required_portal_hashes_are_present_in_offline_snapshot(): void
    {
        $branch = $this->branch();
        $now = now();
        $supplierId = DB::table('suppliers')->insertGetId([
            'branch_id' => $branch->id,
            'name' => 'Hash Tedarikçisi',
            'is_active' => true,
            'portal_enabled' => true,
            'portal_token_hash' => hash('sha256', 'portal-token'),
            'portal_code_hash' => hash('sha256', '1234'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('supplier_quote_requests')->insert([
            'branch_id' => $branch->id,
            'supplier_id' => $supplierId,
            'request_number' => 'TF-HASH-TEST',
            'token_hash' => hash('sha256', 'quote-token'),
            'status' => 'open',
            'requested_by_name' => 'Test Kullanıcısı',
            'expires_at' => now()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $payload = app(BidirectionalSyncService::class)
            ->buildPullPayload($branch->id, 'sqlite');

        $supplier = collect($payload['resources']['suppliers'])
            ->firstWhere('name', 'Hash Tedarikçisi');
        $request = collect($payload['resources']['supplier_quote_requests'])
            ->firstWhere('request_number', 'TF-HASH-TEST');

        $this->assertSame(hash('sha256', 'portal-token'), $supplier['portal_token_hash']);
        $this->assertSame(hash('sha256', '1234'), $supplier['portal_code_hash']);
        $this->assertSame(hash('sha256', 'quote-token'), $request['token_hash']);
    }

    private function branch(): Branch
    {
        return Branch::create([
            'name' => 'Senkron Şubesi',
            'code' => 'SYNC-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }
}
