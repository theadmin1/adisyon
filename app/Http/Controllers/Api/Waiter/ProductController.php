<?php

namespace App\Http\Controllers\Api\Waiter;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWaiterApiToken;
use App\Models\Category;
use App\Models\Product;
use App\Support\WaiterApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $branchId = $this->branchId($request);
        $categories = Category::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('is_active', true))
            ->with(['products' => fn ($query) => $query
                ->withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'products' => $category->products->map(fn (Product $product): array => WaiterApiPresenter::product($product))->values(),
            ]),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $branchId = $this->branchId($request);
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'available_only' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $products = Product::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->when($validated['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($request->boolean('available_only'), fn ($query) => $query->where(function ($nested): void {
                $nested->where('track_stock', false)->orWhere('stock_quantity', '>', 0);
            }))
            ->with('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products->map(fn (Product $product): array => [
                ...WaiterApiPresenter::product($product),
                'category_name' => $product->category?->name,
            ]),
        ]);
    }

    private function branchId(Request $request): int
    {
        return (int) $request->attributes->get(EnsureWaiterApiToken::TOKEN_ATTRIBUTE)->branch_id;
    }
}
