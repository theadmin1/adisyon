<form method="POST" action="{{ $action }}" class="space-y-5">@csrf @if($method!=='POST') @method($method) @endif
<div class="grid gap-4 md:grid-cols-2">
    <div><label class="mb-1 block text-xs text-slate-400">Kategori</label><select name="chain_menu_category_id" required class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5">@foreach($categories as $categoryOption)<option value="{{ $categoryOption->id }}" @selected($editingProduct?->chain_menu_category_id===$categoryOption->id)>{{ $categoryOption->name }}</option>@endforeach</select></div>
    <div><label class="mb-1 block text-xs text-slate-400">SKU / Ürün Kodu</label><input name="sku" required value="{{ $editingProduct?->sku }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 uppercase"></div>
    <div><label class="mb-1 block text-xs text-slate-400">Ürün Adı</label><input name="name" required value="{{ $editingProduct?->name }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5"></div>
    <div><label class="mb-1 block text-xs text-slate-400">Mutfak Departmanı</label><input name="kitchen_department" value="{{ $editingProduct?->kitchen_department }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5"></div>
    <div><label class="mb-1 block text-xs text-slate-400">Merkez Fiyatı</label><input type="number" step="0.01" min="0" name="base_price" required value="{{ $editingProduct?->base_price }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5"></div>
    <div><label class="mb-1 block text-xs text-slate-400">İndirimli Fiyat</label><input type="number" step="0.01" min="0" name="discounted_price" value="{{ $editingProduct?->discounted_price }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5"></div>
</div>
<div><label class="mb-1 block text-xs text-slate-400">Görsel URL</label><input type="url" name="image_path" value="{{ $editingProduct?->image_path }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5"></div>
<div><label class="mb-1 block text-xs text-slate-400">Açıklama</label><textarea name="description" rows="2" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5">{{ $editingProduct?->description }}</textarea></div>
<label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($editingProduct?->is_active ?? true)> Ürün merkezde aktif</label>
<div><p class="mb-1 text-xs font-bold text-slate-300">Şube Ataması ve Fiyat İstisnaları</p><p class="mb-3 text-[11px] text-slate-500">Şubeyi seçin; fiyat boşsa merkez fiyatı kullanılır. “Satışta” kapalıysa ürün şubede pasif yayınlanır.</p><div class="space-y-2 rounded-xl border border-slate-800 bg-slate-950 p-3">
@foreach($branches as $branch) @php($assignment=$editingProduct?->branches->firstWhere('id',$branch->id)?->pivot)
<div class="grid grid-cols-[auto_1fr_120px_auto] items-center gap-3 rounded-lg border border-slate-800 p-3"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked((bool)$assignment)><div><strong class="block text-sm">{{ $branch->name }}</strong><span class="text-[11px] text-slate-600">{{ $branch->code }}</span></div><input type="number" step="0.01" min="0" name="price_overrides[{{ $branch->id }}]" value="{{ $assignment?->price_override }}" placeholder="Özel fiyat" class="w-full rounded-lg border border-slate-700 bg-slate-900 p-2 text-xs"><label class="flex gap-1 text-xs text-slate-400"><input type="checkbox" name="enabled_branch_ids[]" value="{{ $branch->id }}" @checked($assignment?->is_enabled ?? true)> Satışta</label></div>
@endforeach
</div></div>
<button class="w-full rounded-xl bg-cyan-500 py-3 font-black text-slate-950">Taslağı Kaydet</button>
</form>
