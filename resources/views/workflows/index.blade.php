@extends('layouts.app')
@section('title', 'Üretim İş Akışı')
@section('content')
@php
    $statusMap=['planned'=>['Planlandı','text-sky-300 bg-sky-500/10 border-sky-500/20'],'in_progress'=>['Üretimde','text-amber-300 bg-amber-500/10 border-amber-500/20'],'completed'=>['Tamamlandı','text-emerald-300 bg-emerald-500/10 border-emerald-500/20'],'cancelled'=>['İptal','text-rose-300 bg-rose-500/10 border-rose-500/20']];
@endphp
<div class="min-h-screen bg-[#07090e] text-slate-100">
    <header class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-[#0f131f]/95 px-4 py-3 sm:px-8">
        <div class="flex items-center gap-4"><a href="{{ route('dashboard') }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-slate-300 hover:text-white"><i class="fi fi-rr-arrow-left"></i></a><div><h1 class="flex items-center gap-2 text-lg font-black"><i class="fi fi-rr-process text-violet-400"></i>Üretim İş Akışı</h1><p class="text-xs text-slate-400">Reçeteden porsiyon planlama, hammadde tüketimi ve kalan stok kontrolü</p></div></div>
        <div class="rounded-xl border border-violet-500/20 bg-violet-500/10 px-4 py-2 text-xs text-violet-200">Stok düşümü yalnızca <strong>Üretimi Tamamla</strong> işleminde yapılır.</div>
    </header>
    <main class="space-y-6 p-4 sm:p-8">
        @if(false && $errors->any())<div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        @if(false && session('status'))<div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-300">{{ session('status') }}</div>@endif

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach([['Aktif Reçete',$stats['active_recipes'],'text-violet-300'],['Planlanan',$stats['planned'],'text-sky-300'],['Üretimde',$stats['in_progress'],'text-amber-300'],['Bugün Tamamlanan',$stats['completed_today'],'text-emerald-300']] as [$label,$value,$color])
                <div class="rounded-2xl border border-slate-800 bg-[#111524] p-5"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-black {{ $color }}">{{ $value }}</p></div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5">
                <div class="mb-5"><h2 class="font-black">Yeni Reçete Tanımla</h2><p class="text-xs text-slate-500">Örn. 10 porsiyon kuru fasulye için kullanılan toplam malzemeleri girin.</p></div>
                <form method="POST" action="{{ route('workflows.recipes.store') }}" class="space-y-4">@csrf
                    <div class="grid gap-3 sm:grid-cols-3"><label class="text-xs text-slate-400 sm:col-span-1">Reçete Adı<input name="name" required value="{{ old('name') }}" placeholder="Kuru Fasulye" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm text-white"></label><label class="text-xs text-slate-400">Üretilen Menü Ürünü<select name="output_product_id" required class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm"><option value="">Seçin</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->unit }})</option>@endforeach</select></label><label class="text-xs text-slate-400">Temel Porsiyon<input name="base_servings" required type="number" min="0.001" step="0.001" value="{{ old('base_servings',10) }}" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm"></label></div>
                    <div><div class="mb-2 flex items-center justify-between"><span class="text-xs font-bold text-slate-300">Hammaddeler</span><button type="button" onclick="addIngredient()" class="rounded-lg bg-violet-500/10 px-3 py-1.5 text-xs font-bold text-violet-300">+ Malzeme Ekle</button></div><div id="ingredientRows" class="space-y-2"></div></div>
                    <label class="block text-xs text-slate-400">Hazırlama Notu<textarea name="instructions" rows="2" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm" placeholder="Islatma, pişirme veya porsiyonlama notları..."></textarea></label>
                    <button class="w-full rounded-xl bg-violet-600 px-4 py-3 text-sm font-black text-white hover:bg-violet-500">Reçeteyi Kaydet</button>
                </form>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5">
                <div class="mb-5"><h2 class="font-black">Yeni Üretim Planla</h2><p class="text-xs text-slate-500">Şefin hazırlayacağı porsiyon miktarını girin.</p></div>
                <form method="POST" action="{{ route('workflows.store') }}" class="space-y-4">@csrf
                    <label class="block text-xs text-slate-400">Reçete<select name="production_recipe_id" required class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm"><option value="">Reçete seçin</option>@foreach($recipes as $recipe)<option value="{{ $recipe->id }}">{{ $recipe->name }} · {{ number_format((float)$recipe->base_servings,0) }} porsiyon baz</option>@endforeach</select></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="text-xs text-slate-400">Üretilecek Porsiyon<input name="planned_servings" required type="number" min="0.001" step="0.001" value="{{ old('planned_servings',100) }}" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm"></label><label class="text-xs text-slate-400">Planlanan Zaman<input name="scheduled_for" type="datetime-local" value="{{ old('scheduled_for',now()->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm"></label></div>
                    <label class="block text-xs text-slate-400">İş Emri Notu<textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-700 bg-slate-950 p-3 text-sm" placeholder="Servis saati, kazan veya ekip bilgisi..."></textarea></label>
                    <button class="w-full rounded-xl bg-cyan-600 px-4 py-3 text-sm font-black text-white hover:bg-cyan-500" @disabled($recipes->isEmpty())>İş Akışını Planla</button>
                </form>
                <div class="mt-5 border-t border-slate-800 pt-4"><h3 class="mb-3 text-xs font-black uppercase tracking-wider text-slate-500">Tanımlı Reçeteler</h3><div class="max-h-56 space-y-2 overflow-y-auto">@forelse($recipes as $recipe)<div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3"><div class="flex justify-between"><strong class="text-sm">{{ $recipe->name }}</strong><span class="text-xs text-violet-300">{{ number_format((float)$recipe->base_servings,0) }} porsiyon</span></div><p class="mt-2 text-xs text-slate-500">@foreach($recipe->items as $item){{ $item->ingredient?->name }}: {{ number_format((float)$item->quantity,3,',','.') }} {{ $item->unit }}@if(!$loop->last) · @endif @endforeach</p></div>@empty<p class="text-xs text-slate-500">Önce bir reçete oluşturun.</p>@endforelse</div></div>
            </section>
        </div>

        <section><div class="mb-4"><h2 class="text-lg font-black">Üretim İş Emirleri</h2><p class="text-xs text-slate-500">Gerekli, mevcut ve üretim sonrası kalan hammadde miktarları</p></div><div class="space-y-4">
            @forelse($workflows as $workflow)
                @php([$statusLabel,$statusClass]=$statusMap[$workflow->status]??[$workflow->status,'text-slate-300 bg-slate-800 border-slate-700'])
                <article class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524]">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-800 p-5"><div><div class="flex flex-wrap items-center gap-2"><h3 class="font-black">{{ $workflow->recipe_name }}</h3><span class="rounded-full border px-2.5 py-1 text-[10px] font-black {{ $statusClass }}">{{ $statusLabel }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $workflow->workflow_number }} · <strong class="text-slate-300">{{ number_format((float)$workflow->planned_servings,0) }} porsiyon</strong> · {{ $workflow->scheduled_for?->format('d.m.Y H:i') ?? 'Zaman belirtilmedi' }}</p></div>
                        @if(in_array($workflow->status,['planned','in_progress']))<div class="flex gap-2">@if($workflow->status==='planned')<form method="POST" action="{{ route('workflows.start',$workflow) }}">@csrf<button class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs font-bold text-amber-300">Üretimi Başlat</button></form>@endif<form method="POST" action="{{ route('workflows.complete',$workflow) }}" onsubmit="return confirm('Hammaddeler stoktan düşülecek. Üretim tamamlandı mı?')">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white">Üretimi Tamamla</button></form><form method="POST" action="{{ route('workflows.cancel',$workflow) }}">@csrf<button class="rounded-lg px-3 py-2 text-xs font-bold text-rose-300">İptal</button></form></div>@endif
                    </div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[680px] text-left text-xs"><thead class="bg-slate-950/50 uppercase text-slate-500"><tr><th class="p-3 pl-5">Hammadde</th><th class="p-3">Gereken</th><th class="p-3">İşlem Öncesi / Mevcut</th><th class="p-3">Üretim Sonrası Kalan</th><th class="p-3">Durum</th></tr></thead><tbody class="divide-y divide-slate-800">
                        @if($workflow->items->isNotEmpty())@foreach($workflow->items as $item)<tr><td class="p-3 pl-5 font-bold">{{ $item->product_name }}</td><td class="p-3">{{ number_format((float)$item->consumed_quantity,3,',','.') }} {{ $item->stock_unit }}</td><td class="p-3">{{ number_format((float)$item->stock_before,3,',','.') }} {{ $item->stock_unit }}</td><td class="p-3 text-emerald-300">{{ number_format((float)$item->stock_after,3,',','.') }} {{ $item->stock_unit }}</td><td class="p-3 text-emerald-300">Düşüldü</td></tr>@endforeach
                        @else @foreach($workflow->preview_requirements as $requirement)<tr><td class="p-3 pl-5 font-bold">{{ $requirement->product->name }}</td><td class="p-3">{{ number_format($requirement->required,3,',','.') }} {{ $requirement->unit }}</td><td class="p-3">{{ number_format($requirement->available,3,',','.') }} {{ $requirement->unit }}</td><td class="p-3 {{ $requirement->sufficient?'text-emerald-300':'text-rose-300' }}">{{ number_format($requirement->available-$requirement->required,3,',','.') }} {{ $requirement->unit }}</td><td class="p-3 {{ $requirement->sufficient?'text-emerald-300':'text-rose-300' }}">{{ $requirement->sufficient?'Yeterli':'Eksik stok' }}</td></tr>@endforeach @endif
                    </tbody></table></div>
                </article>
            @empty<div class="rounded-3xl border border-dashed border-slate-700 p-12 text-center text-sm text-slate-500">Henüz üretim iş akışı planlanmadı.</div>@endforelse
        </div><div class="mt-5">{{ $workflows->links() }}</div></section>
    </main>
