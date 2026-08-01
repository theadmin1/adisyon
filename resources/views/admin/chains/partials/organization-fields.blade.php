<div class="grid gap-4 sm:grid-cols-2">
    <div><label class="mb-1 block text-xs font-bold text-gray-400">Zincir Adı</label><input name="name" required value="{{ old('name', $editingOrganization?->name) }}" class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm"></div>
    <div><label class="mb-1 block text-xs font-bold text-gray-400">Kod</label><input name="code" required value="{{ old('code', $editingOrganization?->code) }}" class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm uppercase"></div>
</div>
<div>
    <p class="mb-2 text-xs font-bold text-gray-400">Zincire Bağlı Şubeler</p>
    <div class="grid max-h-52 gap-2 overflow-y-auto rounded-xl border border-gray-800 bg-[#0b0c10] p-3 sm:grid-cols-2">
        @foreach($branches as $branch)
            @php($belongsElsewhere = $branch->organizations->isNotEmpty() && ! $branch->organizations->contains('id', $editingOrganization?->id))
            <label class="flex items-start gap-2 rounded-lg p-2 {{ $belongsElsewhere ? 'cursor-not-allowed opacity-40' : 'hover:bg-gray-800' }}">
                <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked($editingOrganization?->branches->contains('id', $branch->id)) @disabled($belongsElsewhere)>
                <span class="text-xs"><strong class="block text-gray-200">{{ $branch->name }}</strong><span class="text-gray-500">{{ $belongsElsewhere ? 'Başka zincire bağlı' : $branch->code }}</span></span>
            </label>
        @endforeach
    </div>
</div>
@if($editingOrganization)
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($editingOrganization->is_active)> Zincir aktif</label>
@endif
