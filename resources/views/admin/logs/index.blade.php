@extends('admin.layout')

@section('title', 'Güvenlik & Sistem Logları - Central Admin Panel')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">🛡️ Güvenlik & Sistem Logları</h2>
            <p class="mt-1 text-sm text-gray-400">Girişleri, cihaz servis olaylarını ve kritik kullanıcı işlemlerini tek merkezden inceleyin.</p>
        </div>
        <a href="{{ route('admin.logs.export', array_merge($filters, ['tab' => $activeTab])) }}"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-950/70 px-4 py-2.5 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-900/70">
            <i class="fa-solid fa-file-csv"></i>
            Filtrelenenleri CSV İndir
        </a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-800 bg-[#181a24] p-5">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Son 24 Saat Giriş</div>
            <div class="mt-2 text-3xl font-black text-white">{{ number_format($stats['logins_24h']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-800 bg-[#181a24] p-5">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Benzersiz IP</div>
            <div class="mt-2 text-3xl font-black text-sky-400">{{ number_format($stats['unique_ips_24h']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-800 bg-[#181a24] p-5">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Admin Girişi</div>
            <div class="mt-2 text-3xl font-black text-amber-400">{{ number_format($stats['admin_logins_24h']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-800 bg-[#181a24] p-5">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Cihaz Olayı</div>
            <div class="mt-2 text-3xl font-black text-emerald-400">{{ number_format($stats['device_events_24h']) }}</div>
        </div>
        <div class="rounded-xl border border-gray-800 bg-[#181a24] p-5">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Kritik İşlem</div>
            <div class="mt-2 text-3xl font-black text-fuchsia-400">{{ number_format($stats['audit_events_24h']) }}</div>
        </div>
    </div>

    <div class="flex flex-wrap w-fit rounded-xl border border-gray-800 bg-[#141620] p-1 gap-1">
        <a href="{{ route('admin.logs.index', ['tab' => 'logins']) }}"
            class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $activeTab === 'logins' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:text-white' }}">
            <i class="fa-solid fa-right-to-bracket mr-1.5"></i>Kullanıcı Girişleri
        </a>
        <a href="{{ route('admin.logs.index', ['tab' => 'devices']) }}"
            class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $activeTab === 'devices' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:text-white' }}">
            <i class="fa-solid fa-satellite-dish mr-1.5"></i>Cihaz Sinyalleri
        </a>
        <a href="{{ route('admin.logs.index', ['tab' => 'audits']) }}"
            class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $activeTab === 'audits' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-gray-400 hover:text-white' }}">
            <i class="fa-solid fa-list-check mr-1.5"></i>İşlem Geçmişi
        </a>
        <a href="{{ route('admin.logs.index', ['tab' => 'terminal']) }}"
            class="rounded-lg px-4 py-2 text-sm font-semibold transition {{ $activeTab === 'terminal' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/20' : 'text-gray-400 hover:text-white' }}">
            <i class="fa-solid fa-terminal mr-1.5 text-emerald-400"></i>Canlı Servis Terminali
        </a>
    </div>

    <form method="GET" action="{{ route('admin.logs.index') }}"
        class="rounded-xl border border-gray-800 bg-[#181a24] p-5">
        <input type="hidden" name="tab" value="{{ $activeTab }}">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="search" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Arama</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                    placeholder="{{ $activeTab === 'logins' ? 'Kullanıcı, e-posta veya restoran kodu' : ($activeTab === 'audits' ? 'İşlem, kullanıcı, personel veya hedef' : 'Cihaz kodu veya olay tipi') }}"
                    class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-gray-600 focus:border-indigo-500">
            </div>

            <div>
                <label for="branch_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Şube</label>
                <select id="branch_id" name="branch_id"
                    class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-500">
                    <option value="">Tüm şubeler</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>
                            {{ $branch->name }} ({{ $branch->code }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if($activeTab === 'logins')
                <div>
                    <label for="portal" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Portal</label>
                    <select id="portal" name="portal"
                        class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-500">
                        <option value="">Tüm portallar</option>
                        <option value="restaurant" @selected(($filters['portal'] ?? '') === 'restaurant')>Restoran</option>
                        <option value="admin" @selected(($filters['portal'] ?? '') === 'admin')>Central Admin</option>
                    </select>
                </div>
            @elseif($activeTab === 'audits')
                <div>
                    <label for="category" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Kategori</label>
                    <select id="category" name="category"
                        class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-500">
                        <option value="">Tüm kategoriler</option>
                        @foreach([
                            'sales' => 'Satış & Adisyon',
                            'cash' => 'Kasa Vardiyası',
                            'purchasing' => 'Tedarikçi & Satın Alma',
                            'inventory' => 'Stok',
                            'catalog' => 'Ürün & Kategori',
                            'staff' => 'Personel & Yetki',
                            'settings' => 'Ayarlar',
                            'integration' => 'Entegrasyon',
                            'administration' => 'Yönetim',
                            'tables' => 'Salon & Masa',
                            'system' => 'Sistem',
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['category'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label for="ip" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">IP Adresi</label>
                    <input id="ip" name="ip" type="text" value="{{ $filters['ip'] ?? '' }}" placeholder="203.0.113.10"
                        class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 font-mono text-sm text-white outline-none transition placeholder:text-gray-600 focus:border-indigo-500">
                </div>
            @endif

            <div>
                <label for="date_from" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Başlangıç</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"
                    class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-500">
            </div>

            <div>
                <label for="date_to" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Bitiş</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"
                    class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 text-sm text-white outline-none transition focus:border-indigo-500">
            </div>
        </div>

        @if($activeTab === 'logins' || $activeTab === 'audits')
            <div class="mt-4 max-w-sm">
                <label for="ip" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">IP Adresi</label>
                <input id="ip" name="ip" type="text" value="{{ $filters['ip'] ?? '' }}" placeholder="IPv4 veya IPv6"
                    class="w-full rounded-lg border border-gray-700 bg-[#10121a] px-3 py-2.5 font-mono text-sm text-white outline-none transition placeholder:text-gray-600 focus:border-indigo-500">
            </div>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-500">
                <i class="fa-solid fa-filter"></i>Filtrele
            </button>
            <a href="{{ route('admin.logs.index', ['tab' => $activeTab]) }}"
                class="rounded-lg border border-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-400 transition hover:bg-gray-800 hover:text-white">
                Filtreleri Temizle
            </a>
            <span class="ml-auto text-xs text-gray-500">{{ number_format($logs->total()) }} kayıt bulundu</span>
        </div>

        @if($errors->any())
            <div class="mt-4 rounded-lg border border-rose-500/30 bg-rose-950/50 p-3 text-sm text-rose-300">
                {{ $errors->first() }}
            </div>
        @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-800 bg-[#181a24] shadow-sm">
        <div class="overflow-x-auto">
            @if($activeTab === 'logins')
                <table class="w-full min-w-[1050px] text-left text-sm text-gray-300">
                    <thead class="border-b border-gray-800 bg-[#141620] text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-4">Giriş Tarihi</th>
                            <th class="p-4">Kullanıcı</th>
                            <th class="p-4">Şube / Restoran</th>
                            <th class="p-4">Portal</th>
                            <th class="p-4">IP Adresi</th>
                            <th class="p-4">Tarayıcı / Cihaz</th>
                            <th class="p-4">Oturum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($logs as $log)
                            <tr class="transition hover:bg-gray-800/40">
                                <td class="whitespace-nowrap p-4 font-mono text-xs text-gray-400">
                                    {{ $log->logged_in_at?->format('d.m.Y H:i:s') ?? '-' }}
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-white">{{ $log->user_name }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500">{{ $log->user_email }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-gray-200">{{ $log->branch?->name ?? 'Central Admin' }}</div>
                                    <div class="mt-0.5 font-mono text-xs text-indigo-400">{{ $log->restaurant_id ?? '-' }}</div>
                                </td>
                                <td class="p-4">
                                    <span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $log->portal === 'admin' ? 'border-amber-500/30 bg-amber-950/70 text-amber-300' : 'border-indigo-500/30 bg-indigo-950/70 text-indigo-300' }}">
                                        {{ $log->portal === 'admin' ? 'Central Admin' : 'Restoran' }}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-xs text-sky-300">{{ $log->ip_address ?? '-' }}</td>
                                <td class="max-w-xs p-4 text-xs text-gray-400" title="{{ $log->user_agent }}">
                                    {{ \Illuminate\Support\Str::limit($log->user_agent ?: 'Bilinmiyor', 80) }}
                                </td>
                                <td class="p-4">
                                    <span class="text-xs {{ $log->remember_me ? 'text-emerald-400' : 'text-gray-500' }}">
                                        {{ $log->remember_me ? 'Hatırlandı' : 'Standart' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-10 text-center text-gray-500">Filtrelere uygun giriş kaydı bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif($activeTab === 'audits')
                @php
                    $categoryLabels = [
                        'sales' => 'Satış & Adisyon',
                        'cash' => 'Kasa Vardiyası',
                        'purchasing' => 'Tedarikçi & Satın Alma',
                        'inventory' => 'Stok',
                        'catalog' => 'Ürün & Kategori',
                        'staff' => 'Personel & Yetki',
                        'settings' => 'Ayarlar',
                        'integration' => 'Entegrasyon',
                        'administration' => 'Yönetim',
                        'tables' => 'Salon & Masa',
                        'system' => 'Sistem',
                    ];
                @endphp
                <table class="w-full min-w-[1350px] text-left text-sm text-gray-300">
                    <thead class="border-b border-gray-800 bg-[#141620] text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-4">Tarih / Saat</th>
                            <th class="p-4">Kullanıcı / Personel</th>
                            <th class="p-4">Şube</th>
                            <th class="p-4">Kategori / İşlem</th>
                            <th class="p-4">Hedef</th>
                            <th class="p-4">Açıklama</th>
                            <th class="p-4">Değişiklik</th>
                            <th class="p-4">IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($logs as $log)
                            <tr class="align-top transition hover:bg-gray-800/40">
                                <td class="whitespace-nowrap p-4 font-mono text-xs text-gray-400">
                                    {{ $log->occurred_at?->format('d.m.Y H:i:s') ?? '-' }}
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-white">{{ $log->actor_user_name }}</div>
                                    <div class="mt-0.5 text-xs text-indigo-300">{{ $log->actor_staff_name ?: 'Personel seçilmedi' }}</div>
                                </td>
                                <td class="p-4 font-semibold text-gray-200">{{ $log->branch?->name ?? 'Central Admin' }}</td>
                                <td class="p-4">
                                    <span class="rounded-full border border-fuchsia-500/30 bg-fuchsia-950/60 px-2.5 py-1 text-xs font-bold text-fuchsia-300">
                                        {{ $categoryLabels[$log->category] ?? $log->category }}
                                    </span>
                                    <div class="mt-2 font-mono text-xs text-gray-400">{{ $log->action }}</div>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-white">{{ $log->subject_label ?? '-' }}</div>
                                    <div class="mt-0.5 font-mono text-[11px] text-gray-600">
                                        {{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '-' }}
                                    </div>
                                </td>
                                <td class="max-w-xs p-4 text-xs leading-relaxed text-gray-300">{{ $log->description ?? '-' }}</td>
                                <td class="max-w-md p-4">
                                    @if($log->old_values || $log->new_values)
                                        <details class="group">
                                            <summary class="cursor-pointer text-xs font-semibold text-sky-300 hover:text-sky-200">Eski / yeni değerleri göster</summary>
                                            <div class="mt-2 grid min-w-[340px] grid-cols-2 gap-2">
                                                <div>
                                                    <div class="mb-1 text-[10px] font-bold uppercase text-rose-400">Eski</div>
                                                    <pre class="max-h-52 overflow-auto whitespace-pre-wrap rounded bg-black/30 p-2 text-[10px] text-gray-400">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-' }}</pre>
                                                </div>
                                                <div>
                                                    <div class="mb-1 text-[10px] font-bold uppercase text-emerald-400">Yeni</div>
                                                    <pre class="max-h-52 overflow-auto whitespace-pre-wrap rounded bg-black/30 p-2 text-[10px] text-gray-300">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '-' }}</pre>
                                                </div>
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-xs text-gray-600">Detay yok</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <div class="font-mono text-xs text-sky-300">{{ $log->ip_address ?? '-' }}</div>
                                    <div class="mt-1 font-mono text-[10px] text-gray-600" title="{{ $log->request_id }}">{{ \Illuminate\Support\Str::limit($log->request_id, 13) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-10 text-center text-gray-500">Filtrelere uygun işlem kaydı bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="w-full min-w-[850px] text-left text-sm text-gray-300">
                    <thead class="border-b border-gray-800 bg-[#141620] text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-4">Tarih / Saat</th>
                            <th class="p-4">Şube</th>
                            <th class="p-4">Cihaz</th>
                            <th class="p-4">Olay Tipi</th>
                            <th class="p-4">IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($logs as $log)
                            <tr class="transition hover:bg-gray-800/40">
                                <td class="whitespace-nowrap p-4 font-mono text-xs text-gray-400">{{ $log->created_at?->format('d.m.Y H:i:s') ?? '-' }}</td>
                                <td class="p-4 font-semibold text-gray-200">{{ $log->device?->branch?->name ?? 'Bilinmeyen Şube' }}</td>
                                <td class="p-4 font-bold text-white">{{ $log->device?->device_code ?? 'Silinmiş Cihaz' }}</td>
                                <td class="p-4">
                                    <span class="rounded-full border border-emerald-500/30 bg-emerald-950/70 px-2.5 py-1 font-mono text-xs font-bold text-emerald-300">
                                        {{ $log->event_type }}
                                    </span>
                                </td>
                                <td class="p-4 font-mono text-xs text-sky-300">{{ $log->ip_address ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-10 text-center text-gray-500">Filtrelere uygun cihaz logu bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        @if($logs->hasPages())
            <div class="border-t border-gray-800 p-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
    @endif

    @if($activeTab === 'terminal')
        <div class="rounded-2xl border border-gray-800 bg-[#0c0e17] overflow-hidden shadow-2xl space-y-0">
            <!-- TERMINAL TOP HEADER -->
            <div class="bg-[#141622] px-5 py-3 border-b border-gray-800 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-full bg-rose-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-amber-500/80 inline-block"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-500/80 inline-block"></span>
                    </div>
                    <span class="font-mono text-xs font-bold text-gray-300 ml-2">AltF4 Adisyon Live Service Terminal — 127.0.0.1:18500 / 127.0.0.1:8000</span>
                </div>

                <div class="flex items-center gap-2">
                    <span id="terminalStatusBadge" class="px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-bold font-mono">
                        ● CANLI AKIŞ AKTİF
                    </span>
                    <button type="button" id="toggleStreamBtn" onclick="toggleTerminalStream()" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-300 transition cursor-pointer">
                        Duraklat
                    </button>
                    <button type="button" onclick="clearTerminalDisplay()" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-300 transition cursor-pointer">
                        Temizle
                    </button>
                </div>
            </div>

            <!-- TERMINAL SCREEN -->
            <div id="terminalScreen" class="p-5 font-mono text-xs text-emerald-400 bg-[#06080f] h-[500px] overflow-y-auto space-y-1.5 selection:bg-emerald-500 selection:text-black">
                <div class="text-gray-500">[SYSTEM] Live terminal streaming initialized...</div>
            </div>
        </div>

        <script>
            let isStreamingPaused = false;

            async function fetchTerminalLogs() {
                if (isStreamingPaused) return;

                try {
                    const response = await fetch("{{ route('admin.logs.terminal-stream') }}");
                    if (response.ok) {
                        const data = await response.json();
                        const screen = document.getElementById('terminalScreen');
                        if (!screen) return;

                        screen.innerHTML = '';
                        screen.innerHTML += `<div class="text-gray-500 mb-2">[SYSTEM ${data.timestamp}] Live terminal stream active (Background Mode). Ports: 18500 & 8000</div>`;

                        if (data.file_logs && data.file_logs.length > 0) {
                            data.file_logs.forEach(logLine => {
                                const line = document.createElement('div');
                                if (logLine.includes('ERROR') || logLine.includes('CRITICAL') || logLine.includes('Exception')) {
                                    line.className = 'text-rose-400 font-bold';
                                } else if (logLine.includes('WARNING') || logLine.includes('WARN')) {
                                    line.className = 'text-amber-300';
                                } else if (logLine.includes('INFO')) {
                                    line.className = 'text-slate-300';
                                } else {
                                    line.className = 'text-emerald-400/90';
                                }
                                line.innerText = logLine;
                                screen.appendChild(line);
                            });
                        }

                        if (data.device_logs && data.device_logs.length > 0) {
                            data.device_logs.forEach(dl => {
                                const line = document.createElement('div');
                                line.className = 'text-sky-300';
                                line.innerText = `[DEVICE LOG ${dl.created_at || ''}] IP: ${dl.ip_address || '-'} -> ${dl.event}`;
                                screen.appendChild(line);
                            });
                        }

                        screen.scrollTop = screen.scrollHeight;
                    }
                } catch (err) {
                    console.error('Terminal stream error:', err);
                }
            }

            function toggleTerminalStream() {
                isStreamingPaused = !isStreamingPaused;
                const btn = document.getElementById('toggleStreamBtn');
                const badge = document.getElementById('terminalStatusBadge');

                if (isStreamingPaused) {
                    if (btn) btn.innerText = 'Devam Et';
                    if (badge) {
                        badge.className = 'px-2.5 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[10px] font-bold font-mono';
                        badge.innerText = '⏸ AKIŞ DURAKLATILDI';
                    }
                } else {
                    if (btn) btn.innerText = 'Duraklat';
                    if (badge) {
                        badge.className = 'px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-bold font-mono';
                        badge.innerText = '● CANLI AKIŞ AKTİF';
                    }
                    fetchTerminalLogs();
                }
            }

            function clearTerminalDisplay() {
                const screen = document.getElementById('terminalScreen');
                if (screen) {
                    screen.innerHTML = '<div class="text-gray-500">[SYSTEM] Terminal ekranı temizlendi.</div>';
                }
            }

            document.addEventListener('DOMContentLoaded', () => {
                fetchTerminalLogs();
                setInterval(fetchTerminalLogs, 3000);
            });
        </script>
    @endif
</div>
@endsection
