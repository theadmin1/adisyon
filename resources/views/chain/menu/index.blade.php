@extends('chain.layout')
@section('title', 'Merkezi Menü')
@section('content')
@php
    $totalProducts = $categories->sum(fn ($category) => $category->products->count());
    $pendingProducts = $categories->sum(fn ($category) => $category->products->filter(fn ($product) => $product->branches->contains(fn ($branch) => !$branch->pivot->published_at))->count());
@endphp

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div><p class="institutional-page-kicker mb-1">Ürün ve Fiyat Yönetimi</p><h1>Merkezi Menü</h1><p class="mt-1 text-sm text-slate-500">Ürünleri düzenleyin ve şubelere yayınlayın.</p></div>
    @if($canManage)
        <div class="flex gap-2">
            <button type="button" onclick="openMenuModal('categoryModal')" class="institutional-action inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3.5 py-2.5 text-sm text-slate-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 6h16M4 12h10M4 18h7"/></svg>Kategori</button>
            <button type="button" onclick="openMenuModal('productModal')" @disabled($categories->isEmpty()) class="institutional-action inline-flex items-center gap-2 rounded-lg bg-cyan-500 px-3.5 py-2.5 text-sm font-bold text-slate-950 disabled:opacity-40"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 5v14M5 12h14"/></svg>Yeni Ürün</button>
        </div>
    @endif
</div>

@if($errors->any())<div class="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@unless($canManage)<div class="mb-4 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-300">Merkezi menüyü yalnızca Zincir Sahibi ve Genel Müdür değiştirebilir.</div>@endunless

