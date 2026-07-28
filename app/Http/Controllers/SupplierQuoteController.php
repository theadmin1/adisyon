<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierQuoteRequest;
use App\Services\AuditLogger;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplierQuoteController extends Controller
{
    public function storeRequest(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')
                    ->where(fn ($query) => $query->where('branch_id', $branchId)->where('is_active', true)),
            ],
            'expires_in_days' => ['required', 'integer', 'between:1,30'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $token = Str::random(64);
        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $quoteRequest = SupplierQuoteRequest::create([
            'branch_id' => $branchId,
            'supplier_id' => $supplier->id,
            'requested_by_user_id' => $request->user()->id,
            'requested_by_staff_profile_id' => $this->staffId($request),
            'request_number' => 'TF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'token_hash' => hash('sha256', $token),
            'status' => 'open',
            'requested_by_name' => $this->actorName($request),
            'message' => $validated['message'] ?? null,
            'expires_at' => now()->addDays((int) $validated['expires_in_days']),
        ]);

        $auditLogger->record(
            'supplier_quote_request.created',
            $quoteRequest,
            newValues: [
                'supplier' => $supplier->name,
                'expires_at' => $quoteRequest->expires_at,
            ],
            description: 'Tedarikçi teklif bağlantısı oluşturuldu.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'quotes'])
            ->with('success', 'Tedarikçi teklif bağlantısı oluşturuldu.')
            ->with('generated_quote_url', route('supplier-quotes.public.show', $token))
            ->with('generated_quote_supplier', $supplier->name);
    }

    public function showPublic(string $token): Response
    {
        $quoteRequest = $this->findPublicQuote($token);

        if ($quoteRequest->status === 'open' && $quoteRequest->expires_at->isPast()) {
            $quoteRequest->update(['status' => 'expired']);
        }

        $quoteRequest->load(['supplier', 'branch', 'items.product']);
        $products = collect();
        $productOptions = [];
        $initialItems = old('items', []);

        if ($quoteRequest->status === 'open') {
            $products = Product::forBranch((int) $quoteRequest->branch_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
            $productOptions = $products
                ->map(static fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?: 'adet',
                ])
                ->values()
                ->all();
        }

        return response()
            ->view('purchasing.quote-public', compact('quoteRequest', 'products', 'productOptions', 'initialItems', 'token'))
            ->withHeaders([
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }

    public function submitPublic(Request $request, string $token): RedirectResponse
    {
        $quoteRequest = $this->findPublicQuote($token);
        if ($quoteRequest->status !== 'open' || $quoteRequest->expires_at->isPast()) {
            return redirect()
                ->route('supplier-quotes.public.show', $token)
                ->withErrors(['quote' => 'Bu teklif bağlantısı artık kullanılamıyor.']);
        }

        $branchId = (int) $quoteRequest->branch_id;
        $validated = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'supplier_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where(fn ($query) => $query->where('branch_id', $branchId)->where('is_active', true)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($quoteRequest, $validated, $request): void {
            $locked = SupplierQuoteRequest::withoutGlobalScope('authenticated_branch')
                ->whereKey($quoteRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'open' || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'quote' => 'Bu teklif bağlantısı daha önce kullanılmış veya süresi dolmuş.',
                ]);
            }

            $productIds = collect($validated['items'])->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $products = Product::forBranch((int) $locked->branch_id)
                ->where('is_active', true)
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== count(array_unique($productIds))) {
                throw ValidationException::withMessages([
                    'items' => 'Seçilen ürünlerden biri artık kullanılamıyor.',
                ]);
            }

            foreach ($validated['items'] as $item) {
                $product = $products->get((int) $item['product_id']);
                $quantity = round((float) $item['quantity'], 3);
                $unitPrice = round((float) $item['unit_price'], 4);
                $taxRate = round((float) ($item['tax_rate'] ?? 0), 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineTax = round($lineSubtotal * $taxRate / 100, 2);

                $locked->items()->create([
                    'branch_id' => $locked->branch_id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'unit' => $product->unit ?: 'adet',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_subtotal' => $lineSubtotal,
                    'line_tax' => $lineTax,
                    'line_total' => round($lineSubtotal + $lineTax, 2),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $locked->update([
                'status' => 'submitted',
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'] ?? null,
                'contact_phone' => $validated['contact_phone'] ?? null,
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'supplier_notes' => $validated['supplier_notes'] ?? null,
                'submitted_ip' => filter_var($request->ip(), FILTER_VALIDATE_IP) ? $request->ip() : null,
                'submitted_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'submitted_at' => now(),
            ]);
        });

        return redirect()
            ->route('supplier-quotes.public.show', $token)
            ->with('quote_submitted', true);
    }

    public function approve(
        Request $request,
        SupplierQuoteRequest $supplierQuoteRequest,
        PurchasingService $purchasingService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $validated = $request->validate([
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
        ]);

        [$quoteRequest, $order] = DB::transaction(function () use ($supplierQuoteRequest, $validated, $request, $purchasingService): array {
            $locked = SupplierQuoteRequest::query()
                ->whereKey($supplierQuoteRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'quote' => 'Yalnızca bekleyen bir tedarikçi teklifi onaylanabilir.',
                ]);
            }

            $locked->load(['supplier', 'items']);
            $items = $locked->items->map(static fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
            ])->all();
            $notes = collect([
                "Tedarikçi teklifi: {$locked->request_number}",
                $locked->supplier_notes,
            ])->filter()->implode(PHP_EOL);

            $order = $purchasingService->createOrder(
                $request->user(),
                $locked->supplier,
                $items,
                $validated['order_date'],
                $validated['expected_delivery_date'] ?? null,
                $notes,
            );

            $locked->update([
                'status' => 'approved',
                'purchase_order_id' => $order->id,
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_by_staff_profile_id' => $this->staffId($request),
                'reviewed_by_name' => $this->actorName($request),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            return [$locked->fresh(), $order];
        });

        $auditLogger->record(
            'supplier_quote_request.approved',
            $quoteRequest,
            oldValues: ['status' => 'submitted'],
            newValues: ['status' => 'approved', 'purchase_order_id' => $order->id],
            description: 'Tedarikçi teklifi onaylandı ve taslak satın alma siparişine dönüştürüldü.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.show', $order)
            ->with('success', 'Teklif onaylandı ve taslak satın alma siparişi oluşturuldu.');
    }

    public function reject(
        Request $request,
        SupplierQuoteRequest $supplierQuoteRequest,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($supplierQuoteRequest->status !== 'submitted') {
            throw ValidationException::withMessages([
                'quote' => 'Yalnızca bekleyen bir tedarikçi teklifi reddedilebilir.',
            ]);
        }

        $supplierQuoteRequest->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_by_staff_profile_id' => $this->staffId($request),
            'reviewed_by_name' => $this->actorName($request),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);
        $auditLogger->record(
            'supplier_quote_request.rejected',
            $supplierQuoteRequest,
            oldValues: ['status' => 'submitted'],
            newValues: ['status' => 'rejected', 'reason' => $validated['rejection_reason']],
            description: 'Tedarikçi teklifi reddedildi.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'quotes'])
            ->with('success', 'Teklif reddedildi.');
    }

    public function revoke(
        Request $request,
        SupplierQuoteRequest $supplierQuoteRequest,
        AuditLogger $auditLogger
    ): RedirectResponse {
        if ($supplierQuoteRequest->status !== 'open') {
            throw ValidationException::withMessages([
                'quote' => 'Yalnızca açık bir teklif bağlantısı iptal edilebilir.',
            ]);
        }

        $supplierQuoteRequest->update([
            'status' => 'revoked',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_by_staff_profile_id' => $this->staffId($request),
            'reviewed_by_name' => $this->actorName($request),
            'reviewed_at' => now(),
        ]);
        $auditLogger->record(
            'supplier_quote_request.revoked',
            $supplierQuoteRequest,
            oldValues: ['status' => 'open'],
            newValues: ['status' => 'revoked'],
            description: 'Tedarikçi teklif bağlantısı iptal edildi.',
            category: 'purchasing',
        );

        return redirect()
            ->route('purchasing.index', ['tab' => 'quotes'])
            ->with('success', 'Teklif bağlantısı iptal edildi.');
    }

    private function findPublicQuote(string $token): SupplierQuoteRequest
    {
        abort_unless(strlen($token) === 64, 404);

        return SupplierQuoteRequest::withoutGlobalScope('authenticated_branch')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }

    private function staffId(Request $request): ?int
    {
        $value = $request->session()->get('active_staff_id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function actorName(Request $request): string
    {
        return (string) ($request->session()->get('active_staff_name') ?: $request->user()->name);
    }
}
