@extends('admin.layout')

@section('title', 'Kayıtlı Cihazlar - Central Admin Panel')

@section('content')
<div class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-white">💻 Kayıtlı Cihazlar & Cihaz API Key Monitörü</h2>
        <p class="text-sm text-gray-400">Merkezi API'ye bağlanan Windows C# POS istemcilerinin Güvenlik API Key'lerini ve canlılık durumunu izleyin.</p>
    </div>

    <!-- CİHAZLAR TABLOSU -->
    <div class="bg-[#181a24] border border-gray-800 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-300">
                <thead class="bg-[#141620] text-xs text-gray-400 uppercase border-b border-gray-800">
                    <tr>
                        <th class="p-4">Cihaz Kodu</th>
                        <th class="p-4">Şube</th>
                        <th class="p-4">Cihaz API Key (Authorization Token)</th>
                        <th class="p-4">IP Adresi</th>
                        <th class="p-4">Versiyon</th>
                        <th class="p-4">Son Sinyal (Ping)</th>
                        <th class="p-4">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($devices as $dev)
                        <tr class="hover:bg-gray-800/40 transition">
                            <td class="p-4 font-bold text-white flex items-center space-x-2">
                                <span class="text-indigo-400">🖥️</span>
                                <span>{{ $dev->device_code }}</span>
                            </td>
                            <td class="p-4 text-gray-200">{{ $dev->branch->name ?? 'Belirtilmedi' }}</td>
                            <td class="p-4">
                                <div class="font-mono text-xs text-amber-400 bg-amber-950/40 px-2.5 py-1 rounded border border-amber-500/30 select-all inline-block">
                                    🔑 {{ $dev->api_key ?? 'Doğrulama Bekleniyor...' }}
                                </div>
                            </td>
                            <td class="p-4 font-mono text-gray-400">{{ $dev->ip_address ?? '127.0.0.1' }}</td>
                            <td class="p-4 font-mono text-xs text-indigo-300">v{{ $dev->app_version ?? '1.0.0' }}</td>
                            <td class="p-4 text-xs text-gray-400">
                                {{ $dev->last_ping_at ? $dev->last_ping_at->diffForHumans() : 'Hiç yok' }}
                            </td>
                            <td class="p-4">
                                @if($dev->license && !$dev->license->isValid())
                                    <span class="px-2.5 py-1 text-xs rounded-full font-bold bg-rose-950 text-rose-400 border border-rose-500/30">🔴 LİSANS ENGELİ (PASİF)</span>
                                @elseif($dev->isOnline())
                                    <span class="px-2.5 py-1 text-xs rounded-full font-bold bg-emerald-950 text-emerald-400 border border-emerald-500/30">🟢 ONLINE</span>
                                @else
                                    <span class="px-2.5 py-1 text-xs rounded-full font-bold bg-gray-800 text-gray-400">⚪ OFFLINE</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6 text-center text-gray-500">Henüz bağlı C# cihaz yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-800">
            {{ $devices->links() }}
        </div>
    </div>

</div>
@endsection
