@extends('admin.layout')

@section('title', 'Personel & Alt Hesap Yönetimi - AltF4 Admin')

@section('content')
<div class="space-y-6">

    <!-- ÜST BAŞLIK VE AKSİYON -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-wide">👥 Personel & Alt Hesap Yönetimi</h2>
            <p class="text-xs text-gray-400 mt-1">Restoranlar için Garson, Mutfak, Kasa, Kaptan alt hesap ve profillerini yönetin.</p>
        </div>

        <button onclick="document.getElementById('addStaffModal').classList.remove('hidden')" class="inline-flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/30 transition">
            <i class="fa-solid fa-plus"></i>
            <span>Yeni Alt Hesap / Profil Ekle</span>
        </button>
    </div>

    <!-- FİLTRELEME & ARAMA BAR -->
    <div class="bg-[#141620] border border-gray-800 p-4 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4 shadow-xl">
        <form action="{{ route('admin.staff.index') }}" method="GET" class="w-full flex flex-col sm:flex-row items-center gap-3">
            
            <!-- Şube Filtresi -->
            <div class="w-full sm:w-64">
                <select name="branch_id" onchange="this.form.submit()" class="w-full bg-[#0b0c10] border border-gray-700 text-gray-200 text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                    <option value="">Tüm Şubeler / Restoranlar</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                            🏬 {{ $branch->name }} ({{ $branch->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Arama Kutusu -->
            <div class="w-full sm:w-72 relative">
                <input type="text" name="search" value="{{ $search }}" placeholder="Personel adı, rol veya PIN ara..." class="w-full bg-[#0b0c10] border border-gray-700 text-gray-200 text-xs rounded-xl pl-9 pr-4 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                <i class="fa-solid fa-search absolute left-3 top-3 text-gray-500 text-xs"></i>
            </div>

            <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs font-semibold px-4 py-2.5 rounded-xl border border-gray-700 transition">
                Filtrele
            </button>

            @if($selectedBranchId || !empty($search))
                <a href="{{ route('admin.staff.index') }}" class="text-xs text-indigo-400 hover:underline">
                    Filtreleri Temizle
                </a>
            @endif
        </form>
    </div>

    <!-- PERSONEL PROFİLLERİ TABLOSU -->
    <div class="bg-[#141620] border border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#181a24] text-gray-400 text-xs uppercase tracking-wider border-b border-gray-800">
                        <th class="py-3.5 px-4 font-semibold">Profil & Personel</th>
                        <th class="py-3.5 px-4 font-semibold">Bağlı Şube</th>
                        <th class="py-3.5 px-4 font-semibold">Rol / Pozisyon</th>
                        <th class="py-3.5 px-4 font-semibold">4-Haneli PIN Kodu</th>
                        <th class="py-3.5 px-4 font-semibold">Durum</th>
                        <th class="py-3.5 px-4 font-semibold text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60 text-xs">
                    @forelse($profiles as $profile)
                        @php
                            $roleColors = [
                                'Garson' => 'bg-indigo-950/80 border-indigo-500/40 text-indigo-300',
                                'Mutfak' => 'bg-emerald-950/80 border-emerald-500/40 text-emerald-300',
                                'Kasa' => 'bg-amber-950/80 border-amber-500/40 text-amber-300',
                                'Kaptan' => 'bg-rose-950/80 border-rose-500/40 text-rose-300',
                                'Yönetici' => 'bg-purple-950/80 border-purple-500/40 text-purple-300',
                                'Müdür' => 'bg-cyan-950/80 border-cyan-500/40 text-cyan-300',
                            ];
                            $badgeClass = $roleColors[$profile->role] ?? 'bg-gray-900 border-gray-700 text-gray-300';
                            
                            $colorBorders = [
                                'indigo' => 'border-indigo-500 text-indigo-400 bg-indigo-950/50',
                                'emerald' => 'border-emerald-500 text-emerald-400 bg-emerald-950/50',
                                'amber' => 'border-amber-500 text-amber-400 bg-amber-950/50',
                                'rose' => 'border-rose-500 text-rose-400 bg-rose-950/50',
                                'purple' => 'border-purple-500 text-purple-400 bg-purple-950/50',
                                'cyan' => 'border-cyan-500 text-cyan-400 bg-cyan-950/50',
                            ];
                            $avatarTheme = $colorBorders[$profile->avatar_color] ?? 'border-indigo-500 text-indigo-400 bg-indigo-950/50';
                        @endphp
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="py-3.5 px-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-9 h-9 rounded-xl border {{ $avatarTheme }} flex items-center justify-center font-bold text-sm shadow-md shrink-0">
                                        @if($profile->role === 'Garson') 🍷
                                        @elseif($profile->role === 'Mutfak') 👨‍🍳
                                        @elseif($profile->role === 'Kasa') 💳
                                        @elseif($profile->role === 'Kaptan') 👔
                                        @else 👤
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm">{{ $profile->name }}</div>
                                        <div class="text-[10px] text-gray-400">ID: #{{ $profile->id }} &bull; Tema: {{ ucfirst($profile->avatar_color) }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3.5 px-4 font-medium text-gray-300">
                                <div class="flex items-center space-x-1.5">
                                    <span class="text-indigo-400">🏬</span>
                                    <span>{{ $profile->branch->name ?? 'Tüm Şubeler' }}</span>
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-lg border text-[11px] font-bold tracking-wide {{ $badgeClass }}">
                                    {{ $profile->role }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4 font-mono text-gray-200">
                                <div class="inline-flex items-center space-x-2 bg-gray-900 border border-gray-700/80 px-2.5 py-1 rounded-lg">
                                    <i class="fa-solid fa-lock text-xs text-amber-400"></i>
                                    <span class="font-bold text-indigo-300 tracking-widest">{{ $profile->pin_code }}</span>
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                @if($profile->is_active)
                                    <span class="inline-flex items-center space-x-1.5 text-emerald-400 text-xs font-semibold">
                                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                        <span>Aktif</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center space-x-1.5 text-gray-500 text-xs font-semibold">
                                        <span class="w-2 h-2 rounded-full bg-gray-600"></span>
                                        <span>Pasif</span>
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-right space-x-2">
                                <!-- Durum Değiştir -->
                                <form action="{{ route('admin.staff.toggle', $profile) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" title="Durumu Değiştir" class="p-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg border border-gray-700 transition">
                                        <i class="fa-solid {{ $profile->is_active ? 'fa-toggle-on text-emerald-400' : 'fa-toggle-off text-gray-500' }}"></i>
                                    </button>
                                </form>

                                <!-- Düzenle -->
                                <button onclick="editStaff({{ json_encode($profile) }})" title="Düzenle" class="p-1.5 bg-gray-800 hover:bg-indigo-900 text-indigo-300 rounded-lg border border-gray-700 transition">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <!-- Sil -->
                                <form action="{{ route('admin.staff.destroy', $profile) }}" method="POST" class="inline" onsubmit="return confirm('Bu alt hesap profilini silmek istediğinize emin misiniz?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Sil" class="p-1.5 bg-gray-800 hover:bg-rose-950 text-rose-400 rounded-lg border border-gray-700 transition">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-500">
                                Kayıtlı personel alt hesabı bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($profiles->hasPages())
            <div class="p-4 border-t border-gray-800">
                {{ $profiles->links() }}
            </div>
        @endif
    </div>

</div>

<!-- YENİ PERSONEL EKLEME MODALI -->
<div id="addStaffModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#141620] border border-gray-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white">✨ Yeni Personel / Alt Hesap Oluştur</h3>
            <button onclick="document.getElementById('addStaffModal').classList.add('hidden')" class="text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="{{ route('admin.staff.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Restoran / Şube</label>
                <select name="branch_id" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                    <option value="">-- Şube Seçiniz --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                            🏬 {{ $branch->name }} ({{ $branch->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Personel Adı Soyadı</label>
                <input type="text" name="name" required placeholder="Örn: Ahmet Yılmaz" class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Rol / Görev</label>
                    <select name="role" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                        <option value="Garson">🍷 Garson</option>
                        <option value="Mutfak">👨‍🍳 Mutfak</option>
                        <option value="Kasa">💳 Kasa</option>
                        <option value="Kaptan">👔 Kaptan</option>
                        <option value="Yönetici">👑 Yönetici</option>
                        <option value="Müdür">💼 Müdür</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">4-6 Haneli PIN Kodu</label>
                    <input type="text" name="pin_code" required maxlength="6" minlength="4" placeholder="Örn: 1234" class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 font-mono focus:border-indigo-500 focus:outline-none transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Avatar / Renk Teması</label>
                <select name="avatar_color" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                    <option value="indigo">💜 İndigo / Mor</option>
                    <option value="emerald">🟢 Zümrüt / Yeşil</option>
                    <option value="amber">🟠 Kehribar / Turuncu</option>
                    <option value="rose">🔴 Gül / Kırmızı</option>
                    <option value="cyan">🔵 Camgöbeği / Mavi</option>
                    <option value="purple">🔮 Asil Mor</option>
                </select>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <input type="checkbox" name="is_active" id="is_active_add" value="1" checked class="rounded bg-[#0b0c10] border-gray-700 text-indigo-600 focus:ring-0">
                <label for="is_active_add" class="text-xs text-gray-300 cursor-pointer">Personel profili hemen aktif olsun</label>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('addStaffModal').classList.add('hidden')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition">
                    Kayıt Et
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PERSONEL DÜZENLEME MODALI -->
<div id="editStaffModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#141620] border border-gray-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white">✏️ Personel Profilini Düzenle</h3>
            <button onclick="document.getElementById('editStaffModal').classList.add('hidden')" class="text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form id="editStaffForm" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Restoran / Şube</label>
                <select name="branch_id" id="edit_branch_id" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">🏬 {{ $branch->name }} ({{ $branch->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Personel Adı Soyadı</label>
                <input type="text" name="name" id="edit_name" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Rol / Görev</label>
                    <select name="role" id="edit_role" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                        <option value="Garson">🍷 Garson</option>
                        <option value="Mutfak">👨‍🍳 Mutfak</option>
                        <option value="Kasa">💳 Kasa</option>
                        <option value="Kaptan">👔 Kaptan</option>
                        <option value="Yönetici">👑 Yönetici</option>
                        <option value="Müdür">💼 Müdür</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">4-6 Haneli PIN Kodu</label>
                    <input type="text" name="pin_code" id="edit_pin_code" required maxlength="6" minlength="4" class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 font-mono focus:border-indigo-500 focus:outline-none transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Avatar / Renk Teması</label>
                <select name="avatar_color" id="edit_avatar_color" required class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
                    <option value="indigo">💜 İndigo / Mor</option>
                    <option value="emerald">🟢 Zümrüt / Yeşil</option>
                    <option value="amber">🟠 Kehribar / Turuncu</option>
                    <option value="rose">🔴 Gül / Kırmızı</option>
                    <option value="cyan">🔵 Camgöbeği / Mavi</option>
                    <option value="purple">🔮 Asil Mor</option>
                </select>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded bg-[#0b0c10] border-gray-700 text-indigo-600 focus:ring-0">
                <label for="edit_is_active" class="text-xs text-gray-300 cursor-pointer">Personel profili aktif</label>
            </div>

            <div class="pt-3 flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('editStaffModal').classList.add('hidden')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition">
                    Güncelle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function editStaff(profile) {
        document.getElementById('editStaffForm').action = "/admin/staff/" + profile.id;
        document.getElementById('edit_branch_id').value = profile.branch_id;
        document.getElementById('edit_name').value = profile.name;
        document.getElementById('edit_role').value = profile.role;
        document.getElementById('edit_pin_code').value = profile.pin_code;
        document.getElementById('edit_avatar_color').value = profile.avatar_color;
        document.getElementById('edit_is_active').checked = profile.is_active;

        document.getElementById('editStaffModal').classList.remove('hidden');
    }
</script>
@endsection
