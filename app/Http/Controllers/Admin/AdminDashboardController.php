<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DeviceLog;
use App\Models\License;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        // Otomatik Migration & Seeding Güvencesi
        if (!\Illuminate\Support\Facades\Schema::hasTable('branches') || !\Illuminate\Support\Facades\Schema::hasTable('licenses') || !\Illuminate\Support\Facades\Schema::hasTable('staff_profiles')) {
            try {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            } catch (\Throwable $e) {}
        }

        try {
            $totalBranches = \Illuminate\Support\Facades\Schema::hasTable('branches') ? Branch::count() : 0;
            $activeLicenses = \Illuminate\Support\Facades\Schema::hasTable('licenses') ? License::where('status', 'Active')->count() : 0;
            $expiredLicenses = \Illuminate\Support\Facades\Schema::hasTable('licenses') ? License::where('status', 'Expired')->count() : 0;
            $onlineDevices = \Illuminate\Support\Facades\Schema::hasTable('devices') ? Device::where('last_ping_at', '>=', now()->subMinutes(2))->count() : 0;
            $totalDevices = \Illuminate\Support\Facades\Schema::hasTable('devices') ? Device::count() : 0;

            $recentLicenses = \Illuminate\Support\Facades\Schema::hasTable('licenses') ? License::with('branch')->latest()->take(5)->get() : collect([]);
            $recentDevices = \Illuminate\Support\Facades\Schema::hasTable('devices') ? Device::with(['branch', 'license'])->latest('last_ping_at')->take(5)->get() : collect([]);
            $recentLogs = \Illuminate\Support\Facades\Schema::hasTable('device_logs') ? DeviceLog::with('device')->latest()->take(10)->get() : collect([]);
        } catch (\Throwable $e) {
            $totalBranches = 0;
            $activeLicenses = 0;
            $expiredLicenses = 0;
            $onlineDevices = 0;
            $totalDevices = 0;
            $recentLicenses = collect([]);
            $recentDevices = collect([]);
            $recentLogs = collect([]);
        }

        return view('admin.dashboard', compact(
            'totalBranches',
            'activeLicenses',
            'expiredLicenses',
            'onlineDevices',
            'totalDevices',
            'recentLicenses',
            'recentDevices',
            'recentLogs'
        ));
    }
}
