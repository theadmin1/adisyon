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
        $devices = Device::with(['branch', 'license'])->latest('last_ping_at')->paginate(15);
        return view('admin.devices.index', compact('devices'));
    }

    public function logs(): View
    {
        $logs = DeviceLog::with('device')->latest()->paginate(25);
        return view('admin.logs.index', compact('logs'));
    }
}
