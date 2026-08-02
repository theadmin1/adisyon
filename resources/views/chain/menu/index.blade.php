@extends('chain.layout')
@section('title', 'Merkezi Menü')
@section('content')
@php
    $totalProducts = $categories->sum(fn ($category) => $category->products->count());
    $totalAssignments = $categories->sum(fn ($category) => $category->products->sum(fn ($product) => $product->branches->count()));
    $publishedAssignments = $categories->sum(fn ($category) => $category->products->sum(fn ($product) => $product->branches->filter(fn ($branch) => (bool) $branch->pivot->published_at)->count()));
    $pendingAssignments = $totalAssignments - $publishedAssignments;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="institutional-page-kicker mb-1">Ürün ve Fiyat Yönetimi</p>
            <h1>Merkezi Menü</h1>
            <p class="mt-1 text-sm text-slate-400">Ürünleri hazırlayın, şubelere atayın ve yayın durumlarını tek ekrandan takip edin.</p>
        </div>
        @if($canManage)
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button" onclick="openMenuModal('categoryModal')" class="institutional-action inline-flex items-center justify-center gap-2 rounded-xl border border-cyan-500/30 px-4 py-2.5 text-sm font-bold text-cyan-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h7"/></svg>
                    Kategori Ekle
                </button>
                <button type="button" onclick="openMenuModal('productModal')" @disabled($categories->isEmpty()) class="institutional-action inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 disabled:cursor-not-allowed disabled:opacity-40">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                    Yeni Ürün
                </button>
            </div>
        @endif
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @unless($canManage)
        <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-300">Rolünüz görüntüleme yetkisine sahip. Merkezi menüyü yalnızca Zincir Sahibi ve Genel Müdür değiştirebilir.</div>
    @endunless

    <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Kategori</p><span class="rounded-lg bg-cyan-500/10 p-2 text-cyan-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 6h16M4 12h16M4 18h10"/></svg></span></div><p class="mt-3 text-2xl font-black">{{ $categories->count() }}</p></div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Ürün</p><span class="rounded-lg bg-violet-500/10 p-2 text-violet-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M3 7h18M5 7l1 13h12l1-13M9 11v5m6-5v5M9 7l1-3h4l1 3"/></svg></span></div><p class="mt-3 text-2xl font-black">{{ $totalProducts }}</p></div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Yayında</p><span class="rounded-lg bg-emerald-500/10 p-2 text-emerald-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m5 12 4 4L19 6"/></svg></span></div><p class="mt-3 text-2xl font-black text-emerald-400">{{ $publishedAssignments }}</p><p class="text-[11px] text-slate-500">şube ataması</p></div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4"><div class="flex items-center justify-between"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Yayın Bekliyor</p><span class="rounded-lg bg-amber-500/10 p-2 text-amber-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span></div><p class="mt-3 text-2xl font-black text-amber-400">{{ $pendingAssignments }}</p><p class="text-[11px] text-slate-500">şube ataması</p></div>
    </div>

    @if($categories->isNotEmpty())
        <div class="sticky top-3 z-20 rounded-2xl border border-slate-700/80 bg-slate-900/95 p-3 shadow-2xl shadow-black/20 backdrop-blur">
            <div class="grid gap-3 lg:grid-cols-[minmax(240px,1fr)_220px_220px_auto]">
                <label class="relative block">
                    <span class="sr-only">Ürün ara</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                    <input id="menuSearch" type="search" placeholder="Ürün adı, SKU veya açıklama ara..." class="w-full rounded-xl border border-slate-700 bg-slate-950 py-2.5 pl-10 pr-3 text-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/10">
                </label>
                <select id="menuCategoryFilter" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-cyan-500">
                    <option value="">Tüm kategoriler</option>
                    @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
                <select id="menuStatusFilter" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm outline-none focus:border-cyan-500">
                    <option value="">Tüm yayın durumları</option>
                    <option value="published">Tamamen yayında</option>
                    <option value="pending">Yayın bekliyor</option>
                    <option value="unassigned">Şube atanmamış</option>
                    <option value="inactive">Pasif ürünler</option>
                </select>
                <button id="menuClearFilters" type="button" class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-bold text-slate-300 transition hover:border-slate-600 hover:bg-slate-800">Temizle</button>
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-slate-800 pt-3 text-xs text-slate-500"><span><strong id="menuResultCount" class="text-slate-200">{{ $totalProducts }}</strong> ürün gösteriliyor</span><span class="hidden sm:inline">Kategori başlığına tıklayarak bölümü daraltabilirsiniz.</span></div>
        </div>
    @endif

    <div id="menuCategoryList" class="space-y-4">
        @forelse($categories as $category)
            <section data-menu-category="{{ $category->id }}" class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <button type="button" data-category-toggle class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-800/50">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="rounded-xl bg-slate-800 p-2.5 text-cyan-400"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 6h16M4 12h12M4 18h8"/></svg></span>
                        <div class="min-w-0"><h2 class="truncate font-black">{{ $category->name }}</h2><p class="text-xs text-slate-500"><span data-visible-category-count>{{ $category->products->count() }}</span> ürün</p></div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3"><span class="rounded-full px-2 py-1 text-[11px] font-bold {{ $category->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">{{ $category->is_active ? 'Aktif' : 'Pasif' }}</span><svg data-category-chevron class="h-4 w-4 text-slate-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m6 9 6 6 6-6"/></svg></div>
                </button>
                <div data-category-body class="border-t border-slate-800 p-4">
                    <div class="grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
                        @forelse($category->products as $product)
                            @php
                                $assignedCount = $product->branches->count();
                                $productPublishedCount = $product->branches->filter(fn ($branch) => (bool) $branch->pivot->published_at)->count();
                                $productPendingCount = $assignedCount - $productPublishedCount;
                                $productStatus = !$product->is_active ? 'inactive' : ($assignedCount === 0 ? 'unassigned' : ($productPendingCount > 0 ? 'pending' : 'published'));
                                $searchText = Illuminate\Support\Str::lower(implode(' ', [$product->name, $product->sku, $product->description, $product->kitchen_department]));
                            @endphp
                            <article data-menu-product data-category-id="{{ $category->id }}" data-status="{{ $productStatus }}" data-search="{{ $searchText }}" class="group flex flex-col rounded-2xl border border-slate-800 bg-slate-950 p-4 transition hover:border-slate-700">
                                <div class="flex gap-3">
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                                        @if($product->image_path)
                                            <img src="{{ \Illuminate\Support\Str::startsWith($product->image_path,['http://','https://']) ? $product->image_path : asset($product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                                        @else
                                            <svg class="h-7 w-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.5" d="m3 16 5-5 4 4 3-3 6 6M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"/></svg>
                                        @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2"><div class="min-w-0"><h3 class="truncate font-bold" title="{{ $product->name }}">{{ $product->name }}</h3><p class="font-mono text-[11px] text-cyan-500">{{ $product->sku }}</p></div><p class="shrink-0 text-base font-black text-emerald-400">₺{{ number_format((float)$product->base_price,2,',','.') }}</p></div>
                                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-slate-500">{{ $product->description ?: 'Açıklama bulunmuyor.' }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-3 gap-2">
                                    <div class="rounded-lg bg-slate-900 px-2 py-2 text-center"><strong class="block text-sm text-slate-200">{{ $assignedCount }}</strong><span class="text-[10px] text-slate-500">Atanan</span></div>
                                    <div class="rounded-lg bg-emerald-500/5 px-2 py-2 text-center"><strong class="block text-sm text-emerald-400">{{ $productPublishedCount }}</strong><span class="text-[10px] text-slate-500">Yayında</span></div>
                                    <div class="rounded-lg bg-amber-500/5 px-2 py-2 text-center"><strong class="block text-sm text-amber-400">{{ $productPendingCount }}</strong><span class="text-[10px] text-slate-500">Bekliyor</span></div>
                                </div>

                                <div class="mt-3 max-h-20 space-y-1 overflow-y-auto pr-1">
                                    @forelse($product->branches as $branch)
                                        <div class="flex items-center justify-between gap-2 rounded-lg border border-slate-800 px-2.5 py-1.5 text-[11px]"><span class="min-w-0 truncate text-slate-300"><span class="mr-1 inline-block h-1.5 w-1.5 rounded-full {{ $branch->pivot->published_at ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>{{ $branch->name }}</span><span class="shrink-0 font-bold text-slate-400">₺{{ number_format((float)($branch->pivot->price_override ?? $product->base_price),2,',','.') }}</span></div>
                                    @empty
                                        <div class="rounded-lg border border-dashed border-slate-800 py-2 text-center text-[11px] text-slate-600">Henüz şube atanmamış</div>
                                    @endforelse
                                </div>

                                @if($canManage)
                                    <div class="mt-auto flex items-center gap-2 border-t border-slate-800 pt-4">
                                        <button type="button" onclick="openMenuModal('editProduct{{ $product->id }}')" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg border border-slate-700 py-2 text-xs font-bold text-slate-300 transition hover:bg-slate-800"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m4 20 4.5-1 10-10a2.1 2.1 0 0 0-3-3l-10 10L4 20Z"/></svg>Düzenle</button>
                                        @if($product->branches->isNotEmpty())
                                            <form class="flex-1" method="POST" action="{{ route('chain.menu.products.publish',$product) }}">@csrf @foreach($product->branches as $branch)<input type="hidden" name="branch_ids[]" value="{{ $branch->id }}">@endforeach<button class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 py-2 text-xs font-black text-slate-950 transition hover:bg-emerald-400"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5"/></svg>{{ $productPendingCount > 0 ? 'Yayınla' : 'Yeniden Yayınla' }}</button></form>
                                        @endif
                                    </div>
                                @endif
                            </article>

                            @if($canManage)
                                <div id="editProduct{{ $product->id }}" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-labelledby="editProductTitle{{ $product->id }}">
                                    <div data-modal-panel class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-4 shadow-2xl sm:p-6"><div class="mb-5 flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-cyan-500">Merkezi Menü</p><h3 id="editProductTitle{{ $product->id }}" class="font-black">Ürünü Düzenle</h3></div><button type="button" onclick="closeMenuModal('editProduct{{ $product->id }}')" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Kapat">✕</button></div>@include('chain.menu.partials.product-form',['action'=>route('chain.menu.products.update',$product),'method'=>'PUT','editingProduct'=>$product])</div>
                                </div>
                            @endif
                        @empty
                            <div class="col-span-full rounded-xl border border-dashed border-slate-800 p-6 text-center text-sm text-slate-600">Bu kategoride henüz ürün yok.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-700 p-12 text-center"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-cyan-400"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 6h16M4 12h12M4 18h8"/></svg></span><h2 class="mt-4 font-black text-slate-200">Menünüz henüz boş</h2><p class="mt-1 text-sm text-slate-500">İlk merkezi menü kategorisini oluşturarak başlayın.</p></div>
        @endforelse
    </div>

    <div id="menuFilteredEmpty" class="hidden rounded-2xl border border-dashed border-slate-700 p-10 text-center"><h2 class="font-black text-slate-300">Aramanızla eşleşen ürün bulunamadı</h2><p class="mt-1 text-sm text-slate-500">Filtreleri değiştirin veya temizleyin.</p><button type="button" onclick="clearMenuFilters()" class="mt-4 rounded-lg bg-slate-800 px-4 py-2 text-sm font-bold text-slate-200">Filtreleri Temizle</button></div>
</div>

@if($canManage)
    <div id="categoryModal" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle"><div data-modal-panel class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl"><div class="mb-5 flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-cyan-500">Merkezi Menü</p><h3 id="categoryModalTitle" class="font-black">Kategori Ekle</h3></div><button type="button" onclick="closeMenuModal('categoryModal')" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Kapat">✕</button></div><form method="POST" action="{{ route('chain.menu.categories.store') }}" class="space-y-4">@csrf<div><label class="mb-1 block text-xs text-slate-400">Kategori Adı</label><input name="name" required class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3"></div><div><label class="mb-1 block text-xs text-slate-400">Sıralama</label><input type="number" name="sort_order" min="0" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3"></div><button class="w-full rounded-xl bg-cyan-500 py-3 font-black text-slate-950">Oluştur</button></form></div></div>
    <div id="productModal" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-labelledby="productModalTitle"><div data-modal-panel class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-4 shadow-2xl sm:p-6"><div class="mb-5 flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-cyan-500">Merkezi Menü</p><h3 id="productModalTitle" class="font-black">Merkezi Ürün Ekle</h3></div><button type="button" onclick="closeMenuModal('productModal')" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Kapat">✕</button></div>@include('chain.menu.partials.product-form',['action'=>route('chain.menu.products.store'),'method'=>'POST','editingProduct'=>null])</div></div>
@endif

<script>
    function openMenuModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.querySelector('input:not([type="hidden"]), select, textarea')?.focus(), 50);
    }

    function closeMenuModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (!document.querySelector('.menu-modal.flex')) document.body.style.overflow = '';
    }

    function applyMenuFilters() {
        const query = (document.getElementById('menuSearch')?.value || '').toLocaleLowerCase('tr-TR').trim();
        const category = document.getElementById('menuCategoryFilter')?.value || '';
        const status = document.getElementById('menuStatusFilter')?.value || '';
        let visibleTotal = 0;

        document.querySelectorAll('[data-menu-category]').forEach(section => {
            let visibleInCategory = 0;
            section.querySelectorAll('[data-menu-product]').forEach(product => {
                const searchable = (product.dataset.search || '').toLocaleLowerCase('tr-TR');
                const matches = (!query || searchable.includes(query)) && (!category || product.dataset.categoryId === category) && (!status || product.dataset.status === status);
                product.classList.toggle('hidden', !matches);
                if (matches) visibleInCategory++;
            });
            section.classList.toggle('hidden', visibleInCategory === 0);
            const counter = section.querySelector('[data-visible-category-count]');
            if (counter) counter.textContent = visibleInCategory;
            visibleTotal += visibleInCategory;
        });

        const resultCount = document.getElementById('menuResultCount');
        if (resultCount) resultCount.textContent = visibleTotal;
        document.getElementById('menuFilteredEmpty')?.classList.toggle('hidden', visibleTotal !== 0 || {{ $totalProducts }} === 0);
    }

    function clearMenuFilters() {
        ['menuSearch', 'menuCategoryFilter', 'menuStatusFilter'].forEach(id => { const element = document.getElementById(id); if (element) element.value = ''; });
        applyMenuFilters();
    }

    document.addEventListener('DOMContentLoaded', () => {
        ['menuSearch', 'menuCategoryFilter', 'menuStatusFilter'].forEach(id => {
            const element = document.getElementById(id);
            element?.addEventListener(element.tagName === 'INPUT' ? 'input' : 'change', applyMenuFilters);
        });
        document.getElementById('menuClearFilters')?.addEventListener('click', clearMenuFilters);
        document.querySelectorAll('[data-category-toggle]').forEach(button => button.addEventListener('click', () => {
            const section = button.closest('[data-menu-category]');
            section.querySelector('[data-category-body]')?.classList.toggle('hidden');
            section.querySelector('[data-category-chevron]')?.classList.toggle('-rotate-90');
        }));
        document.querySelectorAll('.menu-modal').forEach(modal => modal.addEventListener('mousedown', event => {
            if (event.target === modal) closeMenuModal(modal.id);
        }));
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            const modal = document.querySelector('.menu-modal.flex');
            if (modal) closeMenuModal(modal.id);
        }
    });
</script>
@endsection
