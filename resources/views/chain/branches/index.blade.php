@extends('chain.layout')
@section('title', 'Şubeler')
@section('content')
@php($canManage=auth()->user()->chain_role!=='analyst')
<div class="mb-7"><p class="institutional-page-kicker mb-1">Organizasyon Yönetimi</p><h1>Şube Durumları</h1><p class="mt-1 text-sm text-slate-400">Operasyon, stok, personel, cihaz ve masa düzeninin kurumsal özeti</p></div>
@if(session('success'))<div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-400">{{ $errors->first() }}</div>@endif
<div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
@forelse($branches as $branch)
    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <div class="flex items-start justify-between">
            <div><h2 class="text-lg font-black">{{ $branch->name }}</h2><p class="font-mono text-xs text-cyan-400">{{ $branch->code }}</p></div>
            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $branch->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">{{ $branch->is_active ? 'Aktif' : 'Pasif' }}</span>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Bugünkü Ciro</p><p class="mt-1 font-black text-emerald-400">₺{{ number_format((float) ($todaySales[$branch->id] ?? 0), 2, ',', '.') }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Açık Adisyon</p><p class="mt-1 font-black text-amber-400">{{ $openChecks[$branch->id] ?? 0 }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Online Cihaz</p><p class="mt-1 font-black text-cyan-400">{{ $onlineDevices[$branch->id] ?? 0 }} / {{ $branch->devices_count }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Kritik Stok</p><p class="mt-1 font-black {{ ($lowStocks[$branch->id] ?? 0) > 0 ? 'text-rose-400' : 'text-slate-300' }}">{{ $lowStocks[$branch->id] ?? 0 }}</p></div>
        </div>
        <div class="mt-4 grid grid-cols-4 gap-2 border-t border-slate-800 pt-4 text-center text-xs text-slate-400"><span>{{ $branch->products_count }}<small class="block text-[9px] text-slate-600">ürün</small></span><span>{{ $branch->staff_profiles_count }}<small class="block text-[9px] text-slate-600">personel</small></span><span>{{ $branch->dining_tables_count }}<small class="block text-[9px] text-slate-600">masa</small></span><span class="{{ ($openShifts[$branch->id] ?? 0)?'text-emerald-400':'' }}">{{ ($openShifts[$branch->id] ?? 0) ? 'Açık' : 'Kapalı' }}<small class="block text-[9px] text-slate-600">kasa</small></span></div>
        <button type="button" onclick="openTableManager({{ $branch->id }})" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-cyan-500/25 bg-cyan-500/5 px-4 py-2.5 text-xs font-bold text-cyan-400 hover:bg-cyan-500/10"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 11h16M6 11v9m12-9v9M8 4h8l2 7H6l2-7Z"/></svg>Masaları Yönet <span class="rounded-full bg-slate-800 px-2 py-0.5 text-[10px]">{{ $branch->dining_tables_count }}</span></button>
    </article>
@empty
    <div class="col-span-full rounded-2xl border border-dashed border-slate-700 p-12 text-center text-slate-500">Erişebileceğiniz şube bulunmuyor.</div>
@endforelse
</div>

