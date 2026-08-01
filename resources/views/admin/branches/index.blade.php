@extends('admin.layout')

@section('title', 'Şube Yönetimi - Central Admin Panel')

@section('content')
<div class="space-y-6">

    @if (session('restaurant_credentials'))
        @php($credentials = session('restaurant_credentials'))
        <div class="rounded-xl border border-emerald-500/40 bg-emerald-950/40 p-5 text-emerald-100 shadow-lg">
            <h3 class="font-bold text-emerald-300">Restoran giriş bilgileri oluşturuldu</h3>
            <p class="mt-1 text-xs text-emerald-200/70">Şifre güvenlik nedeniyle yalnızca bu ekranda bir kez gösterilir.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-emerald-500/20 bg-black/20 p-3">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">Restoran Kodu / ID</div>
                    <div class="mt-1 select-all font-mono text-lg font-bold text-white">{{ $credentials['restaurant_id'] }}</div>
                </div>
                <div class="rounded-lg border border-emerald-500/20 bg-black/20 p-3">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">Şifre</div>
                    <div class="mt-1 select-all font-mono text-lg font-bold text-white">{{ $credentials['password'] }}</div>
                </div>
            </div>
            @if ($credentials['license_key'] ?? null)
                <div class="mt-3 rounded-lg border border-indigo-500/30 bg-indigo-950/40 p-3">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-300">Lisans Anahtarı</div>
                    <div class="mt-1 select-all break-all font-mono text-lg font-bold text-white">{{ $credentials['license_key'] }}</div>
                </div>
            @endif
        </div>
    @endif

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">🏬 Şubeler ve Restoranlar</h2>
            <p class="text-sm text-gray-400">Lisanslı restoran şubelerinizi ve işletme hesaplarını yönetin.</p>
        </div>
        <button onclick="document.getElementById('createBranchModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-lg shadow-indigo-600/30 transition flex items-center space-x-2">
            <span>➕ Yeni Şube Ekle</span>
        </button>
    </div>

    <!-- ŞUBE TABLOSU -->
    <div class="bg-[#181a24] border border-gray-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-[#141620] text-xs text-gray-400 uppercase border-b border-gray-800">
                    <tr>
                        <th class="p-4">Şube Adı</th>
                        <th class="p-4">Şube Kodu</th>
                        <th class="p-4">E-Posta / Telefon</th>
                        <th class="p-4">Lisans Sayısı</th>
                        <th class="p-4">Cihaz Sayısı</th>
                        <th class="p-4">Personel Profilleri</th>
                        <th class="p-4">Durum</th>
                        <th class="p-4 text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($branches as $b)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="p-4 font-bold text-white">{{ $b->name }}</td>
                            <td class="p-4 font-mono text-indigo-400">{{ $b->code }}</td>
                            <td class="p-4 text-xs text-gray-400">
                                <div>{{ $b->contact_email ?? 'Belirtilmedi' }}</div>
                                <div>{{ $b->phone ?? '' }}</div>
                            </td>
                            <td class="p-4 font-semibold text-emerald-400">{{ $b->licenses_count }} Lisans</td>
                            <td class="p-4 font-semibold text-indigo-300">{{ $b->devices_count }} Cihaz</td>
                            <td class="p-4">
                                <a href="{{ route('admin.staff.index', ['branch_id' => $b->id]) }}" class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg bg-indigo-950/80 border border-indigo-500/40 text-indigo-300 font-semibold hover:bg-indigo-900 transition">
                                    <span>👥 {{ $b->staff_profiles_count ?? 0 }} Personel</span>
                                </a>
                            </td>
                            <td class="p-4">
                                <form action="{{ route('admin.branches.toggle', $b) }}" method="POST">
                                    @csrf
                                    <button type="submit" role="switch" aria-checked="{{ $b->is_active ? 'true' : 'false' }}" title="{{ $b->is_active ? 'Pasif yap' : 'Aktif yap' }}" class="group inline-flex items-center gap-2">
                                        <span class="relative inline-flex h-6 w-11 rounded-full transition {{ $b->is_active ? 'bg-emerald-500' : 'bg-gray-700' }}">
                                            <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all {{ $b->is_active ? 'left-[22px]' : 'left-0.5' }}"></span>
                                        </span>
                                        <span class="text-xs font-bold {{ $b->is_active ? 'text-emerald-400' : 'text-gray-500' }}">{{ $b->is_active ? 'AKTİF' : 'PASİF' }}</span>
                                    </button>
                                </form>
                            </td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button"
                                        class="edit-branch-button inline-flex h-9 w-9 items-center justify-center rounded-lg border border-indigo-500/30 bg-indigo-950/60 text-indigo-300 transition hover:bg-indigo-900 hover:text-white"
                                        title="Düzenle" aria-label="{{ $b->name }} şubesini düzenle"
                                        data-update-url="{{ route('admin.branches.update', $b) }}"
                                        data-name="{{ $b->name }}"
                                        data-code="{{ $b->code }}"
                                        data-contact-email="{{ $b->contact_email }}"
                                        data-phone="{{ $b->phone }}"
                                        data-address="{{ $b->address }}">
                                        <i class="fi fi-rr-edit" aria-hidden="true"></i>
                                    </button>
                                    <form action="{{ route('admin.branches.reset-password', $b) }}" method="POST" onsubmit="return confirm('Bu restoran için yeni bir giriş şifresi üretilecek. Devam edilsin mi?')">
                                        @csrf
                                        <button type="submit" title="Yeni şifre oluştur" aria-label="{{ $b->name }} için yeni şifre oluştur" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-amber-500/30 bg-amber-950/60 text-amber-300 transition hover:bg-amber-900 hover:text-white">
                                            <i class="fi fi-rr-key" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.branches.destroy', $b) }}" method="POST" onsubmit="return confirm('Bu şube, restoran giriş hesabı ve bağlı veriler kalıcı olarak silinecek. Emin misiniz?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Sil" aria-label="{{ $b->name }} şubesini sil" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-500/30 bg-rose-950/60 text-rose-300 transition hover:bg-rose-900 hover:text-white">
                                            <i class="fi fi-rr-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-gray-500">Henüz hiç şube eklenmemiş.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-800">
            {{ $branches->links() }}
        </div>
    </div>

