<form method="POST" action="{{ $action }}" class="space-y-4">@csrf @if($method !== 'POST') @method($method) @endif
    <div><label class="mb-1 block text-xs font-bold text-gray-400">Zincir</label><select name="organization_id" required class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm">
        @foreach($organizations as $organizationOption)<option value="{{ $organizationOption->id }}" @selected(($selectedOrganization?->id ?? null) === $organizationOption->id)>{{ $organizationOption->name }}</option>@endforeach
    </select><p class="mt-1 text-[11px] text-amber-400">Şube seçenekleri aşağıda mevcut zincire göre gösterilir. Zincir değiştirmek için kaydedip kullanıcıyı tekrar açın.</p></div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="mb-1 block text-xs font-bold text-gray-400">Ad Soyad</label><input name="name" required value="{{ $editingUser?->name }}" class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm"></div>
        <div><label class="mb-1 block text-xs font-bold text-gray-400">E-posta</label><input type="email" name="email" required value="{{ $editingUser?->email }}" class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm"></div>
        <div><label class="mb-1 block text-xs font-bold text-gray-400">Rol</label><select name="chain_role" class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm">
            @foreach(['owner'=>'Zincir Sahibi','general_manager'=>'Genel Müdür','regional_manager'=>'Bölge Müdürü','analyst'=>'Rapor Kullanıcısı'] as $value=>$label)<option value="{{ $value }}" @selected(($editingUser?->chain_role ?? '') === $value)>{{ $label }}</option>@endforeach
        </select></div>
        <div><label class="mb-1 block text-xs font-bold text-gray-400">{{ $editingUser ? 'Yeni Şifre (opsiyonel)' : 'Şifre' }}</label><input type="password" name="password" {{ $editingUser ? '' : 'required' }} minlength="10" autocomplete="new-password" class="w-full rounded-lg border border-gray-700 bg-[#0b0c10] p-2.5 text-sm"></div>
    </div>
    <div>
        <p class="mb-1 text-xs font-bold text-gray-400">Erişebileceği Şubeler</p>
        <p class="mb-2 text-[11px] text-cyan-400">Hiç seçim yapılmazsa zincirin tüm şubelerine erişebilir.</p>
        @php($availableBranches = $selectedOrganization?->branches ?? $organizations->first()?->branches ?? collect())
        <div class="grid max-h-52 gap-2 overflow-y-auto rounded-xl border border-gray-800 bg-[#0b0c10] p-3 sm:grid-cols-2">
            @foreach($availableBranches as $branch)
                <label class="flex gap-2 rounded-lg p-2 hover:bg-gray-800"><input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked($editingUser?->chainBranches->contains('id', $branch->id))><span class="text-xs text-gray-300">{{ $branch->name }} <small class="text-gray-600">{{ $branch->code }}</small></span></label>
            @endforeach
        </div>
    </div>
    <button class="w-full rounded-xl bg-indigo-600 py-3 text-sm font-bold">Kullanıcıyı Kaydet</button>
</form>
