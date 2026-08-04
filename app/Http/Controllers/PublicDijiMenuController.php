<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Check;
use App\Models\DijiMenuIntegration;
use App\Models\DiningTable;
use App\Models\Organization;
use App\Services\Checks\CheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class PublicDijiMenuController extends Controller
{
    public function show(string $companySlug, string $branchSlug, ?string $tableToken = null): View
    {
        [$integration, $organization, $branch] = $this->resolveMenu($companySlug, $branchSlug);
        $diningTable = $tableToken
            ? DiningTable::withoutGlobalScope('authenticated_branch')
                ->where('branch_id', $branch->id)
                ->where('qr_token', $tableToken)
                ->where('is_active', true)
                ->firstOrFail()
            : null;

        $categories = Category::withoutGlobalScope('authenticated_branch')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->with(['products' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $menuSettings = $integration?->settings ?? [];
        $brandSettings = data_get($menuSettings, 'brand', []);
        $branchSettings = data_get($menuSettings, 'branches.'.(string) $branch->id, []);

        return view('diji-menu.public', compact(
            'branch',
            'categories',
            'organization',
            'brandSettings',
            'branchSettings',
            'diningTable',
        ));
    }

    public function order(Request $request, string $companySlug, string $branchSlug, string $tableToken, CheckService $checkService): RedirectResponse
    {
        [, , $branch] = $this->resolveMenu($companySlug, $branchSlug);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:30'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $table = DiningTable::withoutGlobalScope('authenticated_branch')
            ->where('branch_id', $branch->id)
            ->where('qr_token', $tableToken)
            ->where('is_active', true)
            ->firstOrFail();

        $productIds = collect($validated['items'])->pluck('product_id')->unique()->values();
        $validProductCount = $branch->products()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->count();
        abort_unless($validProductCount === $productIds->count(), 422, 'Sepette geçersiz veya pasif ürün bulunuyor.');

        try {
            DB::transaction(function () use ($table, $validated, $checkService): void {
                $lockedTable = DiningTable::withoutGlobalScope('authenticated_branch')->whereKey($table->id)->lockForUpdate()->firstOrFail();
                $check = Check::withoutGlobalScope('authenticated_branch')
                    ->where('dining_table_id', $lockedTable->id)
                    ->where('status', 'open')
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if (! $check) {
                    $check = $checkService->openCheck($lockedTable, null, [
                        'guest_count' => $validated['guest_count'] ?? 1,
                        'client_reference' => 'qr-'.Str::lower(Str::random(12)),
                    ]);
                }

                if (! empty($validated['customer_notes'])) {
                    $check->update(['customer_notes' => $validated['customer_notes']]);
                }

                $checkService->addItems($check, $validated['items']);
            });
        } catch (RuntimeException $exception) {
            return redirect()->route('diji-menu.table', [
                'companySlug' => $companySlug,
                'branchSlug' => $branchSlug,
                'tableToken' => $tableToken,
            ])->withErrors(['order' => $exception->getMessage()]);
        }

        return redirect()->route('diji-menu.table', [
            'companySlug' => $companySlug,
            'branchSlug' => $branchSlug,
            'tableToken' => $tableToken,
        ])->with('order_success', 'Siparişiniz masanıza iletildi.');
    }

    /** @return array{0: ?DijiMenuIntegration, 1: Organization, 2: Branch} */
    private function resolveMenu(string $companySlug, string $branchSlug): array
    {
        $integration = DijiMenuIntegration::query()
            ->where('company_slug', $companySlug)
            ->first();

        abort_if($integration && ! $integration->is_active, 404);

        $organization = $integration?->organization
            ?? Organization::query()
                ->where('is_active', true)
                ->get()
                ->first(fn (Organization $candidate) => hash_equals(
                    Str::slug((string) ($candidate->code ?: $candidate->name)),
                    Str::slug($companySlug),
                ) || hash_equals(Str::slug((string) $candidate->name), Str::slug($companySlug)));
        abort_unless($organization, 404);

        $requestedSlug = Str::slug($branchSlug);
        $branchSlugs = $integration?->branch_slugs ?? [];
        $branch = $organization
            ->branches()
            ->where('branches.is_active', true)
            ->get()
            ->first(function (Branch $candidate) use ($branchSlugs, $requestedSlug): bool {
                $configuredSlug = $branchSlugs[(string) $candidate->id] ?? $branchSlugs[$candidate->id] ?? null;

                return ($configuredSlug && hash_equals(Str::slug((string) $configuredSlug), $requestedSlug))
                    || hash_equals(Str::slug((string) $candidate->code), $requestedSlug)
                    || hash_equals(Str::slug((string) $candidate->name), $requestedSlug);
            });
        abort_unless($branch, 404);

        return [$integration, $organization, $branch];
    }
}
