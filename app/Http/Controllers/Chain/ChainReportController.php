<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\Payment;
use App\Models\PurchaseOrderItem;
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
        $salesByBranch = (clone $closed)->selectRaw('branch_id, COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as average, COALESCE(SUM(discount_total),0) as discounts, COALESCE(SUM(guest_count),0) as guests')->groupBy('branch_id')->get()->keyBy('branch_id');
        $dailySales = (clone $closed)->selectRaw('DATE(closed_at) as sale_date, COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue')->groupByRaw('DATE(closed_at)')->orderBy('sale_date')->get();
        $paymentBreakdown = Payment::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$startDate, $endDate])->selectRaw('payment_method, COALESCE(SUM(amount),0) as total')->groupBy('payment_method')->get();
        $branches = Branch::whereIn('id', $accessibleIds)->orderBy('name')->get();

        $periodDays = max(1, $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1);
        $previousEnd = $startDate->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subDays($periodDays)->addSecond();
        $previousRevenue = (float) Check::withoutGlobalScope('authenticated_branch')->whereIn('branch_id', $branchIds)
            ->where('status', 'closed')->whereBetween('closed_at', [$previousStart, $previousEnd])->sum('total');
        $summary->revenue_change = $previousRevenue > 0 ? (((float) $summary->revenue - $previousRevenue) / $previousRevenue) * 100 : null;

        $soldItems = CheckItem::withoutGlobalScopes()->join('checks', 'checks.id', '=', 'check_items.check_id')
            ->whereIn('checks.branch_id', $branchIds)->where('checks.status', 'closed')->whereBetween('checks.closed_at', [$startDate, $endDate])
            ->where('check_items.is_cancelled', false)->where('check_items.is_complimentary', false)
            ->selectRaw('checks.branch_id, check_items.product_id, check_items.product_name, SUM(check_items.quantity) as quantity, SUM(check_items.total_price) as revenue')
            ->groupBy('checks.branch_id', 'check_items.product_id', 'check_items.product_name')->get();
        $latestCosts = PurchaseOrderItem::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereNotNull('product_id')->where('received_quantity', '>', 0)
            ->orderByDesc('id')->get()->unique(fn ($item) => $item->branch_id.'-'.$item->product_id)
            ->mapWithKeys(fn ($item) => [$item->branch_id.'-'.$item->product_id => (float) $item->unit_price]);
        $productPerformance = $soldItems->map(function ($item) use ($latestCosts) {
            $cost = $item->product_id ? $latestCosts->get($item->branch_id.'-'.$item->product_id) : null;
            $item->unit_cost = $cost; $item->estimated_cost = $cost === null ? null : (float) $item->quantity * $cost;
            $item->estimated_profit = $item->estimated_cost === null ? null : (float) $item->revenue - $item->estimated_cost;
            return $item;
        })->sortByDesc('revenue')->values();
        $knownCostItems = $productPerformance->whereNotNull('estimated_cost');
        $summary->estimated_cost = $knownCostItems->sum('estimated_cost');
        $summary->estimated_profit = $knownCostItems->sum('estimated_profit');
        $summary->margin = $knownCostItems->sum('revenue') > 0 ? $summary->estimated_profit / $knownCostItems->sum('revenue') * 100 : null;
        $summary->missing_cost_products = $productPerformance->whereNull('unit_cost')->count();
        foreach ($salesByBranch as $branchId => $sale) {
            $branchItems = $productPerformance->where('branch_id', $branchId)->whereNotNull('estimated_cost');
            $sale->estimated_profit = $branchItems->sum('estimated_profit');
        }

        return view('chain.reports.index', compact('summary', 'salesByBranch', 'dailySales', 'paymentBreakdown', 'productPerformance', 'branches', 'selectedBranchId', 'startDate', 'endDate'));
    }
}