@if($categories->isNotEmpty())
<section class="overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
    <div class="border-b border-slate-800 p-3 sm:p-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
            <label class="relative min-w-0 flex-1"><span class="sr-only">Ürün ara</span><svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="m21 21-4.5-4.5m2.5-5.5a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg><input id="menuSearch" type="search" placeholder="Ürün veya SKU ara" class="w-full rounded-lg border border-slate-700 bg-slate-950 py-2.5 pl-10 pr-3 text-sm outline-none focus:border-cyan-500"></label>
            <div class="flex items-center gap-3 text-xs text-slate-500"><span><strong class="text-slate-200">{{ $totalProducts }}</strong> ürün</span><span class="h-4 w-px bg-slate-700"></span><span class="{{ $pendingProducts ? 'text-amber-400' : 'text-slate-500' }}"><strong>{{ $pendingProducts }}</strong> yayın bekliyor</span></div>
        </div>
        <div id="categoryTabs" class="mt-3 flex gap-1 overflow-x-auto pb-1">
            <button type="button" data-category-tab="" class="menu-tab shrink-0 rounded-lg bg-cyan-500 px-3 py-2 text-xs font-bold text-slate-950">Tümü</button>
            @foreach($categories as $category)<button type="button" data-category-tab="{{ $category->id }}" class="menu-tab shrink-0 rounded-lg px-3 py-2 text-xs font-medium text-slate-400 hover:bg-slate-800 hover:text-slate-200">{{ $category->name }} <span class="ml-1 text-[10px] opacity-60">{{ $category->products->count() }}</span></button>@endforeach
        </div>
    </div>

    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="bg-slate-950/50 text-[11px] uppercase tracking-wide text-slate-500"><tr><th class="w-16 px-4 py-3"></th><th class="px-3 py-3">Ürün</th><th class="px-3 py-3">Kategori</th><th class="px-3 py-3 text-right">Merkez Fiyat</th><th class="px-3 py-3">Şubeler</th><th class="px-3 py-3">Durum</th><th class="w-32 px-4 py-3 text-right">İşlemler</th></tr></thead>
            <tbody class="divide-y divide-slate-800" id="menuDesktopRows">
            @foreach($categories as $category)
                @foreach($category->products as $product)
                    @php
                        $assigned = $product->branches->count();
                        $published = $product->branches->filter(fn ($branch) => (bool) $branch->pivot->published_at)->count();
                        $pending = $assigned - $published;
                        $searchText = implode(' ', [$product->name, $product->sku, $product->description, $category->name]);
                    @endphp
                    <tr data-menu-item data-category="{{ $category->id }}" data-search="{{ $searchText }}" class="hover:bg-slate-800/30">
                        <td class="px-4 py-3"><div class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-lg border border-slate-800 bg-slate-950">@if($product->image_path)<img src="{{ \Illuminate\Support\Str::startsWith($product->image_path,['http://','https://']) ? $product->image_path : asset($product->image_path) }}" alt="" class="h-full w-full object-cover">@else<svg class="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.5" d="m3 16 5-5 4 4 3-3 6 6M5 21h14V3H5v18Z"/></svg>@endif</div></td>
                        <td class="px-3 py-3"><p class="font-semibold text-slate-200">{{ $product->name }}</p><p class="mt-0.5 font-mono text-[11px] text-slate-500">{{ $product->sku }}</p></td>
                        <td class="px-3 py-3 text-xs text-slate-400">{{ $category->name }}</td>
                        <td class="px-3 py-3 text-right font-semibold">₺{{ number_format((float)$product->base_price,2,',','.') }}</td>
                        <td class="px-3 py-3"><button type="button" onclick="toggleBranchDetail('branches{{ $product->id }}')" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-cyan-400"><span>{{ $assigned }} şube</span><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m6 9 6 6 6-6"/></svg></button></td>
                        <td class="px-3 py-3">@if(!$product->is_active)<span class="inline-flex items-center gap-1.5 text-xs text-rose-400"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>Pasif</span>@elseif($assigned===0)<span class="inline-flex items-center gap-1.5 text-xs text-slate-500"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>Atanmadı</span>@elseif($pending>0)<span class="inline-flex items-center gap-1.5 text-xs text-amber-400"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>{{ $pending }} bekliyor</span>@else<span class="inline-flex items-center gap-1.5 text-xs text-emerald-400"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>Yayında</span>@endif</td>
                        <td class="px-4 py-3"><div class="flex justify-end gap-1">@if($canManage)<button type="button" onclick="openMenuModal('editProduct{{ $product->id }}')" title="Düzenle" class="rounded-lg p-2 text-slate-400 hover:bg-slate-700 hover:text-white"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="m4 20 4-1 11-11a2.1 2.1 0 0 0-3-3L5 16l-1 4Z"/></svg></button>@if($assigned)<form method="POST" action="{{ route('chain.menu.products.publish',$product) }}">@csrf @foreach($product->branches as $branch)<input type="hidden" name="branch_ids[]" value="{{ $branch->id }}">@endforeach<button title="Şubelere yayınla" class="rounded-lg p-2 {{ $pending ? 'text-amber-400 hover:bg-amber-500/10' : 'text-emerald-400 hover:bg-emerald-500/10' }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5"/></svg></button></form>@endif @endif</div></td>
                    </tr>
                    <tr id="branches{{ $product->id }}" data-detail-row class="hidden bg-slate-950/50"><td></td><td colspan="6" class="px-3 py-3"><div class="flex flex-wrap gap-2">@forelse($product->branches as $branch)<span class="rounded-md border border-slate-800 px-2.5 py-1.5 text-[11px] text-slate-400"><i class="mr-1 inline-block h-1.5 w-1.5 rounded-full {{ $branch->pivot->published_at ? 'bg-emerald-400' : 'bg-amber-400' }}"></i>{{ $branch->name }} · ₺{{ number_format((float)($branch->pivot->price_override ?? $product->base_price),2,',','.') }}</span>@empty<span class="text-xs text-slate-600">Şube ataması yok.</span>@endforelse</div></td></tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>

    <div id="menuMobileRows" class="divide-y divide-slate-800 md:hidden">
        @foreach($categories as $category)@foreach($category->products as $product)
            @php($assigned=$product->branches->count()) @php($published=$product->branches->filter(fn($branch)=>(bool)$branch->pivot->published_at)->count()) @php($pending=$assigned-$published)
            <article data-menu-item data-category="{{ $category->id }}" data-search="{{ implode(' ',[$product->name,$product->sku,$product->description,$category->name]) }}" class="p-4">
                <div class="flex gap-3"><div class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-800 bg-slate-950">@if($product->image_path)<img src="{{ \Illuminate\Support\Str::startsWith($product->image_path,['http://','https://'])?$product->image_path:asset($product->image_path) }}" alt="" class="h-full w-full object-cover">@else<svg class="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.5" d="m3 16 5-5 4 4 3-3 6 6M5 21h14V3H5v18Z"/></svg>@endif</div><div class="min-w-0 flex-1"><div class="flex justify-between gap-2"><div class="min-w-0"><h3 class="truncate font-semibold">{{ $product->name }}</h3><p class="text-[11px] text-slate-500">{{ $category->name }} · {{ $product->sku }}</p></div><strong class="shrink-0">₺{{ number_format((float)$product->base_price,2,',','.') }}</strong></div><div class="mt-2 flex items-center justify-between"><span class="text-xs {{ $pending ? 'text-amber-400' : ($assigned ? 'text-emerald-400' : 'text-slate-500') }}">{{ $pending ? $pending.' yayın bekliyor' : ($assigned ? $assigned.' şubede yayında' : 'Şube atanmadı') }}</span>@if($canManage)<div class="flex gap-1"><button type="button" onclick="openMenuModal('editProduct{{ $product->id }}')" class="rounded-md p-2 text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="m4 20 4-1 11-11a2.1 2.1 0 0 0-3-3L5 16l-1 4Z"/></svg></button>@if($assigned)<form method="POST" action="{{ route('chain.menu.products.publish',$product) }}">@csrf @foreach($product->branches as $branch)<input type="hidden" name="branch_ids[]" value="{{ $branch->id }}">@endforeach<button class="rounded-md p-2 text-emerald-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M12 16V4m0 0L7 9m5-5 5 5M5 14v5h14v-5"/></svg></button></form>@endif</div>@endif</div></div></div>
            </article>
        @endforeach @endforeach
    </div>
    <div id="menuEmpty" class="hidden p-10 text-center text-sm text-slate-500">Bu filtreye uygun ürün bulunamadı.</div>
