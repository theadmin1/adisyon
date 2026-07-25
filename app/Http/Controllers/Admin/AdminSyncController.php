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
}