</div>

<template id="ingredientTemplate"><div class="ingredient-row grid grid-cols-12 gap-2"><select name="items[__INDEX__][product_id]" required class="col-span-6 rounded-xl border border-slate-700 bg-slate-950 p-2.5 text-xs"><option value="">Hammadde seçin</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id }}">{{ $ingredient->name }} · {{ number_format((float)$ingredient->stock_quantity,2,',','.') }} {{ $ingredient->unit }}</option>@endforeach</select><input name="items[__INDEX__][quantity]" required type="number" min="0.0001" step="0.0001" placeholder="Miktar" class="col-span-3 rounded-xl border border-slate-700 bg-slate-950 p-2.5 text-xs"><select name="items[__INDEX__][unit]" class="col-span-2 rounded-xl border border-slate-700 bg-slate-950 p-2.5 text-xs"><option>g</option><option>kg</option><option>ml</option><option>l</option><option>adet</option><option>porsiyon</option></select><button type="button" onclick="this.closest('.ingredient-row').remove()" class="col-span-1 text-rose-400" aria-label="Malzemeyi sil">×</button></div></template>
<script>let ingredientIndex=0;function addIngredient(){const html=document.getElementById('ingredientTemplate').innerHTML.replaceAll('__INDEX__',ingredientIndex++);document.getElementById('ingredientRows').insertAdjacentHTML('beforeend',html)}addIngredient();</script>
@endsection
