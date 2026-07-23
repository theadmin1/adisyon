@extends('layouts.app')

@section('title', 'Ürünler & Menü Yönetimi - Adisyon POS')

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans antialiased">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-[#121522]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3.5 flex items-center justify-between shadow-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center text-slate-300 hover:text-white transition-all shadow-md" title="Ana Panele Dön">
                <i class="fi fi-rr-arrow-left text-base"></i>
            </a>
            <div>
                <h1 class="font-extrabold text-lg tracking-wide text-white flex items-center gap-2">
                    <i class="fi fi-rr-box-open text-rose-400"></i>
                    <span>Ürünler & Menü Yönetimi</span>
                </h1>
                <p class="text-xs text-slate-400">Restoran menünüzü, fiyatlarınızı ve kategorilerinizi yönetin.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if(session('success'))
                <div class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
                    <i class="fi fi-rr-check-circle text-sm"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <button onclick="openModal('addCategoryModal')" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 hover:text-white text-xs font-bold transition-all flex items-center gap-2">
                <i class="fi fi-rr-apps text-rose-400"></i>
                <span class="hidden sm:inline">+ Yeni Kategori</span>
            </button>

            <button onclick="openModal('addProductModal')" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-extrabold transition-all shadow-lg shadow-rose-600/30 flex items-center gap-2">
                <i class="fi fi-rr-plus text-sm"></i>
                <span>+ Yeni Ürün Ekle</span>
            </button>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

        <!-- STATS OVERVIEW CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Toplam Ürün</div>
                    <div class="text-2xl font-extrabold text-white mt-1">{{ $stats['total_products'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <i class="fi fi-rr-box-open text-xl"></i>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aktif Ürünler</div>
                    <div class="text-2xl font-extrabold text-emerald-400 mt-1">{{ $stats['active_products'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fi fi-rr-check-circle text-xl"></i>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Kategori Sayısı</div>
                    <div class="text-2xl font-extrabold text-indigo-400 mt-1">{{ $stats['total_categories'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <i class="fi fi-rr-apps text-xl"></i>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Ortalama Fiyat</div>
                    <div class="text-2xl font-extrabold text-amber-400 mt-1">₺{{ $stats['avg_price'] }}</div>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i class="fi fi-rr-tags text-xl"></i>
                </div>
            </div>
        </div>

        <!-- CATEGORY TABS & SEARCH BAR -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-[#131625] p-3.5 rounded-2xl border border-slate-800/80 shadow-xl">
            <!-- Categories Horizontal Scroll Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 no-scrollbar">
                <a href="{{ route('products.index') }}" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all {{ empty($selectedCategoryId) ? 'bg-rose-600 text-white shadow-lg shadow-rose-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                    Tüm Ürünler ({{ $stats['total_products'] }})
                </a>

                @foreach($categories as $cat)
                    <a href="{{ route('products.index', ['category_id' => $cat->id]) }}" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all {{ $selectedCategoryId == $cat->id ? 'bg-rose-600 text-white shadow-lg shadow-rose-600/30' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}">
                        {{ $cat->name }} ({{ $cat->products_count }})
                    </a>
                @endforeach
            </div>

            <!-- Search Form -->
            <form action="{{ route('products.index') }}" method="GET" class="relative min-w-[240px]">
                @if($selectedCategoryId)
                    <input type="hidden" name="category_id" value="{{ $selectedCategoryId }}">
                @endif
                <i class="fi fi-rr-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="{{ $search }}" placeholder="Ürün veya SKU ara..." class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:border-rose-500 focus:outline-none transition">
            </form>
        </div>

        <!-- PRODUCTS LIST TABLE -->
        @if($products->isEmpty())
            <div class="p-12 text-center bg-[#131625] border border-slate-800/80 rounded-2xl space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto text-2xl">
                    <i class="fi fi-rr-box-open"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Henüz Ürün Bulunmuyor</h3>
                    <p class="text-xs text-slate-400 mt-1">Seçilen kriterlere uygun ürün bulunamadı. Yeni bir ürün ekleyebilirsiniz.</p>
                </div>
                <button onclick="openModal('addProductModal')" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition shadow-lg shadow-rose-600/30">
                    + İlk Ürünü Ekle
                </button>
            </div>
        @else
            <div class="bg-[#131625] border border-slate-800/80 rounded-2xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#0e101b] border-b border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3.5 px-5">Ürün Adı & Açıklama</th>
                                <th class="py-3.5 px-4">Kategori</th>
                                <th class="py-3.5 px-4">SKU Kodu</th>
                                <th class="py-3.5 px-4">Mutfak / İstasyon</th>
                                <th class="py-3.5 px-4 text-right">Satış Fiyatı</th>
                                <th class="py-3.5 px-4 text-center">Durum</th>
                                <th class="py-3.5 px-5 text-right">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 text-xs">
                            @foreach($products as $product)
                                <tr class="hover:bg-slate-800/30 transition-colors group">
                                    <!-- Ürün Adı & Açıklama -->
                                    <td class="py-4 px-5">
                                        <div class="font-bold text-white text-sm group-hover:text-rose-300 transition-colors">
                                            {{ $product->name }}
                                        </div>
                                        @if($product->description)
                                            <div class="text-[11px] text-slate-400 mt-0.5 max-w-md line-clamp-1">
                                                {{ $product->description }}
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Kategori -->
                                    <td class="py-4 px-4">
                                        <span class="px-2.5 py-1 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-300 text-[11px] font-semibold inline-block">
                                            {{ $product->category->name ?? 'Kategorisiz' }}
                                        </span>
                                    </td>

                                    <!-- SKU Kodu -->
                                    <td class="py-4 px-4 font-mono text-slate-400">
                                        {{ $product->sku }}
                                    </td>

                                    <!-- Mutfak / İstasyon -->
                                    <td class="py-4 px-4">
                                        @if($product->kitchen_department)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-900 border border-slate-800 text-[11px] text-slate-300">
                                                <i class="fi fi-rr-restaurant text-rose-400"></i>
                                                <span>{{ $product->kitchen_department }}</span>
                                            </span>
                                        @else
                                            <span class="text-slate-600">-</span>
                                        @endif
                                    </td>

                                    <!-- Satış Fiyatı -->
                                    <td class="py-4 px-4 text-right">
                                        <span class="font-extrabold text-white text-sm">
                                            ₺{{ number_format($product->price, 2) }}
                                        </span>
                                    </td>

                                    <!-- Durum -->
                                    <td class="py-4 px-4 text-center">
                                        @if($product->is_active)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                <span>Aktif</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-900 border border-slate-800 text-slate-500 text-[10px] font-bold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
                                                <span>Pasif</span>
                                            </span>
                                        @endif
                                    </td>

                                    <!-- İşlemler -->
                                    <td class="py-4 px-5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Status Toggle -->
                                            <form action="{{ route('products.toggle', $product) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="w-8 h-8 rounded-lg border flex items-center justify-center transition text-xs {{ $product->is_active ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 hover:bg-emerald-500/20' : 'bg-slate-800 border-slate-700 text-slate-500 hover:text-slate-300' }}" title="{{ $product->is_active ? 'Pasife Al' : 'Aktifleştir' }}">
                                                    <i class="fi fi-rr-power"></i>
                                                </button>
                                            </form>

                                            <!-- Edit Product -->
                                            <button onclick='editProduct(@json($product))' class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition text-xs" title="Düzenle">
                                                <i class="fi fi-rr-edit"></i>
                                            </button>

                                            <!-- Delete Product -->
                                            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Bu ürünü silmek istediğinize emin misiniz?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 flex items-center justify-center transition text-xs" title="Sil">
                                                    <i class="fi fi-rr-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </main>
</div>

<!-- MODAL 1: YENİ ÜRÜN EKLE -->
<div id="addProductModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#131625] border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3.5">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fi fi-rr-plus text-rose-400"></i>
                <span>Yeni Ürün Ekle</span>
            </h3>
            <button onclick="closeModal('addProductModal')" class="text-slate-400 hover:text-white">
                <i class="fi fi-rr-cross text-sm"></i>
            </button>
        </div>

        <form action="{{ route('products.store') }}" method="POST" class="space-y-4 text-xs">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Ürün Adı</label>
                    <input type="text" name="name" required placeholder="Örn: İskender Kebap" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Kategori</label>
                    <select name="category_id" required class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $selectedCategoryId == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Satış Fiyatı (₺)</label>
                    <input type="number" step="0.01" name="price" required placeholder="280.00" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">SKU / Ürün Kodu</label>
                    <input type="text" name="sku" placeholder="Örn: KBP-101" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Mutfak / İstasyon</label>
                    <select name="kitchen_department" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                        <option value="Mutfak / Izgara">Mutfak / Izgara</option>
                        <option value="Mutfak / FastFood">Mutfak / FastFood</option>
                        <option value="Bar / İçecek">Bar / İçecek</option>
                        <option value="Tatlı Tezgahı">Tatlı Tezgahı</option>
                        <option value="Çorba / Başlangıç">Çorba / Başlangıç</option>
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Ürün Açıklaması</label>
                    <textarea name="description" rows="2" placeholder="Ürün içeriği ve detaylar..." class="w-full bg-slate-900 border border-slate-700/80 rounded-xl p-3 text-white focus:border-rose-500 focus:outline-none transition"></textarea>
                </div>

                <div class="col-span-2 flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800">
                    <span class="font-bold text-slate-300">Ürün Aktif Durumda Başlasın</span>
                    <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 rounded bg-slate-800 border-slate-700 text-rose-600 focus:ring-0">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('addProductModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/30 transition">
                    Ürünü Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: YENİ KATEGORİ EKLE -->
<div id="addCategoryModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#131625] border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3.5">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fi fi-rr-apps text-rose-400"></i>
                <span>Yeni Kategori Ekle</span>
            </h3>
            <button onclick="closeModal('addCategoryModal')" class="text-slate-400 hover:text-white">
                <i class="fi fi-rr-cross text-sm"></i>
            </button>
        </div>

        <form action="{{ route('products.categories.store') }}" method="POST" class="space-y-4 text-xs">
            @csrf

            <div>
                <label class="block font-bold text-slate-300 mb-1">Kategori Adı</label>
                <input type="text" name="name" required placeholder="Örn: Başlangıçlar & Mezeler" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('addCategoryModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/30 transition">
                    Kategoriyi Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 3: ÜRÜN DÜZENLE -->
<div id="editProductModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#131625] border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3.5">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fi fi-rr-edit text-rose-400"></i>
                <span>Ürün Bilgilerini Düzenle</span>
            </h3>
            <button onclick="closeModal('editProductModal')" class="text-slate-400 hover:text-white">
                <i class="fi fi-rr-cross text-sm"></i>
            </button>
        </div>

        <form id="editProductForm" method="POST" class="space-y-4 text-xs">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Ürün Adı</label>
                    <input type="text" id="edit_name" name="name" required class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Kategori</label>
                    <select id="edit_category_id" name="category_id" required class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Satış Fiyatı (₺)</label>
                    <input type="number" step="0.01" id="edit_price" name="price" required class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">SKU / Ürün Kodu</label>
                    <input type="text" id="edit_sku" name="sku" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Mutfak / İstasyon</label>
                    <select id="edit_kitchen_department" name="kitchen_department" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-rose-500 focus:outline-none transition">
                        <option value="Mutfak / Izgara">Mutfak / Izgara</option>
                        <option value="Mutfak / FastFood">Mutfak / FastFood</option>
                        <option value="Bar / İçecek">Bar / İçecek</option>
                        <option value="Tatlı Tezgahı">Tatlı Tezgahı</option>
                        <option value="Çorba / Başlangıç">Çorba / Başlangıç</option>
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Ürün Açıklaması</label>
                    <textarea id="edit_description" name="description" rows="2" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl p-3 text-white focus:border-rose-500 focus:outline-none transition"></textarea>
                </div>

                <div class="col-span-2 flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800">
                    <span class="font-bold text-slate-300">Ürün Aktif Durumda</span>
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" class="w-4 h-4 rounded bg-slate-800 border-slate-700 text-rose-600 focus:ring-0">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('editProductModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-rose-600/30 transition">
                    Güncellemeleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    function editProduct(product) {
        document.getElementById('editProductForm').action = '/products/' + product.id;
        document.getElementById('edit_name').value = product.name || '';
        document.getElementById('edit_category_id').value = product.category_id || '';
        document.getElementById('edit_price').value = product.price || '';
        document.getElementById('edit_sku').value = product.sku || '';
        document.getElementById('edit_kitchen_department').value = product.kitchen_department || 'Mutfak / Izgara';
        document.getElementById('edit_description').value = product.description || '';
        document.getElementById('edit_is_active').checked = !!product.is_active;

        openModal('editProductModal');
    }
</script>
@endsection
