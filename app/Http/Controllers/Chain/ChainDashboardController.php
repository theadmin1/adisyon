<?php

namespace App\Http\Controllers\Chain;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Check;
use App\Models\Device;
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

        return view('chain.dashboard', compact(
            'branches', 'todaySales', 'todayChecks', 'openChecks', 'onlineDevices', 'salesByBranch'
        ));
    }
}
