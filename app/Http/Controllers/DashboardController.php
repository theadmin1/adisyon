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

        // Örnek Adisyon İstatistikleri
        $stats = [
            'total_sales' => '₺14,850.00',
            'open_tables' => 12,
            'completed_orders' => 84,
            'active_waiters' => 5,
        ];

        $tables = [
            ['name' => 'Masa 1', 'status' => 'busy', 'total' => '₺450.00', 'time' => '35 dk'],
            ['name' => 'Masa 2', 'status' => 'free', 'total' => '₺0.00', 'time' => '-'],
            ['name' => 'Masa 3', 'status' => 'busy', 'total' => '₺1,280.00', 'time' => '1 saat 10 dk'],
            ['name' => 'Masa 4', 'status' => 'reserved', 'total' => '₺0.00', 'time' => '20:00'],
            ['name' => 'Bahçe 1', 'status' => 'busy', 'total' => '₺820.00', 'time' => '45 dk'],
            ['name' => 'VIP Salon', 'status' => 'busy', 'total' => '₺3,400.00', 'time' => '2 saat'],
        ];

        return view('dashboard', compact('user', 'stats', 'tables', 'staffRole', 'allowedCategories'));
    }
}
