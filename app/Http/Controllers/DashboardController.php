<?php

namespace App\Http\Controllers;

use App\Models\Check;
use App\Models\DiningTable;
use App\Models\RolePermission;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        if (Schema::hasTable('staff_profiles')) {
            if (! session()->has('active_staff_id')) {
                return redirect()->route('staff.profiles');
            }
        }

        $user = Auth::user();
        $staffRole = session('active_staff_role', 'Yönetici');

        // Veritabanı ve Admin paneli üzerinden dinamik rol yetkileri
        $allowedCategories = RolePermission::getPermissionsForRole($staffRole);

        // Gerçek veritabanı sorguları (Senkronize edilen canlı/yerel veriler)
        $openRevenue = Check::whereIn('status', ['open', 'awaiting_payment'])->sum('total');
        $occupiedCount = DiningTable::where('status', 'occupied')->count();
        $completedOrdersCount = Check::where('status', 'closed')->count();
        $activeWaitersCount = StaffProfile::where('is_active', true)->count();

        $stats = [
            'total_sales' => '₺'.number_format($openRevenue, 2),
            'open_tables' => $occupiedCount,
            'completed_orders' => $completedOrdersCount,
            'active_waiters' => $activeWaitersCount,
        ];

        $tables = DiningTable::with(['hall', 'checks' => fn ($q) => $q->whereIn('status', ['open', 'awaiting_payment'])])
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $check = $t->checks->first();
                $timeStr = '-';
                if ($check && $check->created_at) {
                    try {
                        $timeStr = Carbon::parse($check->created_at)->diffForHumans();
                    } catch (\Throwable $e) {
                    }
                }

                return [
                    'name' => $t->name.($t->hall ? ' ('.$t->hall->name.')' : ''),
                    'status' => $t->status === 'occupied' ? 'busy' : ($t->status === 'reserved' ? 'reserved' : 'free'),
                    'total' => '₺'.number_format($check ? $check->total : 0, 2),
                    'time' => $timeStr,
                ];
            });

        return view('dashboard', compact('user', 'stats', 'tables', 'staffRole', 'allowedCategories'));
    }
}
