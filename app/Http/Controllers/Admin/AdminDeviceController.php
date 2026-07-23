<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceLog;
use Illuminate\View\View;

class AdminDeviceController extends Controller
{
    public function index(): View
    {
        try {
            // 2 dakikadan uzun süredir ping atmayan cihazları Offline olarak güncelle
            Device::where('status', 'Online')
                ->where(function ($query) {
                    $query->whereNull('last_ping_at')
                          ->orWhere('last_ping_at', '<', now()->subMinutes(2));
                })->update(['status' => 'Offline']);

            $devices = Device::with(['branch', 'license'])->latest('last_ping_at')->paginate(15);
        } catch (\Throwable $e) {
            $devices = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }
        return view('admin.devices.index', compact('devices'));
    }

    public function logs(): View
    {
        try {
            $logs = DeviceLog::with('device')->latest()->paginate(25);
        } catch (\Throwable $e) {
            $logs = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        }
        return view('admin.logs.index', compact('logs'));
    }
}
