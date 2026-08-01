<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Check;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChainReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'integer'],
        ]);
        $accessibleIds = Auth::user()->accessibleChainBranchIds();
        $selectedBranchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        abort_if($selectedBranchId && ! in_array($selectedBranchId, $accessibleIds, true), 403);

        $branchIds = $selectedBranchId ? [$selectedBranchId] : $accessibleIds;
        $startDate = Carbon::parse($validated['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $endDate = Carbon::parse($validated['end_date'] ?? now()->toDateString())->endOfDay();

        $closed = Check::withoutGlobalScope('authenticated_branch')
            ->whereIn('branch_id', $branchIds)->where('status', 'closed')->whereBetween('closed_at', [$startDate, $endDate]);
        $summary = (clone $closed)->selectRaw('COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as average, COALESCE(SUM(discount_total),0) as discounts')->first();
        $salesByBranch = (clone $closed)->selectRaw('branch_id, COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue')->groupBy('branch_id')->get()->keyBy('branch_id');
        $dailySales = (clone $closed)->selectRaw('DATE(closed_at) as sale_date, COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue')->groupByRaw('DATE(closed_at)')->orderBy('sale_date')->get();
        $paymentBreakdown = Payment::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$startDate, $endDate])->selectRaw('payment_method, COALESCE(SUM(amount),0) as total')->groupBy('payment_method')->get();
        $branches = Branch::whereIn('id', $accessibleIds)->orderBy('name')->get();

        return view('chain.reports.index', compact('summary', 'salesByBranch', 'dailySales', 'paymentBreakdown', 'branches', 'selectedBranchId', 'startDate', 'endDate'));
    }
}
