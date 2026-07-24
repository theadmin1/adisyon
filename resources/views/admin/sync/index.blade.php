@extends('admin.layout')

@section('title', 'Çevrimdışı Veri & Senkronizasyon Monitörü')

@section('content')
<div class="space-y-6">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-[#141620] p-6 rounded-xl border border-gray-800">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-3">
                <span>📡 Çevrimdışı Veri & Senkronizasyon Monitörü</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">İnternet kesintilerinde cihazlarda oluşan çevrimdışı adisyon, ödeme ve logların canlı aktarım takibi.</p>
        </div>
        <div class="flex items-center space-x-3">
            <form action="{{ route('admin.sync.clear-logs') }}" method="POST" onsubmit="return confirm('Hatalı senkronizasyon loglarını temizlemek istediğinize emin misiniz?');">
                @csrf
                <button type="submit" class="bg-rose-900/60 hover:bg-rose-800 text-rose-200 border border-rose-700/50 text-xs px-4 py-2 rounded-lg font-medium transition flex items-center space-x-2">
                    <i class="fa-solid fa-trash"></i>
                    <span>Hatalı Logları Temizle</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ÖZET İSTATİSTİK KARTLARI -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-[#141620] p-5 rounded-xl border border-gray-800 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">Senkronize Edilen Adisyonlar</p>
                <h3 class="text-2xl font-extrabold text-indigo-400 mt-1">{{ number_format($totalSyncedChecks) }}</h3>
            </div>
            <div class="w-12 h-12 bg-indigo-950/60 border border-indigo-800/50 rounded-lg flex items-center justify-center text-indigo-400 text-xl">
                🧾
            </div>
        </div>

        <div class="bg-[#141620] p-5 rounded-xl border border-gray-800 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">Senkronize Edilen Ödemeler</p>
                <h3 class="text-2xl font-extrabold text-emerald-400 mt-1">{{ number_format($totalSyncedPayments) }}</h3>
            </div>
            <div class="w-12 h-12 bg-emerald-950/60 border border-emerald-800/50 rounded-lg flex items-center justify-center text-emerald-400 text-xl">
                💳
            </div>
        </div>

        <div class="bg-[#141620] p-5 rounded-xl border border-gray-800 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">Son 7 Gün Başarılı Sync</p>
                <h3 class="text-2xl font-extrabold text-teal-400 mt-1">{{ number_format($recentSuccessLogs) }}</h3>
            </div>
            <div class="w-12 h-12 bg-teal-950/60 border border-teal-800/50 rounded-lg flex items-center justify-center text-teal-400 text-xl">
                ✅
            </div>
        </div>

        <div class="bg-[#141620] p-5 rounded-xl border border-gray-800 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium">Son 7 Gün Hatalı İşlem</p>
                <h3 class="text-2xl font-extrabold text-rose-400 mt-1">{{ number_format($recentErrorLogs) }}</h3>
            </div>
            <div class="w-12 h-12 bg-rose-950/60 border border-rose-800/50 rounded-lg flex items-center justify-center text-rose-400 text-xl">
                ⚠️
            </div>
        </div>
    </div>

    <!-- FİLTRELEME FORMU -->
    <div class="bg-[#141620] p-4 rounded-xl border border-gray-800">
        <form method="GET" action="{{ route('admin.sync.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1">Şube Filtresi</label>
                <select name="branch_id" class="w-full bg-[#181a24] border border-gray-700 text-gray-200 text-xs rounded-lg p-2.5 focus:border-indigo-500 focus:ring-0">
                    <option value="">Tüm Şubeler</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1">Cihaz Filtresi</label>
                <select name="device_id" class="w-full bg-[#181a24] border border-gray-700 text-gray-200 text-xs rounded-lg p-2.5 focus:border-indigo-500 focus:ring-0">
                    <option value="">Tüm Cihazlar</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ request('device_id') == $device->id ? 'selected' : '' }}>{{ $device->device_code }} ({{ $device->device_name }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1">Durum Filtresi</label>
                <select name="status" class="w-full bg-[#181a24] border border-gray-700 text-gray-200 text-xs rounded-lg p-2.5 focus:border-indigo-500 focus:ring-0">
                    <option value="">Tüm Durumlar</option>
                    <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Success (Başarılı)</option>
                    <option value="error" {{ request('status') == 'error' ? 'selected' : '' }}>Error (Hatalı)</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending (Bekliyor)</option>
                </select>
            </div>

            <div class="flex items-end space-x-2">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-xs py-2.5 px-4 rounded-lg font-semibold transition">
                    🔍 Filtrele
                </button>
                <a href="{{ route('admin.sync.index') }}" class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs py-2.5 px-4 rounded-lg font-semibold transition">
                    Sıfırla
                </a>
            </div>
        </form>
    </div>

    <!-- LOG & SENKRONİZASYON TABLOSU -->
    <div class="bg-[#141620] rounded-xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="p-4 border-b border-gray-800 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-200">Çevrimdışı Senkronizasyon Kayıtları</h3>
            <span class="text-xs text-gray-500">Toplam {{ $syncLogs->total() }} kayıt</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-300">
                <thead class="bg-[#181a24] text-gray-400 uppercase text-[10px] tracking-wider border-b border-gray-800">
                    <tr>
                        <th class="p-3">Tarih / Zaman</th>
                        <th class="p-3">Şube</th>
                        <th class="p-3">Cihaz</th>
                        <th class="p-3">Veri Tipi</th>
                        <th class="p-3">Sync UUID</th>
                        <th class="p-3">Durum</th>
                        <th class="p-3">Detay / Hata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                    @forelse($syncLogs as $log)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="p-3 font-mono text-gray-400 whitespace-nowrap">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="p-3 font-medium text-gray-200">
                                {{ $log->branch->name ?? 'Merkez' }}
                            </td>
                            <td class="p-3">
                                <span class="bg-gray-800 text-indigo-300 px-2 py-0.5 rounded font-mono text-[11px]">
                                    {{ $log->device->device_code ?? 'Cihaz #' . $log->device_id }}
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="capitalize font-semibold text-gray-300">
                                    {{ $log->payload_type }}
                                </span>
                            </td>
                            <td class="p-3 font-mono text-gray-400 text-[11px]">
                                {{ Str::limit($log->sync_uuid, 20) }}
                            </td>
                            <td class="p-3 whitespace-nowrap">
                                @if($log->status === 'success')
                                    <span class="bg-emerald-950 text-emerald-400 border border-emerald-800/60 px-2.5 py-1 rounded-full text-[10px] font-bold">
                                        ✅ BAŞARILI
                                    </span>
                                @elseif($log->status === 'error')
                                    <span class="bg-rose-950 text-rose-400 border border-rose-800/60 px-2.5 py-1 rounded-full text-[10px] font-bold">
                                        ❌ HATA
                                    </span>
                                @else
                                    <span class="bg-amber-950 text-amber-400 border border-amber-800/60 px-2.5 py-1 rounded-full text-[10px] font-bold">
                                        ⏳ BEKLİYOR
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-400 max-w-xs truncate">
                                @if($log->error_message)
                                    <span class="text-rose-400 font-mono">{{ $log->error_message }}</span>
                                @elseif($log->details)
                                    <span class="text-gray-400 font-mono">{{ json_encode($log->details) }}</span>
                                @else
                                    <span class="text-gray-600">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-500">
                                Henüz çevrimdışı senkronizasyon kaydı bulunmamaktadır.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($syncLogs->hasPages())
            <div class="p-4 border-t border-gray-800 bg-[#181a24]">
                {{ $syncLogs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
