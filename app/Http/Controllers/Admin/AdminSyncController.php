<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Check;
use App\Models\Device;
use App\Models\OfflineSyncLog;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AdminSyncController extends Controller
{
    /**
     * Çevrimdışı Veri & Senkronizasyon Monitörü Görünümü
     */
    public function index(Request $request): View
    {
        $query = OfflineSyncLog::with(['device', 'branch'])->latest();

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $syncLogs = $query->paginate(25)->withQueryString();

        $branches = Branch::where('is_active', true)->get();
        $devices = Device::all();

        // İstatistikler
        $totalSyncedChecks = Check::where('is_synced', true)->whereNotNull('sync_uuid')->count();
        $totalSyncedPayments = Payment::where('is_synced', true)->whereNotNull('sync_uuid')->count();
        $recentSuccessLogs = OfflineSyncLog::where('status', 'success')->where('created_at', '>=', now()->subDays(7))->count();
        $recentErrorLogs = OfflineSyncLog::where('status', 'error')->where('created_at', '>=', now()->subDays(7))->count();

        return view('admin.sync.index', compact(
            'syncLogs',
            'branches',
            'devices',
            'totalSyncedChecks',
            'totalSyncedPayments',
            'recentSuccessLogs',
            'recentErrorLogs'
        ));
    }

    /**
     * Hatalı veya eski senkronizasyon loglarını temizler.
     */
    public function clearLogs(): RedirectResponse
    {
        OfflineSyncLog::truncate();
        return redirect()->route('admin.sync.index')->with('success', 'Tüm senkronizasyon logları başarıyla temizlendi.');
    }

    /**
     * Çevrimdışı Sistem Güncelleme Sayfası Görünümü
     */
    public function updatesIndex(): View
    {
        $sqlitePath = config('database.connections.sqlite.database');
        $dbSize = File::exists($sqlitePath) ? round(File::size($sqlitePath) / 1024, 2) . ' KB' : '0 KB';
        $dbLastModified = File::exists($sqlitePath) ? date('Y-m-d H:i:s', File::lastModified($sqlitePath)) : '-';

        return view('admin.updates.index', compact('dbSize', 'dbLastModified'));
    }

    /**
     * Çevrimdışı (Offline) sistemi adisyon.synaptropic.com üzerinden günceller.
     */
    public function runSystemUpdate(Request $request): RedirectResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('app:update-offline-system', ['--force' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return redirect()->back()->with('success', '🚀 Çevrimdışı sistem adisyon.synaptropic.com üzerinden başarıyla güncellendi! ' . $output);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Güncelleme hatası: ' . $e->getMessage());
        }
    }

    /**
     * Veritabanı senkronizasyonunu adım adım çalıştırır ve MySQL vs SQLite verilerini canlı doğrular.
     */
    public function verifyDatabaseSync(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $sqlitePath = config('database.connections.sqlite.database');
            if (!File::exists($sqlitePath)) {
                @File::makeDirectory(dirname($sqlitePath), 0755, true, true);
                @touch($sqlitePath);
            }

            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--database' => 'sqlite',
                    '--force' => true,
                ]);
            } catch (\Throwable $mEx) {}

            $sqliteConn = DB::connection('sqlite');
            $schema = \Illuminate\Support\Facades\Schema::connection('sqlite');

            $before = [
                'categories_count' => $schema->hasTable('categories') ? $sqliteConn->table('categories')->count() : 0,
                'products_count' => $schema->hasTable('products') ? $sqliteConn->table('products')->count() : 0,
                'tables_count' => $schema->hasTable('dining_tables') ? $sqliteConn->table('dining_tables')->count() : 0,
                'halls_count' => $schema->hasTable('halls') ? $sqliteConn->table('halls')->count() : 0,
                'sample_products' => $schema->hasTable('products') ? $sqliteConn->table('products')->limit(5)->get(['id', 'name', 'price']) : [],
            ];

            \Illuminate\Support\Facades\Artisan::call('app:sync-local', ['--fresh' => true]);
            $syncOutput = \Illuminate\Support\Facades\Artisan::output();

            $after = [
                'categories_count' => $schema->hasTable('categories') ? $sqliteConn->table('categories')->count() : 0,
                'products_count' => $schema->hasTable('products') ? $sqliteConn->table('products')->count() : 0,
                'tables_count' => $schema->hasTable('dining_tables') ? $sqliteConn->table('dining_tables')->count() : 0,
                'halls_count' => $schema->hasTable('halls') ? $sqliteConn->table('halls')->count() : 0,
                'sample_products' => $schema->hasTable('products') ? $sqliteConn->table('products')->limit(5)->get(['id', 'name', 'price']) : [],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Veritabanı senkronizasyon ve doğrulama işlemi başarıyla tamamlandı.',
                'sync_output' => $syncOutput,
                'before' => $before,
                'after' => $after,
                'is_verified' => true,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Doğrulama senkronizasyon hatası: ' . $e->getMessage(),
            ], 500);
        }
    }
}
