<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Services\ChainMenuPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChainMenuController extends Controller
{
    public function index(): View
    {
        $organization = Auth::user()->organization;
        $categories = $organization->menuCategories()->with(['products.branches'])->orderBy('sort_order')->get();
        $branches = Branch::whereIn('id', Auth::user()->accessibleChainBranchIds())->orderBy('name')->get();
        $canManage = in_array(Auth::user()->chain_role, ['owner', 'general_manager'], true);

        return view('chain.menu.index', compact('categories', 'branches', 'canManage'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $organization = Auth::user()->organization;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $slug = Str::slug($validated['name']);
        $request->validate(['name' => [Rule::unique('chain_menu_categories', 'name')->where('organization_id', $organization->id)]]);
        $organization->menuCategories()->create([
            'name' => trim($validated['name']), 'slug' => $slug,
            'sort_order' => $validated['sort_order'] ?? ($organization->menuCategories()->max('sort_order') + 1), 'is_active' => true,
        ]);

        return back()->with('success', 'Merkezi menü kategorisi oluşturuldu.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $this->authorizeManagement();
        $organization = Auth::user()->organization;
        $validated = $this->validateProduct($request);
        $category = $organization->menuCategories()->findOrFail($validated['chain_menu_category_id']);

        DB::transaction(function () use ($validated, $organization, $category, $request): void {
            $product = $organization->menuProducts()->create([
                ...collect($validated)->except(['branch_ids', 'price_overrides', 'enabled_branch_ids'])->all(),
                'chain_menu_category_id' => $category->id,
                'sku' => strtoupper($validated['sku']),
                'is_active' => $request->boolean('is_active', true),
            ]);
            $this->syncAssignments($product, $validated, $request);
        });

        return back()->with('success', 'Merkezi ürün taslağı oluşturuldu. Yayınla butonuyla şubelere aktarabilirsiniz.');
    }

    public function updateProduct(Request $request, ChainMenuProduct $menuProduct): RedirectResponse
    {
        $this->authorizeProduct($menuProduct);
        $validated = $this->validateProduct($request, $menuProduct);
        $category = Auth::user()->organization->menuCategories()->findOrFail($validated['chain_menu_category_id']);

        DB::transaction(function () use ($menuProduct, $validated, $category, $request): void {
            $menuProduct->update([
                ...collect($validated)->except(['branch_ids', 'price_overrides', 'enabled_branch_ids'])->all(),
                'chain_menu_category_id' => $category->id,
                'sku' => strtoupper($validated['sku']),
                'is_active' => $request->boolean('is_active'),
            ]);
            $this->syncAssignments($menuProduct, $validated, $request);
        });

        return back()->with('success', 'Merkezi ürün güncellendi. Değişiklikleri aktarmak için yeniden yayınlayın.');
    }

    public function publish(Request $request, ChainMenuProduct $menuProduct, ChainMenuPublisher $publisher): RedirectResponse
    {
        $this->authorizeProduct($menuProduct);
        $branchIds = $request->validate(['branch_ids' => ['required', 'array', 'min:1'], 'branch_ids.*' => ['integer', 'distinct']])['branch_ids'];
        $accessible = Auth::user()->accessibleChainBranchIds();
        abort_unless(count(array_intersect($branchIds, $accessible)) === count($branchIds), 403);
        $count = $publisher->publish($menuProduct->load(['organization', 'category']), $branchIds);

        return back()->with('success', "Ürün {$count} şubeye yayınlandı.");
    }

    private function validateProduct(Request $request, ?ChainMenuProduct $product = null): array
    {
        return $request->validate([
            'chain_menu_category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('chain_menu_products', 'sku')->where('organization_id', Auth::user()->organization_id)->ignore($product)],
            'base_price' => ['required', 'numeric', 'min:0'],
            'discounted_price' => ['nullable', 'numeric', 'min:0'],
            'kitchen_department' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_path' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'distinct'],
            'enabled_branch_ids' => ['nullable', 'array'],
            'price_overrides' => ['nullable', 'array'],
            'price_overrides.*' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function syncAssignments(ChainMenuProduct $product, array $validated, Request $request): void
    {
        $branchIds = array_map('intval', $validated['branch_ids'] ?? []);
        $accessible = Auth::user()->accessibleChainBranchIds();
        abort_unless(count(array_intersect($branchIds, $accessible)) === count($branchIds), 403);
        $enabled = array_map('intval', $validated['enabled_branch_ids'] ?? []);
        $overrides = $validated['price_overrides'] ?? [];

        DB::table('chain_menu_product_branch')->where('chain_menu_product_id', $product->id)->whereNotIn('branch_id', $branchIds)->delete();
        foreach ($branchIds as $branchId) {
            DB::table('chain_menu_product_branch')->updateOrInsert(
                ['chain_menu_product_id' => $product->id, 'branch_id' => $branchId],
                [
                    'price_override' => filled($overrides[$branchId] ?? null) ? $overrides[$branchId] : null,
                    'is_enabled' => in_array($branchId, $enabled, true),
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }
    }

    private function authorizeManagement(): void
    {
        abort_unless(in_array(Auth::user()->chain_role, ['owner', 'general_manager'], true), 403);
    }

    private function authorizeProduct(ChainMenuProduct $product): void
    {
        $this->authorizeManagement();
        abort_unless($product->organization_id === Auth::user()->organization_id, 404);
    }
}
