<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChainPurchasingController extends Controller
{
    public function index(): View
    {
        $branchIds = Auth::user()->accessibleChainBranchIds();
        $branches = Branch::whereIn('id', $branchIds)->orderBy('name')->get();
        $suppliers = Supplier::withoutGlobalScopes()->with('branch')->whereIn('branch_id', $branchIds)->orderBy('name')->get();
        $products = Product::withoutGlobalScopes()->with('branch')->whereIn('branch_id', $branchIds)->where('is_active', true)->orderBy('name')->get();
        $orders = PurchaseOrder::withoutGlobalScopes()->with(['branch', 'supplier', 'items'])
            ->whereIn('branch_id', $branchIds)->latest('order_date')->latest('id')->paginate(20);
        $stats = [
            'suppliers' => $suppliers->where('is_active', true)->count(),
            'open' => PurchaseOrder::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereIn('status', ['draft', 'ordered', 'partial'])->count(),
            'pending' => PurchaseOrder::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->whereIn('status', ['ordered', 'partial'])->sum('total'),
            'received' => PurchaseOrder::withoutGlobalScopes()->whereIn('branch_id', $branchIds)->where('status', 'received')->sum('total'),
        ];
        $canManage = Auth::user()->chain_role !== 'analyst';

        return view('chain.purchasing.index', compact('branches', 'suppliers', 'products', 'orders', 'stats', 'canManage'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->authorizeMutation();
        $branchId = (int) $request->input('branch_id');
        $this->authorizeBranch($branchId);
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->where(fn ($query) => $query->where('branch_id', $branchId))],
            'contact_person' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        Supplier::withoutGlobalScopes()->create($validated + ['is_active' => true]);

        return back()->with('success', 'Tedarikçi şubeye tanımlandı.');
    }

    public function storeOrder(Request $request, PurchasingService $service): RedirectResponse
    {
        $this->authorizeMutation();
        $branchId = (int) $request->input('branch_id');
        $this->authorizeBranch($branchId);
        $validated = $request->validate([
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'], 'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'], 'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $supplier = Supplier::withoutGlobalScopes()->where('branch_id', $branchId)->where('is_active', true)->findOrFail($validated['supplier_id']);
        $order = $service->createOrder(Auth::user(), $supplier, $validated['items'], $validated['order_date'], $validated['expected_delivery_date'] ?? null, $validated['notes'] ?? null, $branchId);

        return back()->with('success', "{$order->order_number} siparişi oluşturuldu.");
    }

    public function place(int $order, PurchasingService $service): RedirectResponse
    {
        $this->authorizeMutation(); $purchaseOrder = $this->order($order); $service->placeOrder($purchaseOrder);
        return back()->with('success', 'Sipariş onaylandı ve mal kabulüne açıldı.');
    }

    public function receive(Request $request, int $order, PurchasingService $service): RedirectResponse
    {
        $this->authorizeMutation(); $purchaseOrder = $this->order($order);
        $validated = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0'], 'supplier_invoice_number' => ['nullable', 'string', 'max:100']]);
        $service->receive($purchaseOrder, Auth::user(), $validated['quantities'], $validated['supplier_invoice_number'] ?? null, null, null);
        return back()->with('success', 'Mal kabulü tamamlandı ve şube stoğu güncellendi.');
    }

    public function cancel(int $order, PurchasingService $service): RedirectResponse
    {
        $this->authorizeMutation(); $service->cancel($this->order($order));
        return back()->with('success', 'Sipariş iptal edildi.');
    }

    private function order(int $id): PurchaseOrder
    {
        return PurchaseOrder::withoutGlobalScopes()->whereIn('branch_id', Auth::user()->accessibleChainBranchIds())->findOrFail($id);
    }

    private function authorizeBranch(int $branchId): void
    {
        abort_unless(in_array($branchId, Auth::user()->accessibleChainBranchIds(), true), 403);
    }

    private function authorizeMutation(): void
    {
        abort_if(Auth::user()->chain_role === 'analyst', 403);
    }
}
