@extends('admin.layout')

@section('title', 'Rol & Modül Yetki Matrisi - AltF4 Admin')

@section('content')
<div class="space-y-6">

    <!-- ÜST BAŞLIK VE AKSİYON -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-wide">🔐 Rol & Modül Yetki Matrisi</h2>
            <p class="text-xs text-gray-400 mt-1">Personel rollerinin (Garson, Mutfak, Kasa, Şef vb.) restoran kontrol panelindeki erişim yetkilerini dinamik olarak işaretleyin.</p>
        </div>

        <button onclick="document.getElementById('addRoleModal').classList.remove('hidden')" class="inline-flex items-center space-x-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/30 transition">
            <i class="fa-solid fa-plus"></i>
            <span>Yeni Özel Rol Tanımla</span>
        </button>
    </div>

    <!-- YETKİ MATRİSİ FORMU -->
    <form action="{{ route('admin.roles.update') }}" method="POST">
        @csrf

        <div class="bg-[#141620] border border-gray-800 rounded-2xl overflow-hidden shadow-2xl space-y-4">
            
            <div class="p-4 bg-[#181a24] border-b border-gray-800 flex items-center justify-between">
                <div class="flex items-center space-x-2 text-xs font-bold text-indigo-300 uppercase tracking-wider">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Rol Bazlı Modül İzin Seçimi</span>
                </div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-5 py-2 rounded-xl shadow-lg shadow-emerald-600/30 transition flex items-center space-x-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Tüm Değişiklikleri Kaydet</span>
                </button>
            </div>

            <div class="overflow-x-auto p-4">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[#0b0c10] text-gray-400 text-[11px] uppercase tracking-wider border-b border-gray-800">
                            <th class="py-3 px-4 font-bold sticky left-0 bg-[#0b0c10] min-w-[160px] z-10">Rol Adı</th>
                            @foreach($allModules as $modKey => $modData)
                                <th class="py-3 px-3 font-semibold text-center min-w-[110px]">
                                    <div class="text-white text-xs font-bold">{{ $modData['name'] }}</div>
                                    <div class="text-[9px] text-gray-500 font-mono lower">{{ $modKey }}</div>
                                </th>
                            @endforeach
                            <th class="py-3 px-3 font-semibold text-center min-w-[120px]">Toplu Seçim</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60 text-xs">
                        @foreach($roleList as $roleName)
                            @php
                                $allowed = $matrix[$roleName] ?? [];
                            @endphp
                            <tr class="hover:bg-gray-800/40 transition">
                                <!-- Gizli input rol ismini taşır -->
                                <input type="hidden" name="roles[]" value="{{ $roleName }}">

                                <!-- Rol Adı (Sol Sabit Kolon) -->
                                <td class="py-4 px-4 font-bold text-white sticky left-0 bg-[#141620] z-10 border-r border-gray-800">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                                        <span class="text-sm">{{ $roleName }}</span>
                                    </div>
                                </td>

                                <!-- 12 Modül Checkbox'ları -->
                                @foreach($allModules as $modKey => $modData)
                                    @php
                                        $isChecked = in_array($modKey, $allowed);
                                    @endphp
                                    <td class="py-4 px-3 text-center">
                                        <label class="inline-flex items-center justify-center p-2 rounded-xl bg-[#0b0c10] border {{ $isChecked ? 'border-indigo-500/50 bg-indigo-950/30' : 'border-gray-800' }} cursor-pointer hover:border-indigo-500/80 transition">
                                            <input type="checkbox" 
                                                name="permissions[{{ $roleName }}][]" 
                                                value="{{ $modKey }}" 
                                                class="role-cb-{{ Str::slug($roleName) }} w-4 h-4 rounded bg-[#0b0c10] border-gray-700 text-indigo-600 focus:ring-0 cursor-pointer"
                                                {{ $isChecked ? 'checked' : '' }}>
                                        </label>
                                    </td>
                                @endforeach

                                <!-- Toplu Seç/Temizle Butonları -->
                                <td class="py-4 px-3 text-center space-x-1">
                                    <button type="button" onclick="selectAll('role-cb-{{ Str::slug($roleName) }}', true)" class="px-2 py-1 bg-gray-800 hover:bg-gray-700 text-[10px] text-gray-300 rounded border border-gray-700 transition" title="Tümünü Seç">
                                        ✓ Hepsi
                                    </button>
                                    <button type="button" onclick="selectAll('role-cb-{{ Str::slug($roleName) }}', false)" class="px-2 py-1 bg-gray-800 hover:bg-gray-700 text-[10px] text-rose-300 rounded border border-gray-700 transition" title="Temizle">
                                        ✕ Hiçbiri
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-[#181a24] border-t border-gray-800 flex items-center justify-between">
                <div class="text-xs text-gray-400">
                    💡 İpucu: İşaretlenen modüller, o role sahip personel sisteme PIN girdikten sonra ekranında aktifleşir.
                </div>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs px-6 py-2.5 rounded-xl shadow-lg shadow-emerald-600/30 transition flex items-center space-x-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Tüm Değişiklikleri Kaydet</span>
                </button>
            </div>

        </div>
    </form>

</div>

<!-- YENİ ROL EKLEME MODALI -->
<div id="addRoleModal" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#141620] border border-gray-800 rounded-2xl w-full max-w-md p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white">✨ Yeni Özel Rol Tanımla</h3>
            <button onclick="document.getElementById('addRoleModal').classList.add('hidden')" class="text-gray-400 hover:text-white">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="{{ route('admin.roles.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Rol / Pozisyon Adı</label>
                <input type="text" name="role_name" required placeholder="Örn: Şef, Barista, Kurye" class="w-full bg-[#0b0c10] border border-gray-700 text-white text-xs rounded-xl px-3.5 py-2.5 focus:border-indigo-500 focus:outline-none transition">
            </div>

            <div class="pt-3 flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('addRoleModal').classList.add('hidden')" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition">
                    Rolü Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function selectAll(className, status) {
        document.querySelectorAll('.' + className).forEach(cb => {
            cb.checked = status;
        });
    }
</script>
@endsection
