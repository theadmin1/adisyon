<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $selectedCategoryId = $request->query('category_id');
        $search = $request->query('search');

        // Otomatik varsayılan kategorileri & örnek ürünleri yükle (Veritabanı boşsa)
        if (Category::count() === 0) {
            $this->seedDefaultData();
        }

        $categories = Category::withCount('products')->orderBy('sort_order')->get();

        $productsQuery = Product::with('category');

        if ($selectedCategoryId) {
            $productsQuery->where('category_id', $selectedCategoryId);
        }

        if ($search) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->orderBy('created_at', 'desc')->get();

        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'total_categories' => Category::count(),
            'avg_price' => Product::avg('price') ? number_format(Product::avg('price'), 2) : '0.00',
        ];

        return view('products.index', compact('products', 'categories', 'selectedCategoryId', 'search', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'kitchen_department' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:4096',
            'image_url' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');
        $validated['sku'] = $validated['sku'] ?? 'PRD-' . rand(1000, 9999);

        // Fotoğraf Yükleme İşlemi
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . Str::slug($validated['name']) . '.' . $file->getClientOriginalExtension();
            $uploadPath = public_path('uploads/products');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            $file->move($uploadPath, $filename);
            $validated['image_path'] = 'uploads/products/' . $filename;
        } elseif (!empty($validated['image_url'])) {
            $validated['image_path'] = trim($validated['image_url']);
        }

        Product::create($validated);

        return redirect()->route('products.index', ['category_id' => $validated['category_id']])
            ->with('success', "'{$validated['name']}' ürünü fotoğrafıyla birlikte başarıyla eklendi.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'kitchen_department' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:4096',
            'image_url' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');

        // Fotoğraf Güncelleme İşlemi
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . Str::slug($validated['name']) . '.' . $file->getClientOriginalExtension();
            $uploadPath = public_path('uploads/products');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }
            $file->move($uploadPath, $filename);
            $validated['image_path'] = 'uploads/products/' . $filename;
        } elseif (!empty($validated['image_url'])) {
            $validated['image_path'] = trim($validated['image_url']);
        }

        $product->update($validated);

        return redirect()->back()->with('success', "'{$product->name}' ürün bilgileri ve fotoğrafı güncellendi.");
    }

    public function toggleStatus(Request $request, Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);

        $statusText = $product->is_active ? 'aktif' : 'pasif';
        $message = "'{$product->name}' ürünü {$statusText} duruma getirildi.";

        if ($request->wantsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'is_active' => $product->is_active,
                'status_text' => $product->is_active ? 'Aktif' : 'Pasif',
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return redirect()->back()->with('success', "'{$name}' ürünü sistemden silindi.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
        ]);

        $category = Category::create([
            'name' => trim($validated['name']),
            'slug' => Str::slug($validated['name']),
            'sort_order' => Category::max('sort_order') + 1,
            'is_active' => true,
        ]);

        return redirect()->route('products.index', ['category_id' => $category->id])
            ->with('success', "'{$category->name}' kategorisi başarıyla eklendi.");
    }

    private function seedDefaultData(): void
    {
        $categories = [
            ['name' => 'Ana Yemekler', 'order' => 1],
            ['name' => 'Döner & Kebap', 'order' => 2],
            ['name' => 'Burgerler', 'order' => 3],
            ['name' => 'Çorbalar', 'order' => 4],
            ['name' => 'İçecekler', 'order' => 5],
            ['name' => 'Tatlılar', 'order' => 6],
        ];

        foreach ($categories as $cat) {
            $createdCategory = Category::create([
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'sort_order' => $cat['order'],
                'is_active' => true,
            ]);

            if ($cat['name'] === 'Döner & Kebap') {
                Product::create([
                    'category_id' => $createdCategory->id,
                    'name' => 'İskender Kebap',
                    'slug' => 'iskender-kebap',
                    'sku' => 'KBP-101',
                    'price' => 280.00,
                    'kitchen_department' => 'Mutfak / Izgara',
                    'description' => 'Tereyağlı, pideli nefis 1. sınıf döner kebap',
                    'image_path' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=300&q=80',
                    'is_active' => true,
                ]);
                Product::create([
                    'category_id' => $createdCategory->id,
                    'name' => 'Adana Dürüm',
                    'slug' => 'adana-durum',
                    'sku' => 'KBP-102',
                    'price' => 190.00,
                    'kitchen_department' => 'Mutfak / Izgara',
                    'description' => 'Zırh kıyması, lavaş ekmeği ve közlenmiş biber ile',
                    'image_path' => 'https://images.unsplash.com/photo-1626777552726-4a6b54c97e46?auto=format&fit=crop&w=300&q=80',
                    'is_active' => true,
                ]);
            } elseif ($cat['name'] === 'Burgerler') {
                Product::create([
                    'category_id' => $createdCategory->id,
                    'name' => 'Cheeseburger Menü',
                    'slug' => 'cheeseburger-menu',
                    'sku' => 'BRG-201',
                    'price' => 240.00,
                    'kitchen_department' => 'Mutfak / FastFood',
                    'description' => '150gr Dana Köfte, Çedar Peyniri ve Patates ile',
                    'image_path' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=300&q=80',
                    'is_active' => true,
                ]);
            } elseif ($cat['name'] === 'İçecekler') {
                Product::create([
                    'category_id' => $createdCategory->id,
                    'name' => 'Yayık Ayran',
                    'slug' => 'yayik-ayran',
                    'sku' => 'DRK-301',
                    'price' => 35.00,
                    'kitchen_department' => 'Bar',
                    'description' => 'Bol köpüklü taze yayık ayranı',
                    'image_path' => 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=300&q=80',
                    'is_active' => true,
                ]);
            } elseif ($cat['name'] === 'Tatlılar') {
                Product::create([
                    'category_id' => $createdCategory->id,
                    'name' => 'Fıstıklı Künefe',
                    'slug' => 'fistikli-kunefe',
                    'sku' => 'SWT-401',
                    'price' => 160.00,
                    'kitchen_department' => 'Tatlı Tezgahı',
                    'description' => 'Antep fıstıklı ve özel hatay künefe peynirli',
                    'image_path' => 'https://images.unsplash.com/photo-1579372786545-d24232daf58c?auto=format&fit=crop&w=300&q=80',
                    'is_active' => true,
                ]);
            }
        }
    }
}
