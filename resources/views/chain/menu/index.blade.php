@extends('chain.layout')
@section('title', 'Merkezi Menü')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div><p class="institutional-page-kicker mb-1">Ürün ve Fiyat Yönetimi</p><h1>Merkezi Menü</h1><p class="mt-1 text-sm text-slate-400">Ürün, kategori ve şube fiyatlarının merkezi yönetimi</p></div>
        @if($canManage)<div class="flex gap-2"><button onclick="openMenuModal('categoryModal')" class="institutional-action rounded-lg border border-cyan-500/30 px-4 py-2 text-sm text-cyan-300">Kategori Tanımla</button><button onclick="openMenuModal('productModal')" @disabled($categories->isEmpty()) class="institutional-action rounded-lg bg-cyan-500 px-4 py-2 text-sm text-slate-950 disabled:opacity-40">Ürün Tanımla</button></div>@endif
    </div>

    @if($errors->any())<div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @unless($canManage)<div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-300">Rolünüz görüntüleme yetkisine sahip. Merkezi menüyü yalnızca Zincir Sahibi ve Genel Müdür değiştirebilir.</div>@endunless

    @forelse($categories as $category)
    <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4"><div><h2 class="font-black">{{ $category->name }}</h2><p class="text-xs text-slate-500">{{ $category->products->count() }} merkezi ürün</p></div><span class="rounded-full px-2 py-1 text-xs {{ $category->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">{{ $category->is_active ? 'Aktif' : 'Pasif' }}</span></div>
        <div class="grid gap-4 p-4 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse($category->products as $product)
            <article class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                @if($product->image_path)<div class="mb-4 aspect-[16/10] overflow-hidden rounded-xl border border-slate-800"><img src="{{ \Illuminate\Support\Str::startsWith($product->image_path,['http://','https://'])?$product->image_path:asset($product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy"></div>@endif
                <div class="flex justify-between gap-3"><div><h3 class="font-bold">{{ $product->name }}</h3><p class="font-mono text-xs text-cyan-500">{{ $product->sku }}</p></div><p class="text-lg font-black text-emerald-400">₺{{ number_format((float)$product->base_price,2,',','.') }}</p></div>
                <p class="mt-3 line-clamp-2 text-xs text-slate-500">{{ $product->description ?: 'Açıklama bulunmuyor.' }}</p>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @forelse($product->branches as $branch)
                    <span class="rounded-lg border px-2 py-1 text-[11px] {{ $branch->pivot->published_at ? 'border-emerald-500/20 bg-emerald-500/5 text-emerald-300' : 'border-amber-500/20 bg-amber-500/5 text-amber-300' }}">{{ $branch->name }} · ₺{{ number_format((float)($branch->pivot->price_override ?? $product->base_price),2,',','.') }}</span>
                    @empty<span class="text-xs text-slate-600">Henüz şube atanmamış</span>@endforelse
                </div>
                @if($canManage)<div class="mt-4 flex gap-2 border-t border-slate-800 pt-4"><button onclick="openMenuModal('editProduct{{ $product->id }}')" class="flex-1 rounded-lg border border-slate-700 py-2 text-xs font-bold text-slate-300">Düzenle</button>@if($product->branches->isNotEmpty())<form class="flex-1" method="POST" action="{{ route('chain.menu.products.publish',$product) }}">@csrf @foreach($product->branches as $branch)<input type="hidden" name="branch_ids[]" value="{{ $branch->id }}">@endforeach<button class="w-full rounded-lg bg-emerald-500 py-2 text-xs font-black text-slate-950">Şubelere Yayınla</button></form>@endif</div>@endif
            </article>
            @if($canManage)<div id="editProduct{{ $product->id }}" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4"><div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-6"><div class="mb-5 flex justify-between"><h3 class="font-black">Ürünü Düzenle</h3><button onclick="closeMenuModal('editProduct{{ $product->id }}')">✕</button></div>@include('chain.menu.partials.product-form',['action'=>route('chain.menu.products.update',$product),'method'=>'PUT','editingProduct'=>$product])</div></div>@endif
            @empty<div class="col-span-full p-6 text-center text-sm text-slate-600">Bu kategoride ürün yok.</div>@endforelse
        </div>
    </section>
    @empty<div class="rounded-2xl border border-dashed border-slate-700 p-12 text-center text-slate-500">İlk merkezi menü kategorisini oluşturun.</div>@endforelse
</div>

@if($canManage)
<div id="categoryModal" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4"><div class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6"><div class="mb-5 flex justify-between"><h3 class="font-black">Kategori Ekle</h3><button onclick="closeMenuModal('categoryModal')">✕</button></div><form method="POST" action="{{ route('chain.menu.categories.store') }}" class="space-y-4">@csrf<div><label class="mb-1 block text-xs text-slate-400">Kategori Adı</label><input name="name" required class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3"></div><div><label class="mb-1 block text-xs text-slate-400">Sıralama</label><input type="number" name="sort_order" min="0" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3"></div><button class="w-full rounded-xl bg-cyan-500 py-3 font-black text-slate-950">Oluştur</button></form></div></div>
<div id="productModal" class="menu-modal fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4"><div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-6"><div class="mb-5 flex justify-between"><h3 class="font-black">Merkezi Ürün Ekle</h3><button onclick="closeMenuModal('productModal')">✕</button></div>@include('chain.menu.partials.product-form',['action'=>route('chain.menu.products.store'),'method'=>'POST','editingProduct'=>null])</div></div>
@endif
<script>function openMenuModal(id){const e=document.getElementById(id);e.classList.remove('hidden');e.classList.add('flex')}function closeMenuModal(id){const e=document.getElementById(id);e.classList.add('hidden');e.classList.remove('flex')}</script>
@endsection
