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
        $totalBranches = Branch::count();
        $activeLicenses = License::where('status', 'Active')->count();
        $expiredLicenses = License::where('status', 'Expired')->count();
        $onlineDevices = Device::where('last_ping_at', '>=', now()->subMinutes(2))->count();
        $totalDevices = Device::count();

        $recentLicenses = License::with('branch')->latest()->take(5)->get();
        $recentDevices = Device::with(['branch', 'license'])->latest('last_ping_at')->take(5)->get();
        $recentLogs = DeviceLog::with('device')->latest()->take(10)->get();

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