</div>

<div id="editBranchModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="editBranchModalTitle">
    <div class="w-full max-w-md space-y-5 rounded-xl border border-gray-800 bg-[#181a24] p-6 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 id="editBranchModalTitle" class="text-lg font-bold text-white">Şube / Restoran Düzenle</h3>
            <button type="button" onclick="closeEditBranch()" class="text-gray-400 hover:text-white">&times;</button>
        </div>
        <form id="editBranchForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div><label class="mb-1 block text-xs font-bold uppercase text-gray-400">Şube Adı</label><input id="edit_name" name="name" required class="w-full rounded-lg border border-gray-700 bg-[#141620] p-2.5 text-sm text-white"></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-gray-400">Şube Kodu / Giriş ID</label><input id="edit_code" name="code" required pattern="[A-Za-z0-9_-]+" class="w-full rounded-lg border border-gray-700 bg-[#141620] p-2.5 text-sm uppercase text-white"><p class="mt-1 text-[11px] text-amber-400">Kod değişirse restoran giriş ID'si de değişir.</p></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-gray-400">Yetkili E-Posta</label><input id="edit_contact_email" type="email" name="contact_email" class="w-full rounded-lg border border-gray-700 bg-[#141620] p-2.5 text-sm text-white"></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-gray-400">Telefon</label><input id="edit_phone" name="phone" class="w-full rounded-lg border border-gray-700 bg-[#141620] p-2.5 text-sm text-white"></div>
            <div><label class="mb-1 block text-xs font-bold uppercase text-gray-400">Adres</label><textarea id="edit_address" name="address" rows="3" class="w-full rounded-lg border border-gray-700 bg-[#141620] p-2.5 text-sm text-white"></textarea></div>
            <div class="flex justify-end gap-3 border-t border-gray-800 pt-4"><button type="button" onclick="closeEditBranch()" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-gray-300">İptal</button><button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Güncelle</button></div>
        </form>
    </div>
</div>

<script>
function openEditBranch(button) {
    const modal = document.getElementById('editBranchModal');
    document.getElementById('editBranchForm').action = button.dataset.updateUrl;
    document.getElementById('edit_name').value = button.dataset.name || '';
    document.getElementById('edit_code').value = button.dataset.code || '';
    document.getElementById('edit_contact_email').value = button.dataset.contactEmail || '';
    document.getElementById('edit_phone').value = button.dataset.phone || '';
    document.getElementById('edit_address').value = button.dataset.address || '';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    document.getElementById('edit_name').focus();
}

function closeEditBranch() {
    const modal = document.getElementById('editBranchModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

document.querySelectorAll('.edit-branch-button').forEach(function (button) {
    button.addEventListener('click', function () {
        openEditBranch(button);
    });
});

document.getElementById('editBranchModal').addEventListener('click', function (event) {
    if (event.target === this) closeEditBranch();
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !document.getElementById('editBranchModal').classList.contains('hidden')) {
        closeEditBranch();
    }
});
</script>

<!-- YENİ ŞUBE EKLENME MODAL -->
<div id="createBranchModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-[#181a24] border border-gray-800 rounded-xl max-w-md w-full p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white">🏬 Yeni Şube / Restoran Ekle</h3>
            <button onclick="document.getElementById('createBranchModal').classList.add('hidden')" class="text-gray-400 hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.branches.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Şube Adı</label>
                <input type="text" name="name" placeholder="Örn. Antigravity Kadıköy Şubesi" required class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Şube Kodu</label>
                <input type="text" name="code" placeholder="Örn. KADIKOY-01" required pattern="[A-Za-z0-9_-]+" autocomplete="off" class="w-full bg-[#141620] border border-gray-700 text-white uppercase rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                <p class="mt-1 text-[11px] text-gray-500">Bu kod restoran giriş ekranında kullanıcı adı olarak kullanılacaktır.</p>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Yetkili E-Posta</label>
                <input type="email" name="contact_email" placeholder="admin@kadikoy.com" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Telefon</label>
                <input type="text" name="phone" placeholder="0555 111 22 33" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="rounded-xl border border-indigo-500/30 bg-indigo-950/20 p-4">
                <label class="flex cursor-pointer items-center gap-3">
                    <input type="hidden" name="create_license" value="0">
                    <input type="checkbox" name="create_license" value="1" checked onchange="document.getElementById('branchLicenseFields').classList.toggle('hidden', !this.checked)" class="h-4 w-4 rounded border-gray-600 bg-[#141620] text-indigo-600 focus:ring-indigo-500">
                    <span>
                        <strong class="block text-sm text-white">Lisansı şimdi oluştur</strong>
                        <small class="text-xs text-gray-400">Şubeyle birlikte aktif lisans anahtarı üretir.</small>
                    </span>
                </label>

                <div id="branchLicenseFields" class="mt-4 grid gap-4 border-t border-indigo-500/20 pt-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Bitiş Tarihi</label>
                        <input type="date" name="license_expires_at" value="{{ old('license_expires_at', now()->addYear()->format('Y-m-d')) }}" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Cihaz Limiti</label>
                        <input type="number" name="license_max_devices" value="{{ old('license_max_devices', 5) }}" min="1" max="1000" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Lisans Notu</label>
                        <textarea name="license_notes" rows="2" placeholder="Örn. Yıllık abonelik" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">{{ old('license_notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 border-t border-gray-800 pt-4">
                <button type="button" onclick="document.getElementById('createBranchModal').classList.add('hidden')" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm font-semibold hover:bg-gray-700">İptal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30">💾 Kaydet</button>
            </div>
        </form>
    </div>
</div>
@endsection
