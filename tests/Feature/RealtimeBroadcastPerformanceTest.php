<?php

namespace Tests\Feature;

use App\Events\WaiterRealtimeUpdated;
use App\Models\Branch;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Support\CatalogVersion;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RealtimeBroadcastPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_realtime_updates_are_queued_instead_of_blocking_the_request(): void
    {
        Queue::fake();

        $branch = Branch::create(['name' => 'Performance Test', 'code' => 'PERF-01']);
        $table = DiningTable::create([
            'branch_id' => $branch->id,
            'name' => 'Table 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $event = new WaiterRealtimeUpdated($branch->id, ['tables'], 'DiningTable.updated', [
            'table_id' => $table->id,
        ]);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertNotInstanceOf(ShouldBroadcastNow::class, $event);

        Queue::assertPushed(BroadcastEvent::class, fn (BroadcastEvent $job): bool => $job->event instanceof WaiterRealtimeUpdated
            && $job->event->branchId === $branch->id
        );
    }

    public function test_container_runs_a_dedicated_database_queue_worker(): void
    {
        $supervisor = file_get_contents(base_path('docker/supervisord.conf'));

        $this->assertStringContainsString('[program:queue-worker]', $supervisor);
        $this->assertStringContainsString('queue:work database', $supervisor);
    }

    public function test_product_and_stock_changes_advance_the_lightweight_catalog_version(): void
    {
        Queue::fake();
        Cache::flush();

        $branch = Branch::create(['name' => 'Catalog Test', 'code' => 'CAT-01']);
        $this->assertSame(1, CatalogVersion::current($branch->id));

        $category = Category::create([
            'branch_id' => $branch->id,
            'name' => 'İçecekler',
            'slug' => 'icecekler',
            'is_active' => true,
        ]);
        $afterCategory = CatalogVersion::current($branch->id);

        $product = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'name' => 'Ayran',
            'slug' => 'ayran',
            'price' => 30,
            'stock_quantity' => 10,
            'track_stock' => true,
            'is_active' => true,
        ]);
        $afterProduct = CatalogVersion::current($branch->id);

        $product->decrement('stock_quantity');
        $afterStock = CatalogVersion::current($branch->id);

        $this->assertGreaterThan(1, $afterCategory);
        $this->assertGreaterThan($afterCategory, $afterProduct);
        $this->assertGreaterThan($afterProduct, $afterStock);
    }
}
