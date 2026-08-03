<?php

namespace App\Http\Controllers\Chain;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ChainMenuCategory;
use App\Models\ChainMenuProduct;
use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkflow;
use App\Services\ChainMenuPublisher;
use App\Services\ProductionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChainWorkflowController extends Controller
{
    public function index(Request $request, ProductionWorkflowService $service): View
    {
        $branchIds = Auth::user()->accessibleChainBranchIds();
        $selectedBranchId = $request->integer('branch_id') ?: null;
        abort_if($selectedBranchId && ! in_array($selectedBranchId, $branchIds, true), 403);
        $scopeIds = $selectedBranchId ? [$selectedBranchId] : $branchIds;
        $branches = Branch::whereIn('id', $branchIds)->orderBy('name')->get();
        $centralCategories = ChainMenuCategory::with(['products' => fn ($query) => $query->orderBy('name')])
            ->where('organization_id', Auth::user()->organization_id)
            ->orderBy('sort_order')
            ->get();
        $centralProducts = $centralCategories->pluck('products')->flatten()->values();
        $centralIngredients = $centralProducts->where('item_type', 'raw_material')->values();
        $recipes = ProductionRecipe::withoutGlobalScopes()->with(['branch', 'outputProduct', 'items.ingredient'])->whereIn('branch_id', $scopeIds)->where('is_active', true)->latest()->get();
        $workflows = ProductionWorkflow::withoutGlobalScopes()->with(['branch', 'recipe.outputProduct', 'items.product', 'createdBy', 'completedBy'])->whereIn('branch_id', $scopeIds)->latest()->paginate(25)->withQueryString();
        $workflows->getCollection()->each(function ($workflow) use ($service): void {
            $workflow->preview_requirements = $workflow->items->isNotEmpty() || ! $workflow->recipe ? collect() : $service->requirements($workflow->recipe, (float) $workflow->planned_servings);
        });
        $baseQuery = ProductionWorkflow::withoutGlobalScopes()->whereIn('branch_id', $scopeIds);
        $stats = [
            'recipes' => $recipes->count(),
            'planned' => (clone $baseQuery)->where('status', 'planned')->count(),
            'in_progress' => (clone $baseQuery)->where('status', 'in_progress')->count(),
            'completed_today' => (clone $baseQuery)->where('status', 'completed')->whereDate('completed_at', today())->count(),
        ];
        $canManage = Auth::user()->chain_role !== 'analyst';

        return view('chain.workflows.index', compact('branches', 'selectedBranchId', 'centralCategories', 'centralProducts', 'centralIngredients', 'recipes', 'workflows', 'stats', 'canManage'));
    }

    public function storeRecipe(Request $request, ProductionWorkflowService $service, ChainMenuPublisher $publisher): RedirectResponse
    {
        $this->authorizeMutation();
        $branchId = (int) $request->validate(['branch_id' => ['required', 'integer']])['branch_id'];
        $this->authorizeBranch($branchId);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('production_recipes')->where('branch_id', $branchId)],
            'output_menu_product_id' => ['required', 'integer'], 'base_servings' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'instructions' => ['nullable', 'string', 'max:3000'], 'items' => ['required', 'array', 'min:1'],
            'items.*.menu_product_id' => ['required', 'integer', 'distinct'], 'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit' => ['required', Rule::in(ProductionWorkflowService::allowedUnits())],
        ]);
        $organizationId = (int) $request->user()->organization_id;
        $output = ChainMenuProduct::with(['organization', 'category'])
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['output_menu_product_id']);
        $ingredientIds = collect($validated['items'])->pluck('menu_product_id');
        if ($ingredientIds->contains((int) $output->id)) {
            throw ValidationException::withMessages(['items' => 'Üretilen ürün kendi reçetesinde hammadde olamaz.']);
        }
        $ingredients = ChainMenuProduct::with(['organization', 'category'])
            ->where('organization_id', $organizationId)->where('item_type', 'raw_material')
            ->whereIn('id', $ingredientIds)->get()->keyBy('id');
        if ($ingredients->count() !== $ingredientIds->count()) {
            throw ValidationException::withMessages(['items' => 'Tüm hammaddeler merkezi menüdeki hammadde kayıtlarından seçilmelidir.']);
        }
        DB::transaction(function () use ($validated, $branchId, $request, $service, $publisher, $output, $ingredients): void {
            $publisher->publish($output, [$branchId]);
            foreach ($ingredients as $ingredient) $publisher->publish($ingredient, [$branchId]);

            $publishedProducts = Product::withoutGlobalScopes()->where('branch_id', $branchId)
                ->whereIn('sku', $ingredients->pluck('sku')->push($output->sku))->get()->keyBy('sku');
            $publishedOutput = $publishedProducts->get($output->sku);
            if (! $publishedOutput) throw ValidationException::withMessages(['output_menu_product_id' => 'Merkezi menü ürünü şubeye aktarılamadı.']);

            foreach ($validated['items'] as $item) {
                $centralIngredient = $ingredients->get($item['menu_product_id']);
                $publishedIngredient = $publishedProducts->get($centralIngredient->sku);
                if (! $publishedIngredient) throw ValidationException::withMessages(['items' => 'Bir hammadde şubeye aktarılamadı.']);
                $service->convert((float) $item['quantity'], $item['unit'], $publishedIngredient->unit);
            }

            $recipe = ProductionRecipe::withoutGlobalScopes()->create(['branch_id' => $branchId, 'output_product_id' => $publishedOutput->id, 'created_by_user_id' => $request->user()->id, 'name' => $validated['name'], 'base_servings' => $validated['base_servings'], 'instructions' => $validated['instructions'] ?? null, 'is_active' => true]);
            foreach ($validated['items'] as $item) {
                $centralIngredient = $ingredients->get($item['menu_product_id']);
                $publishedIngredient = $publishedProducts->get($centralIngredient->sku);
                $recipe->items()->create(['ingredient_product_id' => $publishedIngredient->id, 'quantity' => $item['quantity'], 'unit' => $service->normalizeUnit($item['unit'])]);
            }
        });
        return back()->with('success', 'Merkezi menü ürünleri şubeye bağlandı ve reçete oluşturuldu.');
    }

    public function storeWorkflow(Request $request, ProductionWorkflowService $service): RedirectResponse
    {
        $this->authorizeMutation();
        $validated = $request->validate(['production_recipe_id' => ['required', 'integer'], 'planned_servings' => ['required', 'numeric', 'gt:0', 'max:100000'], 'scheduled_for' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $recipe = ProductionRecipe::withoutGlobalScopes()->whereKey($validated['production_recipe_id'])->where('is_active', true)->firstOrFail();
        $this->authorizeBranch((int) $recipe->branch_id);
        $workflow = $service->create($recipe, (float) $validated['planned_servings'], $request->user(), $validated['scheduled_for'] ?? null, $validated['notes'] ?? null);
        return back()->with('success', "{$workflow->workflow_number} numaralı iş akışı planlandı.");
    }

    public function start(ProductionWorkflow $workflow, ProductionWorkflowService $service): RedirectResponse { $this->authorizeWorkflow($workflow); $service->start($workflow); return back()->with('success', 'Üretim başlatıldı.'); }
    public function complete(ProductionWorkflow $workflow, ProductionWorkflowService $service): RedirectResponse { $this->authorizeWorkflow($workflow); $service->complete($workflow, Auth::user()); return back()->with('success', 'Üretim tamamlandı ve stoklar işlendi.'); }
    public function cancel(ProductionWorkflow $workflow, ProductionWorkflowService $service): RedirectResponse { $this->authorizeWorkflow($workflow); $service->cancel($workflow); return back()->with('success', 'İş akışı iptal edildi.'); }

    private function authorizeWorkflow(ProductionWorkflow $workflow): void { $this->authorizeMutation(); $this->authorizeBranch((int) $workflow->branch_id); }
    private function authorizeBranch(int $branchId): void { abort_unless(in_array($branchId, Auth::user()->accessibleChainBranchIds(), true), 403); }
    private function authorizeMutation(): void { abort_if(Auth::user()->chain_role === 'analyst', 403); }
}
