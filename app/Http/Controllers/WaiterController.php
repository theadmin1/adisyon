<?php

namespace App\Http\Controllers;

use App\Enums\CheckStatus;
use App\Enums\TableStatus;
use App\Models\Category;
use App\Models\Check;
use App\Models\Product;
use App\Models\StaffProfile;
use App\Services\AuditLogger;
use App\Services\AutoSyncService;
use App\Services\Checks\CheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WaiterController extends Controller
{
    public function index(Request $request): View
    {
        return $this->render($request);
    }

    public function show(Request $request, Check $check): View
    {
        $this->ensureActive($check);

        return $this->render($request, $check);
    }

    public function addItems(Request $request, Check $check, CheckService $checkService, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureActive($check);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $productIds = collect($validated['items'])->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values();
        $validProductIds = Product::query()
            ->where('branch_id', $check->branch_id)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->pluck('id');

        if ($validProductIds->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Seçilen ürünlerden biri bu şubede satışa açık değil.',
            ]);
        }

        $staff = $this->activeStaff($request);

        if ($check->status === CheckStatus::AwaitingPayment) {
            $check->update([
                'status' => CheckStatus::Open,
                'is_synced' => config('database.default') === 'mysql',
            ]);
            $check->diningTable?->update(['status' => TableStatus::Occupied]);
        }

        if ($staff && ! $check->waiter_staff_profile_id) {
            $check->update([
                'waiter_staff_profile_id' => $staff->id,
                'waiter_name' => $staff->name,
                'is_synced' => config('database.default') === 'mysql',
            ]);
        }

        $updatedCheck = $checkService->addItems($check, $validated['items'], $staff);
        $auditLogger->record(
            'waiter.check_items_added',
            $updatedCheck,
            [],
            ['item_count' => count($validated['items']), 'waiter' => $staff?->name],
            'Garson adisyona ürün ekledi.',
            'waiter'
        );

        return redirect()
            ->route('waiter.checks.show', ['check' => $updatedCheck, 'scope' => $request->input('scope', 'all')])
            ->with('status', 'Ürünler adisyona eklendi. Yeni ürünleri mutfağa gönderebilirsiniz.');
    }

    public function updateCustomerNotes(Request $request, Check $check, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureActive($check);
        $validated = $request->validate(['customer_notes' => ['nullable', 'string', 'max:1000']]);
        $oldNotes = $check->customer_notes;
        $check->update([
            'customer_notes' => $validated['customer_notes'] ?? null,
            'is_synced' => config('database.default') === 'mysql',
        ]);

        $auditLogger->record(
            'waiter.customer_notes_updated',
            $check,
            ['customer_notes' => $oldNotes],
            ['customer_notes' => $check->customer_notes],
            'Müşteri/adisyon notu güncellendi.',
            'waiter'
        );
        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Müşteri notu kaydedildi.');
    }

    public function requestPayment(Check $check, AuditLogger $auditLogger): RedirectResponse
    {
        $this->ensureActive($check);

        if (! $check->items()->where('is_cancelled', false)->exists()) {
            return back()->withErrors(['check' => 'Ürün bulunmayan bir adisyon için hesap istenemez.']);
        }

        $check->update([
            'status' => CheckStatus::AwaitingPayment,
            'is_synced' => config('database.default') === 'mysql',
        ]);
        $check->diningTable?->update(['status' => TableStatus::AwaitingPayment]);
        $auditLogger->record(
            'waiter.payment_requested',
            $check,
            ['status' => CheckStatus::Open->value],
            ['status' => CheckStatus::AwaitingPayment->value],
            'Garson hesabı kasaya gönderdi.',
            'waiter'
        );
        AutoSyncService::syncIfLocal();

        return back()->with('status', 'Hesap isteği kasaya iletildi.');
    }

    private function render(Request $request, ?Check $selectedCheck = null): View
    {
        $staff = $this->activeStaff($request);
        $scope = $request->query('scope') === 'mine' ? 'mine' : 'all';
        $search = trim((string) $request->query('search'));
        $checksQuery = Check::query()
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value])
            ->with(['diningTable.hall', 'waiterStaffProfile'])
            ->withCount(['items as active_items_count' => fn ($query) => $query->where('is_cancelled', false)])
            ->latest('opened_at');

        if ($scope === 'mine') {
            $checksQuery->where('waiter_staff_profile_id', $staff?->id ?? 0);
        }

        if ($search !== '') {
            $checksQuery->where(function ($query) use ($search) {
                $query->where('check_number', 'like', "%{$search}%")
                    ->orWhere('waiter_name', 'like', "%{$search}%")
                    ->orWhereHas('diningTable', fn ($table) => $table->where('name', 'like', "%{$search}%"));
            });
        }

        $checks = $checksQuery->get();

        if ($selectedCheck) {
            $selectedCheck->load([
                'diningTable.hall',
                'waiterStaffProfile',
                'items' => fn ($query) => $query
                    ->where('is_cancelled', false)
                    ->with(['product.category', 'addedByStaffProfile'])
                    ->oldest('id'),
            ]);
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->with(['products' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $productOptions = $categories->flatMap(fn (Category $category) => $category->products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $category->name,
            'price' => $product->effective_price,
            'unit' => $product->unit,
            'track_stock' => $product->track_stock,
            'stock' => (float) $product->stock_quantity,
        ]))->values();

        $baseStatsQuery = Check::query()
            ->whereIn('status', [CheckStatus::Open->value, CheckStatus::AwaitingPayment->value]);
        $stats = [
            'open' => (clone $baseStatsQuery)->where('status', CheckStatus::Open->value)->count(),
            'awaiting_payment' => (clone $baseStatsQuery)->where('status', CheckStatus::AwaitingPayment->value)->count(),
            'mine' => $staff ? (clone $baseStatsQuery)->where('waiter_staff_profile_id', $staff->id)->count() : 0,
            'unsent' => $selectedCheck ? $selectedCheck->items
                ->whereNull('sent_to_kitchen_at')
                ->filter(fn ($item) => ! $item->product_id || $item->product?->send_to_kitchen)
                ->count() : 0,
        ];

        return view('waiter.index', compact(
            'categories',
            'checks',
            'productOptions',
            'scope',
            'search',
            'selectedCheck',
            'staff',
            'stats'
        ));
    }

    private function ensureActive(Check $check): void
    {
        abort_unless(
            in_array($check->status, [CheckStatus::Open, CheckStatus::AwaitingPayment], true),
            409,
            'Bu adisyon artık işlem yapılabilir durumda değil.'
        );
    }

    private function activeStaff(Request $request): ?StaffProfile
    {
        $staffId = $request->session()->get('active_staff_id');

        return is_numeric($staffId)
            ? StaffProfile::query()->whereKey((int) $staffId)->where('is_active', true)->first()
            : null;
    }
}
