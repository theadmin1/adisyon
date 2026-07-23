@extends('admin.layout')

@section('title', 'Şube Yönetimi - Central Admin Panel')

@section('content')
<div class="space-y-6">

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
                                <span class="px-2.5 py-1 text-xs rounded-full font-bold {{ $b->is_active ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/30' : 'bg-rose-950 text-rose-400 border border-rose-500/30' }}">
                                    {{ $b->is_active ? 'AKTİF' : 'PASİF' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-6 text-center text-gray-500">Henüz hiç şube eklenmemiş.</td>
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
                <input type="text" name="code" placeholder="Örn. KADIKOY-01" required class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Yetkili E-Posta</label>
                <input type="email" name="contact_email" placeholder="admin@kadikoy.com" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Telefon</label>
                <input type="text" name="phone" placeholder="0555 111 22 33" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div class="flex items-center justify-end space-x-3 border-t border-gray-800 pt-4">
                <button type="button" onclick="document.getElementById('createBranchModal').classList.add('hidden')" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm font-semibold hover:bg-gray-700">İptal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30">💾 Kaydet</button>
            </div>
        </form>
    </div>
</div>
@endsection
