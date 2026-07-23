@extends('admin.layout')

@section('title', 'Genel Bakış - Central Admin Panel')

@section('content')
<div class="space-y-6">

    <!-- BAŞLIK & ÖZET -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">📊 Sistem Genel Bakış</h2>
            <p class="text-sm text-gray-400">Restoran şubeleriniz, aktif lisanslar ve C# Kiosk cihazlarının canlılık durumu.</p>
        </div>
        <a href="{{ route('admin.licenses.index') }}" class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-lg shadow-indigo-600/30 transition flex items-center space-x-2">
            <span>➕ Yeni Lisans Oluştur</span>
        </a>
    </div>

    <!-- STATS CARDS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
        <div class="bg-[#181a24] border border-gray-800 p-5 rounded-xl flex items-center space-x-4 shadow-sm">
            <div class="p-3 bg-indigo-950 text-indigo-400 rounded-lg text-2xl">🏬</div>
            <div>
                <div class="text-2xl font-bold text-white">{{ $totalBranches }}</div>
                <div class="text-xs text-gray-400">Kayıtlı Şube / Restoran</div>
            </div>
        </div>

        <div class="bg-[#181a24] border border-gray-800 p-5 rounded-xl flex items-center space-x-4 shadow-sm">
            <div class="p-3 bg-emerald-950 text-emerald-400 rounded-lg text-2xl">🔑</div>
            <div>
                <div class="text-2xl font-bold text-emerald-400">{{ $activeLicenses }}</div>
                <div class="text-xs text-gray-400">Aktif Lisans Anahtarı</div>
            </div>
        </div>

        <div class="bg-[#181a24] border border-gray-800 p-5 rounded-xl flex items-center space-x-4 shadow-sm">
            <div class="p-3 bg-emerald-950 text-emerald-400 rounded-lg text-2xl">💻</div>
            <div>
                <div class="text-2xl font-bold text-white"><span class="text-emerald-400">{{ $onlineDevices }}</span> / {{ $totalDevices }}</div>
                <div class="text-xs text-gray-400">Online Kasa Cihazı</div>
            </div>
        </div>

        <div class="bg-[#181a24] border border-gray-800 p-5 rounded-xl flex items-center space-x-4 shadow-sm">
            <div class="p-3 bg-rose-950 text-rose-400 rounded-lg text-2xl">⚠️</div>
            <div>
                <div class="text-2xl font-bold text-rose-400">{{ $expiredLicenses }}</div>
                <div class="text-xs text-gray-400">Süresi Dolan Lisans</div>
            </div>
        </div>
    </div>

    <!-- İKİLİ TABLO DÜZENİ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- LİSANS ÖZET TABLOSU -->
        <div class="bg-[#181a24] border border-gray-800 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-white flex items-center space-x-2">
                    <span>🔑 Son Oluşturulan Lisanslar</span>
                </h3>
                <a href="{{ route('admin.licenses.index') }}" class="text-xs text-indigo-400 hover:underline">Tümünü Gör &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-[#141620] text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Lisans Key</th>
                            <th class="p-3">Şube</th>
                            <th class="p-3">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($recentLicenses as $lic)
                            <tr class="hover:bg-gray-800/40">
                                <td class="p-3 font-mono font-semibold text-indigo-300">{{ $lic->license_key }}</td>
                                <td class="p-3 text-gray-200">{{ $lic->branch->name ?? 'Genel' }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-1 text-xs rounded-full font-semibold {{ $lic->status === 'Active' ? 'bg-emerald-950 text-emerald-400 border border-emerald-500/30' : 'bg-rose-950 text-rose-400 border border-rose-500/30' }}">
                                        {{ $lic->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-3 text-center text-gray-500">Henüz lisans kaydı yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CİHAZ CANLILIK TABLOSU -->
        <div class="bg-[#181a24] border border-gray-800 rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-white flex items-center space-x-2">
                    <span>💻 Canlı Cihazlar (Heartbeat)</span>
                </h3>
                <a href="{{ route('admin.devices.index') }}" class="text-xs text-indigo-400 hover:underline">Tümünü Gör &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-300">
                    <thead class="bg-[#141620] text-xs text-gray-400 uppercase">
                        <tr>
                            <th class="p-3">Cihaz Kodu</th>
                            <th class="p-3">IP Adresi</th>
                            <th class="p-3">Son Sinyal</th>
                            <th class="p-3">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($recentDevices as $dev)
                            <tr class="hover:bg-gray-800/40">
                                <td class="p-3 font-bold text-white">{{ $dev->device_code }}</td>
                                <td class="p-3 font-mono text-gray-400">{{ $dev->ip_address ?? '127.0.0.1' }}</td>
                                <td class="p-3 text-xs text-gray-400">{{ $dev->last_ping_at ? $dev->last_ping_at->diffForHumans() : 'Hiç sinyal yok' }}</td>
                                <td class="p-3">
                                    @if($dev->isOnline())
                                        <span class="px-2 py-1 text-xs rounded-full font-semibold bg-emerald-950 text-emerald-400 border border-emerald-500/30">🟢 ONLINE</span>
                                    @else
                                        <span class="px-2 py-1 text-xs rounded-full font-semibold bg-gray-800 text-gray-400">🔴 OFFLINE</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-3 text-center text-gray-500">Henüz bağlı C# cihaz yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
@endsection
