<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionRecipe;
use App\Models\ProductionWorkflow;
use App\Services\ProductionWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductionWorkflowController extends Controller
{
    public function index(ProductionWorkflowService $service): View
    {
        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get();
        $ingredients = $products->where('track_stock', true)->values();
        $recipes = ProductionRecipe::with(['outputProduct', 'items.ingredient'])->where('is_active', true)->latest()->get();
        $workflows = ProductionWorkflow::with(['recipe.outputProduct', 'items.product', 'createdBy', 'completedBy'])->latest()->paginate(20);
        $workflows->getCollection()->each(function ($workflow) use ($service): void {
            $workflow->preview_requirements = $workflow->items->isNotEmpty() || ! $workflow->recipe
                ? collect()
                : $service->requirements($workflow->recipe, (float) $workflow->planned_servings);
        });
        $stats = [
            'active_recipes' => $recipes->count(),
            'planned' => ProductionWorkflow::where('status', 'planned')->count(),
            'in_progress' => ProductionWorkflow::where('status', 'in_progress')->count(),
            'completed_today' => ProductionWorkflow::where('status', 'completed')->whereDate('completed_at', today())->count(),
        ];

        return view('workflows.index', compact('products', 'ingredients', 'recipes', 'workflows', 'stats'));
    }

    public function storeRecipe(Request $request, ProductionWorkflowService $service): RedirectResponse
    {
        $branchId = (int) $request->user()->branch_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('production_recipes')->where('branch_id', $branchId)],
            'output_product_id' => ['required', 'integer'],
            'base_servings' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'instructions' => ['nullable', 'string', 'max:3000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit' => ['required', Rule::in(ProductionWorkflowService::allowedUnits())],
        ]);
        $output = Product::forBranch($branchId)->whereKey($validated['output_product_id'])->firstOrFail();
        $ingredientIds = collect($validated['items'])->pluck('product_id');
        if ($ingredientIds->contains($output->id)) throw ValidationException::withMessages(['items' => 'Üretilen yemek kendi reçetesinde hammadde olamaz.']);
        $ingredientProducts = Product::forBranch($branchId)->where('track_stock', true)->whereIn('id', $ingredientIds)->get()->keyBy('id');
        if ($ingredientProducts->count() !== $ingredientIds->count()) throw ValidationException::withMessages(['items' => 'Tüm hammaddeler bu şubeye ait ve stok takibinde olmalıdır.']);
        foreach ($validated['items'] as $item) $service->convert((float) $item['quantity'], $item['unit'], $ingredientProducts->get($item['product_id'])->unit);

        DB::transaction(function () use ($validated, $branchId, $request, $service): void {
            $recipe = ProductionRecipe::withoutGlobalScopes()->create([
                'branch_id' => $branchId, 'output_product_id' => $validated['output_product_id'], 'created_by_user_id' => $request->user()->id,
                'name' => $validated['name'], 'base_servings' => $validated['base_servings'], 'instructions' => $validated['instructions'] ?? null, 'is_active' => true,
            ]);
            foreach ($validated['items'] as $item) {
                $recipe->items()->create(['ingredient_product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'unit' => $service->normalizeUnit($item['unit'])]);
            }
        });

        return back()->with('status', 'Reçete oluşturuldu. Artık porsiyon sayısına göre üretim planlayabilirsiniz.');
    }

    public function storeWorkflow(Request $request, ProductionWorkflowService $service): RedirectResponse
    {
        $validated = $request->validate(['production_recipe_id' => ['required', 'integer'], 'planned_servings' => ['required', 'numeric', 'gt:0', 'max:100000'], 'scheduled_for' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $recipe = ProductionRecipe::whereKey($validated['production_recipe_id'])->where('is_active', true)->firstOrFail();
        $workflow = $service->create($recipe, (float) $validated['planned_servings'], $request->user(), $validated['scheduled_for'] ?? null, $validated['notes'] ?? null);
        return back()->with('status', "{$workflow->workflow_number} numaralı üretim iş akışı planlandı.");
    }

    public function start(ProductionWorkflow $workflow, ProductionWorkflowService $service): RedirectResponse
    {
        $service->start($workflow);
        return back()->with('status', 'Üretim başlatıldı. Stoklar tamamlandığında düşülecek.');
    }

    public function complete(ProductionWorkflow $workflow, ProductionWorkflowService $service): RedirectResponse
    {
        $service->complete($workflow, request()->user());
        return back()->with('status', 'Üretim tamamlandı; hammaddeler stoktan düşüldü ve mamul stoğu işlendi.');
    }

    public function cancel(ProductionWorkflow $workflow, ProductionWorkflowService $service): RedirectResponse
    {
        $service->cancel($workflow);
        return back()->with('status', 'İş akışı iptal edildi; stok hareketi yapılmadı.');
    }
}
