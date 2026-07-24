<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('staff_profiles')) {
            if (!session()->has('active_staff_id')) {
                return redirect()->route('staff.profiles');
            }
        }

        $user = Auth::user();
        $staffRole = session('active_staff_role', 'Yönetici');

        // Veritabanı ve Admin paneli üzerinden dinamik rol yetkileri
        $allowedCategories = \App\Models\RolePermission::getPermissionsForRole($staffRole);

        // Gerçek veritabanı sorguları (Senkronize edilen canlı/yerel veriler)
        $openRevenue = \App\Models\Check::whereIn('status', ['open', 'awaiting_payment'])->sum('total');
        $occupiedCount = \App\Models\DiningTable::where('status', 'occupied')->count();
        $completedOrdersCount = \App\Models\Check::where('status', 'closed')->count();
        $activeWaitersCount = \App\Models\StaffProfile::where('is_active', true)->count();

        $stats = [
            'total_sales' => '₺' . number_format($openRevenue, 2),
            'open_tables' => $occupiedCount,
            'completed_orders' => $completedOrdersCount,
            'active_waiters' => $activeWaitersCount,
        ];

        $tables = \App\Models\DiningTable::with(['hall', 'checks' => fn($q) => $q->whereIn('status', ['open', 'awaiting_payment'])])
            ->orderBy('hall_id')
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $check = $t->checks->first();
                return [
                    'name' => $t->name . ($t->hall ? ' (' . $t->hall->name . ')' : ''),
                    'status' => $t->status === 'occupied' ? 'busy' : ($t->status === 'reserved' ? 'reserved' : 'free'),
                    'total' => '₺' . number_format($check ? $check->total : 0, 2),
                    'time' => $check ? $check->created_at->diffForHumans() : '-',
                ];
            });

        return view('dashboard', compact('user', 'stats', 'tables', 'staffRole', 'allowedCategories'));
    }
}
