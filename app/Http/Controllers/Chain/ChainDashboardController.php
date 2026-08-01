<?php

namespace App\Http\Controllers\Chain;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Check;
use App\Models\Device;
use App\Models\DeliveryOrder;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChainDashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $branchIds = $user->accessibleChainBranchIds();

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->withCount([
                'devices',
                'devices as online_devices_count' => fn ($query) => $query->where('last_ping_at', '>=', now()->subMinutes(2)),
            ])
            ->orderBy('name')
            ->get();

        $todaySales = (float) Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('closed_at')
            ->whereDate('closed_at', today())
            ->sum('total');

        $todayChecks = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('closed_at')
            ->whereDate('closed_at', today())
            ->count();

        $openChecks = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)
            ->where('status', CheckStatus::Open->value)
            ->count();

        $onlineDevices = Device::whereIn('branch_id', $branchIds)
            ->where('last_ping_at', '>=', now()->subMinutes(2))
            ->count();

        $salesByBranch = Check::withoutGlobalScope('authenticated_branch')
            ->selectRaw('branch_id, COUNT(*) as check_count, COALESCE(SUM(total), 0) as sales_total')
            ->whereIn('branch_id', $branchIds)
            ->whereNotNull('closed_at')
            ->whereDate('closed_at', today())
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $dailyRows = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)->where('status', CheckStatus::Closed->value)
            ->whereBetween('closed_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->selectRaw('DATE(closed_at) as sale_date, COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue')
            ->groupByRaw('DATE(closed_at)')->get()->keyBy('sale_date');
        $dailySales = collect(range(6, 0))->map(function (int $daysAgo) use ($dailyRows): object {
            $date = now()->subDays($daysAgo);
            $row = $dailyRows->get($date->toDateString());
            return (object) ['date' => $date, 'revenue' => (float) ($row?->revenue ?? 0), 'checks' => (int) ($row?->check_count ?? 0)];
        });

        $paymentBreakdown = Payment::withoutGlobalScopes()->whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today())->selectRaw('payment_method, COALESCE(SUM(amount),0) as total')
            ->groupBy('payment_method')->orderByDesc('total')->get();
        $lowStockCount = Product::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_level')->count();
        $activeDeliveryCount = DeliveryOrder::withoutGlobalScopes()->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['new', 'preparing', 'on_the_way'])->count();
        $averageTicket = $todayChecks > 0 ? $todaySales / $todayChecks : 0;

        return view('chain.dashboard', compact(
            'branches', 'todaySales', 'todayChecks', 'openChecks', 'onlineDevices', 'salesByBranch',
            'dailySales', 'paymentBreakdown', 'lowStockCount', 'activeDeliveryCount', 'averageTicket'
        ));
    }
}
