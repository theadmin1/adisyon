@extends('admin.layout')

@section('title', 'Lisans Yönetimi - Central Admin Panel')

@section('content')
<div class="space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">🔑 Lisans Yönetimi</h2>
            <p class="text-sm text-gray-400">Windows C# POS ve Kiosk uygulamaları için lisans anahtarlarını yönetin ve doğrulayın.</p>
        </div>
        <button onclick="document.getElementById('createLicenseModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-lg shadow-indigo-600/30 transition flex items-center space-x-2">
            <span>➕ Yeni Lisans Key Üret</span>
        </button>
    </div>

    <!-- LİSANS TABLOSU -->
    <div class="bg-[#181a24] border border-gray-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-[#141620] text-xs text-gray-400 uppercase border-b border-gray-800">
                    <tr>
                        <th class="p-4">ID</th>
                        <th class="p-4">Lisans Anahtarı</th>
                        <th class="p-4">Şube</th>
                        <th class="p-4">Cihaz Limit</th>
                        <th class="p-4">Bitiş Tarihi</th>
                        <th class="p-4">Durum</th>
                        <th class="p-4 text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($licenses as $lic)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="p-4 font-mono text-gray-500">#{{ $lic->id }}</td>
                            <td class="p-4">
                                <div class="font-mono font-bold text-indigo-400 select-all">{{ $lic->license_key }}</div>
                                <div class="text-xs text-gray-500 font-mono">Token: {{ Str::limit($lic->device_token ?? 'Yok', 20) }}</div>
                            </td>
                            <td class="p-4">
                                <div class="font-semibold text-white">{{ $lic->branch->name ?? 'Belirtilmedi' }}</div>
                                <div class="text-xs text-gray-500">{{ $lic->branch->code ?? '' }}</div>
                            </td>
                            <td class="p-4 font-semibold text-gray-300">{{ $lic->devices->count() }} / {{ $lic->max_devices }} Cihaz</td>
                            <td class="p-4 text-xs text-gray-400">
                                {{ $lic->expires_at ? $lic->expires_at->format('d.m.Y H:i') : 'Süresiz' }}
                            </td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 text-xs rounded-full font-bold {{ $lic->status === 'Active' ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/30' : 'bg-rose-950 text-rose-400 border border-rose-500/30' }}">
                                    {{ $lic->status }}
                                </span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <form action="{{ route('admin.licenses.toggle', $lic->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs px-3 py-1.5 rounded font-semibold transition {{ $lic->status === 'Active' ? 'bg-amber-950/80 text-amber-400 border border-amber-500/30 hover:bg-amber-900' : 'bg-emerald-950/80 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-900' }}">
                                        {{ $lic->status === 'Active' ? '🔴 Askıya Al' : '🟢 Aktif Et' }}
                                    </button>
                                </form>
                                <form action="{{ route('admin.licenses.destroy', $lic->id) }}" method="POST" class="inline" onsubmit="return confirm('Bu lisansı silmek istediğinize emin misiniz?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs px-2.5 py-1.5 rounded bg-rose-950/60 text-rose-400 border border-rose-500/30 hover:bg-rose-900 transition">
                                        🗑️ Sil
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-gray-500">Henüz hiç lisans oluşturulmamış.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-800">
            {{ $licenses->links() }}
        </div>
    </div>

</div>

<!-- YENİ LİSANS ÜRET MODAL -->
<div id="createLicenseModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-[#181a24] border border-gray-800 rounded-xl max-w-md w-full p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3">
            <h3 class="text-lg font-bold text-white">🔑 Yeni Otomatik Lisans Key Üret</h3>
            <button onclick="document.getElementById('createLicenseModal').classList.add('hidden')" class="text-gray-400 hover:text-white">&times;</button>
        </div>

        <form action="{{ route('admin.licenses.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Bağlı Şube / Restoran</label>
                <select name="branch_id" required class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Maksimum Kasa / Cihaz Limiti</label>
                <input type="number" name="max_devices" value="5" min="1" required class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Lisans Bitiş Tarihi</label>
                <input type="date" name="expires_at" value="{{ now()->addYear()->format('Y-m-d') }}" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Not / Açıklama</label>
                <textarea name="notes" placeholder="Örn: 1 yıllık yıllık abonelik paketi" class="w-full bg-[#141620] border border-gray-700 text-white rounded-lg p-2.5 text-sm focus:border-indigo-500 focus:outline-none"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3 border-t border-gray-800 pt-4">
                <button type="button" onclick="document.getElementById('createLicenseModal').classList.add('hidden')" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm font-semibold hover:bg-gray-700">İptal</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-500 shadow-lg shadow-indigo-600/30">⚡ Oluştur & Kaydet</button>
            </div>
        </form>
    </div>
</div>
@endsection
