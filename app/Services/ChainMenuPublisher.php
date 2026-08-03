<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ChainMenuProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChainMenuPublisher
{
    public function publish(ChainMenuProduct $menuProduct, array $branchIds): int
    {
        $allowed = $menuProduct->organization->branches()->whereIn('branches.id', $branchIds)->pluck('branches.id')->all();
        abort_unless(count($allowed) === count(array_unique($branchIds)), 403);

        DB::transaction(function () use ($menuProduct, $allowed): void {
            foreach ($allowed as $branchId) {
                $assignment = DB::table('chain_menu_product_branch')
                    ->where('chain_menu_product_id', $menuProduct->id)->where('branch_id', $branchId)->first();
                $category = Category::withoutGlobalScope('authenticated_branch')->updateOrCreate(
                    ['branch_id' => $branchId, 'slug' => $menuProduct->category->slug],
                    ['name' => $menuProduct->category->name, 'sort_order' => $menuProduct->category->sort_order, 'is_active' => $menuProduct->category->is_active, 'is_synced' => true]
                );
                $product = Product::withoutGlobalScope('authenticated_branch')->firstOrNew(
                    ['branch_id' => $branchId, 'sku' => $menuProduct->sku]
                );
                $isNewProduct = ! $product->exists;
                $product->fill([
                    'category_id' => $category->id,
                    'name' => $menuProduct->name,
                    'slug' => Str::slug($menuProduct->name),
                    'price' => $assignment?->price_override ?? $menuProduct->base_price,
                    'unit' => $menuProduct->unit,
                    'track_stock' => $menuProduct->track_stock,
                    'discounted_price' => $menuProduct->discounted_price,
                    'kitchen_department' => $menuProduct->kitchen_department,
                    'description' => $menuProduct->description,
                    'image_path' => $menuProduct->image_path,
                    'is_active' => $menuProduct->item_type === 'menu_item' && $menuProduct->is_active && ($assignment?->is_enabled ?? true),
                    'is_synced' => true,
                ]);
                if ($isNewProduct) {
                    $product->stock_quantity = 0;
                    $product->min_stock_level = 0;
                }
                $product->save();
                DB::table('chain_menu_product_branch')->updateOrInsert(
                    ['chain_menu_product_id' => $menuProduct->id, 'branch_id' => $branchId],
                    [
                        'published_product_id' => $product->id,
                        'is_enabled' => $assignment?->is_enabled ?? true,
                        'price_override' => $assignment?->price_override,
                        'published_at' => now(),
                        'created_at' => $assignment?->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return count($allowed);
    }
}
