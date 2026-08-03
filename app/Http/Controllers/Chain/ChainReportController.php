<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChainInventoryMovement;
use App\Models\ChainMenuProduct;
use App\Models\Check;
use App\Models\CheckItem;
use App\Models\DeliveryOrder;
use App\Models\DiningTable;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkflow;
use App\Models\ProductionWorkflowItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChainReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'integer'],
            'module' => ['nullable', Rule::in(['overview', 'tables', 'quick_sale', 'delivery', 'kitchen', 'products', 'stocks', 'production', 'purchasing'])],
        ]);
        $accessibleIds = Auth::user()->accessibleChainBranchIds();
        $selectedBranchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        abort_if($selectedBranchId && ! in_array($selectedBranchId, $accessibleIds, true), 403);

        $branchIds = $selectedBranchId ? [$selectedBranchId] : $accessibleIds;
        $startDate = Carbon::parse($validated['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $endDate = Carbon::parse($validated['end_date'] ?? now()->toDateString())->endOfDay();
        $activeModule = $validated['module'] ?? 'overview';
        $organizationId = (int) Auth::user()->organization_id;

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

        $tableStatuses = DiningTable::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->where('is_active', true)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $tableChecks = (clone $closed)->whereNotNull('dining_table_id');
        $tableSummary = (clone $tableChecks)->selectRaw('COUNT(*) as check_count, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as average, COALESCE(SUM(guest_count),0) as guests')->first();
        $tableSummary->total_tables = $tableStatuses->sum();
        $tableSummary->occupied = (int) ($tableStatuses['occupied'] ?? 0);
        $tableSummary->awaiting_payment = (int) ($tableStatuses['awaiting_payment'] ?? 0);
        $tableSummary->available = (int) ($tableStatuses['available'] ?? 0);
        $busiestTables = Check::withoutGlobalScopes()->join('dining_tables', 'dining_tables.id', '=', 'checks.dining_table_id')
            ->join('branches', 'branches.id', '=', 'checks.branch_id')->whereIn('checks.branch_id', $branchIds)
            ->where('checks.status', 'closed')->whereBetween('checks.closed_at', [$startDate, $endDate])
            ->selectRaw('checks.branch_id, branches.name as branch_name, dining_tables.name as table_name, COUNT(*) as check_count, COALESCE(SUM(checks.total),0) as revenue, COALESCE(SUM(checks.guest_count),0) as guests')
            ->groupBy('checks.branch_id', 'branches.name', 'dining_tables.id', 'dining_tables.name')->orderByDesc('revenue')->limit(15)->get();

        $quickSales = (clone $closed)->whereNull('dining_table_id');
        $quickSaleSummary = (clone $quickSales)->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as average, COALESCE(SUM(discount_total),0) as discounts')->first();
        $quickSalesByBranch = (clone $quickSales)->selectRaw('branch_id, COUNT(*) as sale_count, COALESCE(SUM(total),0) as revenue, COALESCE(AVG(total),0) as average')->groupBy('branch_id')->get()->keyBy('branch_id');
        $quickPaymentBreakdown = Payment::withoutGlobalScopes()->join('checks', 'checks.id', '=', 'payments.check_id')
            ->whereIn('checks.branch_id', $branchIds)->whereNull('checks.dining_table_id')->where('checks.status', 'closed')
            ->whereBetween('checks.closed_at', [$startDate, $endDate])->selectRaw('payments.payment_method, COALESCE(SUM(payments.amount),0) as total')
            ->groupBy('payments.payment_method')->get();

        $deliveryBase = DeliveryOrder::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereBetween('received_at', [$startDate, $endDate]);
        $deliverySummary = (clone $deliveryBase)->selectRaw("COUNT(*) as order_count, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count, SUM(CASE WHEN status IN ('new','preparing','on_the_way') THEN 1 ELSE 0 END) as active_count, COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END),0) as revenue, COALESCE(AVG(CASE WHEN status = 'delivered' THEN total END),0) as average")->first();
        $deliveryChannels = (clone $deliveryBase)->selectRaw("channel, COUNT(*) as order_count, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count, COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END),0) as revenue")
            ->groupBy('channel')->orderByDesc('revenue')->get();
        $deliveryDurations = (clone $deliveryBase)->where('status', 'delivered')->whereNotNull('delivered_at')->get(['received_at', 'delivered_at']);
        $deliverySummary->average_minutes = $deliveryDurations->isEmpty() ? null : round($deliveryDurations->avg(fn ($order) => $order->received_at->diffInMinutes($order->delivered_at)), 1);

        $kitchenBase = CheckItem::withoutGlobalScopes()->join('checks', 'checks.id', '=', 'check_items.check_id')
            ->whereIn('checks.branch_id', $branchIds)->whereBetween('check_items.created_at', [$startDate, $endDate]);
        $kitchenSummary = (clone $kitchenBase)->selectRaw("COUNT(*) as item_count, SUM(CASE WHEN check_items.is_cancelled = 1 OR check_items.kitchen_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count, SUM(CASE WHEN check_items.kitchen_status = 'preparing' THEN 1 ELSE 0 END) as preparing_count, SUM(CASE WHEN check_items.kitchen_status IN ('delivered','ready','served') THEN 1 ELSE 0 END) as completed_count, SUM(CASE WHEN check_items.kitchen_status IN ('received','sent','pending') OR check_items.kitchen_status IS NULL THEN 1 ELSE 0 END) as waiting_count")->first();
        $completedKitchen = (clone $kitchenBase)->whereIn('check_items.kitchen_status', ['delivered', 'ready', 'served'])->whereNotNull('check_items.sent_to_kitchen_at')->get(['check_items.sent_to_kitchen_at', 'check_items.updated_at']);
        $kitchenSummary->average_minutes = $completedKitchen->isEmpty() ? null : round($completedKitchen->avg(fn ($item) => Carbon::parse($item->sent_to_kitchen_at)->diffInMinutes(Carbon::parse($item->updated_at))), 1);
        $kitchenProducts = (clone $kitchenBase)->where('check_items.is_cancelled', false)->selectRaw('check_items.product_name, SUM(check_items.quantity) as quantity, COUNT(DISTINCT check_items.check_id) as order_count')
            ->groupBy('check_items.product_name')->orderByDesc('quantity')->limit(15)->get();

        $productExceptions = (clone $kitchenBase)->selectRaw('SUM(CASE WHEN check_items.is_cancelled = 1 THEN check_items.quantity ELSE 0 END) as cancelled_quantity, SUM(CASE WHEN check_items.is_complimentary = 1 THEN check_items.quantity ELSE 0 END) as complimentary_quantity, COALESCE(SUM(CASE WHEN check_items.is_complimentary = 1 THEN check_items.total_price ELSE 0 END),0) as complimentary_value')->first();

        $stockSummary = Product::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->where('track_stock', true)
            ->selectRaw('COUNT(*) as product_count, SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock, SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock, COALESCE(SUM(stock_quantity),0) as total_quantity')->first();
        $criticalStocks = Product::withoutGlobalScopes()->with('branch')->whereIn('branch_id', $branchIds)->where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'min_stock_level')->orderBy('stock_quantity')->limit(20)->get();
        $stockMovements = StockMovement::withoutGlobalScopes()->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->whereIn('products.branch_id', $branchIds)->whereBetween('stock_movements.created_at', [$startDate, $endDate])
            ->selectRaw('stock_movements.type, COUNT(*) as movement_count, COALESCE(SUM(stock_movements.quantity),0) as quantity')
            ->groupBy('stock_movements.type')->orderByDesc('movement_count')->get();

        $centralStockBase = ChainMenuProduct::where('organization_id', $organizationId)->where('item_type', 'raw_material')->where('track_stock', true);
        $centralStockSummary = (clone $centralStockBase)
            ->selectRaw('COUNT(*) as product_count, SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock, SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as low_stock, SUM(CASE WHEN stock_quantity <= min_stock_level THEN 1 ELSE 0 END) as critical_count')->first();
        $centralUnitTotals = (clone $centralStockBase)->selectRaw('unit, COUNT(*) as product_count, COALESCE(SUM(stock_quantity),0) as stock_quantity')
            ->groupBy('unit')->orderBy('unit')->get();
        $centralCriticalStocks = (clone $centralStockBase)->with('category')->whereColumn('stock_quantity', '<=', 'min_stock_level')
            ->orderBy('stock_quantity')->limit(20)->get();
        $centralMovementBase = ChainInventoryMovement::where('organization_id', $organizationId)->whereBetween('created_at', [$startDate, $endDate]);
        $centralStockMovements = (clone $centralMovementBase)
            ->selectRaw('type, unit, COUNT(*) as movement_count, COALESCE(SUM(quantity),0) as quantity')
            ->groupBy('type', 'unit')->orderByDesc('movement_count')->get();
        $centralDistributionByBranch = ChainInventoryMovement::join('branches', 'branches.id', '=', 'chain_inventory_movements.branch_id')
            ->where('chain_inventory_movements.organization_id', $organizationId)->where('chain_inventory_movements.type', 'distribution_out')
            ->whereIn('chain_inventory_movements.branch_id', $branchIds)->whereBetween('chain_inventory_movements.created_at', [$startDate, $endDate])
            ->selectRaw('branches.name as branch_name, chain_inventory_movements.unit, COUNT(*) as movement_count, COALESCE(SUM(chain_inventory_movements.quantity),0) as quantity')
            ->groupBy('branches.id', 'branches.name', 'chain_inventory_movements.unit')->orderBy('branches.name')->get();

        $activeRecipes = ProductionRecipe::withoutGlobalScopes()->with(['branch', 'outputProduct'])->withCount('items')
            ->whereIn('branch_id', $branchIds)->where('is_active', true)->latest()->get();
        $workflowPeriodBase = ProductionWorkflow::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereBetween('created_at', [$startDate, $endDate]);
        $productionSummary = (clone $workflowPeriodBase)->selectRaw("COUNT(*) as workflow_count, SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned_count, SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count, COALESCE(SUM(planned_servings),0) as planned_servings")->first();
        $productionSummary->active_recipes = $activeRecipes->count();
        $completedWorkflowBase = ProductionWorkflow::withoutGlobalScopes()->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')->whereBetween('completed_at', [$startDate, $endDate]);
        $productionSummary->completed_servings = (float) (clone $completedWorkflowBase)->sum('planned_servings');
        $productionConsumptionByUnit = ProductionWorkflowItem::join('production_workflows', 'production_workflows.id', '=', 'production_workflow_items.production_workflow_id')
            ->whereIn('production_workflows.branch_id', $branchIds)->where('production_workflows.status', 'completed')
            ->whereBetween('production_workflows.completed_at', [$startDate, $endDate])
            ->selectRaw('production_workflow_items.stock_unit as unit, COALESCE(SUM(production_workflow_items.consumed_quantity),0) as quantity')
            ->groupBy('production_workflow_items.stock_unit')->orderBy('production_workflow_items.stock_unit')->get();
        $productionOutputs = ProductionWorkflow::withoutGlobalScopes()->join('branches', 'branches.id', '=', 'production_workflows.branch_id')
            ->whereIn('production_workflows.branch_id', $branchIds)->where('production_workflows.status', 'completed')
            ->whereBetween('production_workflows.completed_at', [$startDate, $endDate])
            ->selectRaw('production_workflows.recipe_name, branches.name as branch_name, COUNT(*) as workflow_count, COALESCE(SUM(production_workflows.planned_servings),0) as servings')
            ->groupBy('production_workflows.recipe_name', 'branches.id', 'branches.name')->orderByDesc('servings')->limit(15)->get();
        $recentWorkflows = ProductionWorkflow::withoutGlobalScopes()->with(['branch', 'recipe.outputProduct'])
            ->whereIn('branch_id', $branchIds)->whereBetween('created_at', [$startDate, $endDate])->latest()->limit(20)->get();

        $purchaseBase = PurchaseOrder::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereBetween('order_date', [$startDate->toDateString(), $endDate->toDateString()]);
        $purchaseSummary = (clone $purchaseBase)->selectRaw("COUNT(*) as order_count, SUM(CASE WHEN status IN ('draft','ordered','partial') THEN 1 ELSE 0 END) as open_count, SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_count, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count, COALESCE(SUM(total),0) as ordered_value")->first();
        $purchaseSummary->received_value = (float) PurchaseReceipt::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereBetween('received_at', [$startDate, $endDate])->sum('received_value');
        $purchaseSummary->active_suppliers = Supplier::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->where('is_active', true)->count();
        $supplierPerformance = PurchaseOrder::withoutGlobalScopes()->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->join('branches', 'branches.id', '=', 'purchase_orders.branch_id')->whereIn('purchase_orders.branch_id', $branchIds)
            ->whereBetween('purchase_orders.order_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('suppliers.name as supplier_name, branches.name as branch_name, COUNT(*) as order_count, COALESCE(SUM(purchase_orders.total),0) as total, SUM(CASE WHEN purchase_orders.status = \'received\' THEN 1 ELSE 0 END) as received_count')
            ->groupBy('suppliers.id', 'suppliers.name', 'branches.id', 'branches.name')->orderByDesc('total')->limit(20)->get();

        return view('chain.reports.index', compact(
            'activeModule', 'summary', 'salesByBranch', 'dailySales', 'paymentBreakdown', 'productPerformance', 'productExceptions',
            'tableSummary', 'busiestTables', 'quickSaleSummary', 'quickSalesByBranch', 'quickPaymentBreakdown',
            'deliverySummary', 'deliveryChannels', 'kitchenSummary', 'kitchenProducts', 'stockSummary', 'criticalStocks',
            'stockMovements', 'centralStockSummary', 'centralUnitTotals', 'centralCriticalStocks', 'centralStockMovements',
            'centralDistributionByBranch', 'productionSummary', 'productionConsumptionByUnit', 'productionOutputs',
            'activeRecipes', 'recentWorkflows', 'purchaseSummary', 'supplierPerformance', 'branches', 'selectedBranchId', 'startDate', 'endDate'
        ));
    }
}
