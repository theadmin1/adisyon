<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashShift;
use App\Models\Check;
use App\Models\Device;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChainBranchController extends Controller
{
    public function index(): View
    {
        $branchIds = Auth::user()->accessibleChainBranchIds();
        $branches = Branch::whereIn('id', $branchIds)
            ->withCount(['devices', 'staffProfiles', 'products'])
            ->orderBy('name')
            ->get();

        $openChecks = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)->whereIn('status', ['open', 'awaiting_payment'])
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $todaySales = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)->where('status', 'closed')->whereDate('closed_at', today())
            ->selectRaw('branch_id, COALESCE(SUM(total), 0) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $onlineDevices = Device::whereIn('branch_id', $branchIds)->where('last_ping_at', '>=', now()->subMinutes(2))
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $lowStocks = Product::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)
            ->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
        $openShifts = CashShift::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)->where('status', 'open')
            ->selectRaw('branch_id, COUNT(*) as total')->groupBy('branch_id')->pluck('total', 'branch_id');

        return view('chain.branches.index', compact('branches', 'openChecks', 'todaySales', 'onlineDevices', 'lowStocks', 'openShifts'));
    }
}
