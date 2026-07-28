<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchasingController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'orders');
        $search = trim((string) $request->query('search'));
        $suppliers = Supplier::query()->orderBy('name')->get();
        $products = Product::query()->where('is_active', true)->orderBy('name')->get();
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('order_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"))))
            ->latest('order_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'active_suppliers' => Supplier::where('is_active', true)->count(),
            'open_orders' => PurchaseOrder::whereIn('status', ['draft', 'ordered', 'partial'])->count(),
            'pending_value' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->sum('total'),
            'received_value' => PurchaseOrder::where('status', 'received')->sum('total'),
        ];

        return view('purchasing.index', compact('tab', 'search', 'suppliers', 'products', 'orders', 'stats'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'items.product', 'receipts.items.product']);

        return view('purchasing.show', compact('purchaseOrder'));
    }

    public function storeSupplier(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $supplier = Supplier::create($validated + ['branch_id' => $branchId, 'is_active' => true]);
        $auditLogger->record('supplier.created', $supplier, newValues: $validated, description: 'Tedarikçi kartı oluşturuldu.', category: 'purchasing');

        return back()->with('success', 'Tedarikçi oluşturuldu.');
    }

    public function updateSupplier(Request $request, Supplier $supplier, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->where(fn ($q) => $q->where('branch_id', $request->user()->branch_id))->ignore($supplier->id)],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $old = $supplier->only(array_keys($validated));
        $supplier->update($validated);
        $auditLogger->record('supplier.updated', $supplier, $old, $validated, 'Tedarikçi kartı güncellendi.', 'purchasing');

        return back()->with('success', 'Tedarikçi güncellendi.');
    }

    public function toggleSupplier(Supplier $supplier, AuditLogger $auditLogger): RedirectResponse
    {
        $old = $supplier->is_active;
        $supplier->update(['is_active' => ! $old]);
        $auditLogger->record('supplier.status_changed', $supplier, ['is_active' => $old], ['is_active' => $supplier->is_active], 'Tedarikçi durumu değiştirildi.', 'purchasing');

        return back()->with('success', 'Tedarikçi durumu güncellendi.');
    }

    public function storeOrder(Request $request, PurchasingService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $validated = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where(fn ($q) => $q->where('branch_id', $branchId)->where('is_active', true))],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where(fn ($q) => $q->where('branch_id', $branchId))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $order = $service->createOrder($request->user(), $supplier, $validated['items'], $validated['order_date'], $validated['expected_delivery_date'] ?? null, $validated['notes'] ?? null);
        $auditLogger->record('purchase_order.created', $order, newValues: ['supplier' => $supplier->name, 'total' => $order->total, 'item_count' => count($validated['items'])], description: 'Satın alma siparişi oluşturuldu.', category: 'purchasing');

        return redirect()->route('purchasing.show', $order)->with('success', 'Satın alma siparişi oluşturuldu.');
    }

    public function placeOrder(PurchaseOrder $purchaseOrder, PurchasingService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $order = $service->placeOrder($purchaseOrder);
        $auditLogger->record('purchase_order.placed', $order, ['status' => 'draft'], ['status' => 'ordered'], 'Satın alma siparişi onaylandı.', 'purchasing');

        return back()->with('success', 'Sipariş onaylandı ve mal kabulüne açıldı.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchasingService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $receipt = $service->receive($purchaseOrder, $request->user(), $validated['quantities'], $validated['supplier_invoice_number'] ?? null, $validated['supplier_invoice_date'] ?? null, $validated['notes'] ?? null);
        $auditLogger->record('purchase_order.received', $purchaseOrder, newValues: ['receipt_number' => $receipt->receipt_number, 'received_value' => $receipt->received_value, 'status' => $purchaseOrder->fresh()->status], description: 'Satın alma mal kabulü yapıldı ve stoklar güncellendi.', category: 'purchasing');

        return back()->with('success', "Mal kabulü tamamlandı: {$receipt->receipt_number}");
    }

    public function cancel(PurchaseOrder $purchaseOrder, PurchasingService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $oldStatus = $purchaseOrder->status;
        $order = $service->cancel($purchaseOrder);
        $auditLogger->record('purchase_order.cancelled', $order, ['status' => $oldStatus], ['status' => 'cancelled'], 'Satın alma siparişi iptal edildi.', 'purchasing');

        return back()->with('success', 'Satın alma siparişi iptal edildi.');
    }
}