@foreach($branches as $branch)
<div id="tableManager{{ $branch->id }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl sm:p-6">
        <div class="mb-5 flex items-start justify-between"><div><p class="institutional-page-kicker mb-1">{{ $branch->code }}</p><h3 class="text-lg font-black">{{ $branch->name }} · Masa Yönetimi</h3><p class="mt-1 text-xs text-slate-500">Şubedeki salon ve masaları merkezden yönetin.</p></div><button type="button" onclick="closeTableManager({{ $branch->id }})" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800">✕</button></div>

        @if($canManage)
        <section class="mb-5 rounded-xl border border-violet-500/20 bg-violet-500/5 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><strong class="text-sm text-violet-200">Masa Kategorileri</strong><p class="mt-0.5 text-[11px] text-slate-500">İç Mekân, Bahçe, Teras veya VIP gibi masa gruplarını yönetin.</p></div>
                <span class="text-xs text-violet-300">{{ $branch->halls->count() }} kategori</span>
            </div>
            <form method="POST" action="{{ route('chain.branches.table-categories.store',$branch) }}" class="mt-4 grid gap-2 sm:grid-cols-[minmax(0,1fr)_160px_auto]">
                @csrf
                <input type="hidden" name="form_context" value="table_{{ $branch->id }}">
                <input name="name" required maxlength="100" placeholder="Kategori adı · Örn. Bahçe" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 text-sm">
                <input name="code" maxlength="50" placeholder="Kod · BAHCE" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 text-sm">
                <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2.5 text-xs font-black text-white hover:bg-violet-500"><span class="text-base leading-none">+</span>Kategori Ekle</button>
            </form>
            @if($branch->halls->isNotEmpty())
            <div class="mt-4 space-y-2 border-t border-slate-800 pt-4">
                @foreach($branch->halls as $hall)
                @php($hallTableCount=$branch->diningTables->where('hall_id',$hall->id)->count())
                <div class="flex items-center gap-2 rounded-lg border border-slate-800 bg-slate-950 p-2">
                    <form method="POST" action="{{ route('chain.branches.table-categories.update',[$branch,$hall]) }}" class="grid min-w-0 flex-1 gap-2 sm:grid-cols-[minmax(0,1fr)_130px_auto]">
                        @csrf @method('PUT')
                        <input type="hidden" name="form_context" value="table_{{ $branch->id }}">
                        <input name="name" value="{{ $hall->name }}" required maxlength="100" aria-label="Kategori adı" class="min-w-0 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 text-xs">
                        <input name="code" value="{{ $hall->code }}" maxlength="50" aria-label="Kategori kodu" placeholder="Kod" class="min-w-0 rounded-lg border border-slate-800 bg-slate-900 px-3 py-2 font-mono text-xs">
                        <button title="Kategoriyi güncelle" class="rounded-lg border border-cyan-500/25 px-3 py-2 text-xs font-bold text-cyan-400 hover:bg-cyan-500/10">Kaydet</button>
                    </form>
                    <span class="hidden whitespace-nowrap text-[10px] text-slate-500 md:inline">{{ $hallTableCount }} masa</span>
                    <form method="POST" action="{{ route('chain.branches.table-categories.destroy',[$branch,$hall]) }}" onsubmit="return confirm('{{ $hall->name }} kategorisi silinsin mi?')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="form_context" value="table_{{ $branch->id }}">
                        <button title="Kategoriyi sil" @disabled($hallTableCount>0) class="rounded-lg border border-rose-500/25 p-2 text-rose-400 hover:bg-rose-500/10 disabled:cursor-not-allowed disabled:opacity-30"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 14h10l1-14"/></svg></button>
                    </form>
                </div>
                @endforeach
            </div>
            @endif
        </section>
        <form method="POST" action="{{ route('chain.branches.tables.store',$branch) }}" class="mb-5 space-y-4 rounded-xl border border-slate-800 bg-slate-950 p-4">@csrf<input type="hidden" name="form_context" value="table_{{ $branch->id }}"><div class="flex items-center justify-between"><div><strong class="text-sm">Yeni Masa Ekle</strong><p class="mt-0.5 text-[11px] text-slate-500">Mevcut salonu seçin veya yeni salon adı girin.</p></div><span class="text-xs text-cyan-500">{{ $branch->dining_tables_count }} masa</span></div><div class="grid gap-3 sm:grid-cols-2"><div><label class="mb-1 block text-xs text-slate-400">Masa Adı</label><input name="name" required maxlength="100" placeholder="Örn. Masa 12" class="w-full rounded-lg border border-slate-700 bg-slate-900 p-3 text-sm"></div><div><label class="mb-1 block text-xs text-slate-400">Masa Kodu</label><input name="code" maxlength="50" placeholder="Örn. M12" class="w-full rounded-lg border border-slate-700 bg-slate-900 p-3 text-sm"></div></div><div class="grid gap-3 sm:grid-cols-3"><div><label class="mb-1 block text-xs text-slate-400">Mevcut Salon</label><select name="hall_id" class="w-full rounded-lg border border-slate-700 bg-slate-900 p-3 text-sm"><option value="">Salonsuz Alan</option>@foreach($branch->halls as $hall)<option value="{{ $hall->id }}">{{ $hall->name }}</option>@endforeach</select></div><div><label class="mb-1 block text-xs text-slate-400">Yeni Salon <span class="text-slate-600">(opsiyonel)</span></label><input name="new_hall_name" maxlength="100" placeholder="Örn. Teras" class="w-full rounded-lg border border-slate-700 bg-slate-900 p-3 text-sm"></div><div><label class="mb-1 block text-xs text-slate-400">Kapasite</label><input name="capacity" type="number" min="1" max="100" value="4" required class="w-full rounded-lg border border-slate-700 bg-slate-900 p-3 text-sm"></div></div><div class="flex items-end gap-3"><div class="flex-1"><label class="mb-1 block text-xs text-slate-400">Not</label><input name="notes" maxlength="500" placeholder="Konum veya kullanım notu" class="w-full rounded-lg border border-slate-700 bg-slate-900 p-3 text-sm"></div><button class="inline-flex items-center gap-2 rounded-lg bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 5v14M5 12h14"/></svg>Masa Ekle</button></div></form>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-800">
            <div class="max-h-80 overflow-auto">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="sticky top-0 bg-slate-950 text-[10px] uppercase text-slate-500">
                        <tr><th class="p-3">Masa</th><th class="p-3">Salon</th><th class="p-3">Kapasite</th><th class="p-3">Durum</th>@if($canManage)<th class="p-3 text-right">İşlem</th>@endif</tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                    @forelse($branch->diningTables as $table)
                        <tr>
                            <td class="p-3"><strong>{{ $table->name }}</strong><small class="block font-mono text-slate-500">{{ $table->code ?: 'Kod yok' }}</small></td>
                            <td class="p-3 text-slate-400">{{ $table->hall?->name ?? 'Salonsuz Alan' }}</td>
                            <td class="p-3">{{ $table->capacity }} kişi</td>
                            <td class="p-3"><span class="inline-flex items-center gap-1.5 text-xs {{ $table->is_active ? 'text-emerald-400' : 'text-slate-500' }}"><i class="h-1.5 w-1.5 rounded-full bg-current"></i>{{ $table->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                            @if($canManage)
                            <td class="p-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" title="Düzenle" onclick="openTableEditor({{ $branch->id }}, {{ $table->id }})" class="rounded-lg border border-cyan-500/25 p-2 text-cyan-400 hover:bg-cyan-500/10">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 20h4L19 9l-4-4L4 16v4Zm9.5-13.5 4 4"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('chain.branches.tables.toggle', [$branch, $table]) }}">@csrf @method('PATCH')<button title="{{ $table->is_active ? 'Pasife al' : 'Aktifleştir' }}" class="rounded-lg border border-slate-700 p-2 {{ $table->is_active ? 'text-amber-400' : 'text-emerald-400' }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M12 3v9m6.4-6.4a9 9 0 1 1-12.8 0"/></svg></button></form>
                                    <form method="POST" action="{{ route('chain.branches.tables.destroy', [$branch, $table]) }}" onsubmit="return confirm('{{ $table->name }} silinsin mi?')">@csrf @method('DELETE')<button title="Sil" class="rounded-lg border border-rose-500/25 p-2 text-rose-400 hover:bg-rose-500/10"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 14h10l1-14"/></svg></button></form>
                                </div>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">Bu şubede henüz masa yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@if($canManage)
    @foreach($branch->diningTables as $table)
    @php($editingThisTable = old('form_context') === "table_{$branch->id}_edit_{$table->id}")
    <div id="tableEditor{{ $branch->id }}_{{ $table->id }}" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/80 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="tableEditorTitle{{ $table->id }}" onclick="if(event.target===this) closeTableEditor({{ $branch->id }}, {{ $table->id }})">
        <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div><p class="institutional-page-kicker mb-1">{{ $branch->name }}</p><h3 id="tableEditorTitle{{ $table->id }}" class="text-lg font-black">{{ $table->name }} · Masayı Düzenle</h3><p class="mt-1 text-xs text-slate-500">Masa adı, kodu, kategorisi, kapasitesi ve notunu güncelleyin.</p></div>
                <button type="button" onclick="closeTableEditor({{ $branch->id }}, {{ $table->id }})" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800" aria-label="Düzenleme penceresini kapat">✕</button>
            </div>
            <form method="POST" action="{{ route('chain.branches.tables.update', [$branch, $table]) }}" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="form_context" value="table_{{ $branch->id }}_edit_{{ $table->id }}">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="mb-1 block text-xs text-slate-400">Masa Adı</label><input name="name" value="{{ $editingThisTable ? old('name') : $table->name }}" required maxlength="100" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm"></div>
                    <div><label class="mb-1 block text-xs text-slate-400">Masa Kodu</label><input name="code" value="{{ $editingThisTable ? old('code') : $table->code }}" maxlength="50" placeholder="Kod kullanmak istemiyorsanız boş bırakın" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm"></div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-slate-400">Masa Kategorisi</label>
                        <select name="hall_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm">
                            @php($selectedHallId = $editingThisTable ? old('hall_id') : $table->hall_id)
                            <option value="" @selected(blank($selectedHallId))>Salonsuz Alan</option>
                            @foreach($branch->halls as $hall)<option value="{{ $hall->id }}" @selected((string) $selectedHallId === (string) $hall->id)>{{ $hall->name }}</option>@endforeach
                        </select>
                    </div>
                    <div><label class="mb-1 block text-xs text-slate-400">Kapasite</label><input name="capacity" type="number" min="1" max="100" value="{{ $editingThisTable ? old('capacity') : $table->capacity }}" required class="w-full rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm"></div>
                </div>
                <div><label class="mb-1 block text-xs text-slate-400">Not</label><textarea name="notes" maxlength="500" rows="3" placeholder="Konum veya kullanım notu" class="w-full resize-y rounded-lg border border-slate-700 bg-slate-950 p-3 text-sm">{{ $editingThisTable ? old('notes') : $table->notes }}</textarea></div>
                <div class="flex flex-col-reverse gap-3 border-t border-slate-800 pt-4 sm:flex-row sm:justify-end">
                    <button type="button" onclick="closeTableEditor({{ $branch->id }}, {{ $table->id }})" class="rounded-lg border border-slate-700 px-5 py-3 text-sm font-bold text-slate-300 hover:bg-slate-800">Vazgeç</button>
                    <button class="inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950 hover:bg-cyan-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="m5 12 4 4L19 6"/></svg>Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>
    @endforeach
@endif
@endforeach
<script>
function openTableManager(id){const modal=document.getElementById(`tableManager${id}`);modal?.classList.remove('hidden');modal?.classList.add('flex');document.body.style.overflow='hidden'}
function closeTableManager(id){const modal=document.getElementById(`tableManager${id}`);modal?.classList.add('hidden');modal?.classList.remove('flex');document.body.style.overflow=''}
function openTableEditor(branchId,tableId){const modal=document.getElementById(`tableEditor${branchId}_${tableId}`);modal?.classList.remove('hidden');modal?.classList.add('flex');modal?.querySelector('input[name="name"]')?.focus();document.body.style.overflow='hidden'}
function closeTableEditor(branchId,tableId){const modal=document.getElementById(`tableEditor${branchId}_${tableId}`);modal?.classList.add('hidden');modal?.classList.remove('flex')}
document.addEventListener('keydown',event=>{if(event.key!=='Escape')return;const editor=document.querySelector('[id^="tableEditor"].flex');if(editor){const ids=editor.id.replace('tableEditor','').split('_');closeTableEditor(ids[0],ids[1]);return}document.querySelectorAll('[id^="tableManager"].flex').forEach(modal=>closeTableManager(modal.id.replace('tableManager','')))})
@if(preg_match('/^table_(\d+)_edit_(\d+)$/',(string)old('form_context'),$editContext)) document.addEventListener('DOMContentLoaded',()=>{openTableManager({{ (int)$editContext[1] }});openTableEditor({{ (int)$editContext[1] }},{{ (int)$editContext[2] }})});
@elseif(str_starts_with((string)old('form_context'),'table_')) document.addEventListener('DOMContentLoaded',()=>openTableManager({{ (int)str_replace('table_','',(string)old('form_context')) }})); @endif
</script>
@endsection