</section>
@else
<div class="rounded-xl border border-dashed border-slate-700 p-12 text-center"><h2 class="font-semibold text-slate-300">Menü henüz boş</h2><p class="mt-1 text-sm text-slate-500">Önce bir kategori oluşturun.</p></div>
@endif

@if($canManage)
@foreach($categories as $category)@foreach($category->products as $product)
<div id="editProduct{{ $product->id }}" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-3 backdrop-blur-sm" role="dialog" aria-modal="true"><div class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-2xl sm:p-6"><div class="mb-5 flex items-center justify-between"><div><p class="text-xs text-cyan-500">{{ $product->sku }}</p><h3 class="font-semibold">{{ $product->name }}</h3></div><button type="button" onclick="closeMenuModal('editProduct{{ $product->id }}')" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800">✕</button></div>@include('chain.menu.partials.product-form',['action'=>route('chain.menu.products.update',$product),'method'=>'PUT','editingProduct'=>$product])</div></div>
@endforeach @endforeach
<div id="categoryModal" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true"><div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900 p-6"><div class="mb-5 flex justify-between"><h3 class="font-semibold">Yeni Kategori</h3><button type="button" onclick="closeMenuModal('categoryModal')">✕</button></div><form method="POST" action="{{ route('chain.menu.categories.store') }}" class="space-y-4">@csrf<label class="block"><span class="mb-1 block text-xs text-slate-400">Kategori Adı</span><input name="name" required class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3"></label><label class="block"><span class="mb-1 block text-xs text-slate-400">Sıralama</span><input type="number" name="sort_order" min="0" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3"></label><button class="w-full rounded-lg bg-cyan-500 py-3 font-bold text-slate-950">Oluştur</button></form></div></div>
<div id="productModal" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-3 backdrop-blur-sm" role="dialog" aria-modal="true"><div class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 p-4 sm:p-6"><div class="mb-5 flex justify-between"><h3 class="font-semibold">Yeni Ürün</h3><button type="button" onclick="closeMenuModal('productModal')">✕</button></div>@include('chain.menu.partials.product-form',['action'=>route('chain.menu.products.store'),'method'=>'POST','editingProduct'=>null])</div></div>
@endif

<script>
let activeMenuCategory='';
function openMenuModal(id){const e=document.getElementById(id);if(!e)return;e.classList.remove('hidden');e.classList.add('flex');document.body.style.overflow='hidden'}
function closeMenuModal(id){const e=document.getElementById(id);if(!e)return;e.classList.add('hidden');e.classList.remove('flex');if(!document.querySelector('.menu-modal.flex'))document.body.style.overflow=''}
function toggleBranchDetail(id){document.getElementById(id)?.classList.toggle('hidden')}
function filterMenu(){const q=(document.getElementById('menuSearch')?.value||'').toLocaleLowerCase('tr-TR').trim();let visible=0;document.querySelectorAll('[data-detail-row]').forEach(row=>row.classList.add('hidden'));document.querySelectorAll('[data-menu-item]').forEach(item=>{const match=(!activeMenuCategory||item.dataset.category===activeMenuCategory)&&(!q||(item.dataset.search||'').toLocaleLowerCase('tr-TR').includes(q));item.classList.toggle('hidden',!match);if(match&&item.closest('#menuDesktopRows'))visible++});document.getElementById('menuEmpty')?.classList.toggle('hidden',visible>0)}
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('menuSearch')?.addEventListener('input',filterMenu);document.querySelectorAll('[data-category-tab]').forEach(tab=>tab.addEventListener('click',()=>{activeMenuCategory=tab.dataset.categoryTab;document.querySelectorAll('.menu-tab').forEach(x=>{x.classList.remove('bg-cyan-500','text-slate-950','font-bold');x.classList.add('text-slate-400')});tab.classList.add('bg-cyan-500','text-slate-950','font-bold');tab.classList.remove('text-slate-400');filterMenu()}));document.querySelectorAll('.menu-modal').forEach(modal=>modal.addEventListener('mousedown',e=>{if(e.target===modal)closeMenuModal(modal.id)}))});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const modal=document.querySelector('.menu-modal.flex');if(modal)closeMenuModal(modal.id)}});
</script>
@endsection
