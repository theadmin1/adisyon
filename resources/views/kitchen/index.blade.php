@extends('layouts.app')

@section('title', '👨‍🍳 Mutfak Sipariş Yönetimi (KDS)')

@section('styles')
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, 0.6);
        border-radius: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(16, 185, 129, 0.3);
        border-radius: 8px;
    }
    .kitchen-card {
        transition: all 0.25s ease-in-out;
    }
    .kitchen-card:hover {
        transform: translateY(-2px);
    }
</style>
@endsection

@section('content')
<div class="flex flex-col h-screen bg-[#07090e] text-slate-100 font-sans overflow-hidden">
    
    <!-- Top Navigation & Header Bar -->
    <header class="h-16 bg-[#0f131f]/95 border-b border-slate-800/80 px-4 sm:px-6 flex items-center justify-between z-30 shrink-0 backdrop-blur-md">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-all border border-slate-700/50">
                <i class="fi fi-rr-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold tracking-tight text-white flex items-center gap-2">
                    <span class="p-1 rounded-lg bg-orange-500/10 text-orange-400 border border-orange-500/20">
                        <i class="fi fi-rr-restaurant"></i>
                    </span>
                    Mutfak Sipariş Yönetimi (KDS)
                </h1>
                <p class="text-[11px] text-slate-400 hidden sm:block">Anlık Sipariş Takibi & Sesli Bildirim Paneli</p>
            </div>
        </div>

        <!-- Right Utilities & Audio Toggle -->
        <div class="flex items-center gap-3">

            <!-- Audio Sound Toggle Button -->
            <button id="btnSoundToggle" onclick="toggleAudioSound()" class="px-3.5 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500 hover:text-white transition-all flex items-center gap-2 text-xs font-bold shadow-sm">
                <span id="soundIcon">🔊</span>
                <span id="soundText">Sesli Uyarı Açık</span>
            </button>

            <!-- Manual Refresh -->
            <button onclick="window.location.reload()" class="p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/50 transition-all flex items-center gap-1.5 text-xs font-bold" title="Yenile">
                <i class="fi fi-rr-refresh text-xs"></i>
                <span class="hidden sm:inline">Yenile</span>
            </button>
        </div>
    </header>

    <!-- 4 MAIN CATEGORY TABS -->
    <div class="bg-[#0b0e18] border-b border-slate-800/80 px-4 sm:px-6 py-3 shrink-0 z-20 overflow-x-auto">
        <div class="flex items-center gap-2 sm:gap-3 min-w-max">
            <!-- TÜMÜ -->
            <a href="{{ route('kitchen.index', ['status' => 'all']) }}" 
               class="px-4 py-2 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'all' ? 'bg-indigo-600 border-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'bg-slate-900/80 border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800' }}">
                <i class="fi fi-rr-apps text-sm"></i>
                <span>TÜM SİPARİŞLER (KOLONLU)</span>
                <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-mono">{{ $stats['total'] }}</span>
            </a>

            <div class="h-6 w-[1px] bg-slate-800 mx-1"></div>

            <!-- 1. ALINDI -->
            <a href="{{ route('kitchen.index', ['status' => 'received']) }}" 
               class="px-4 py-2 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'received' ? 'bg-amber-600 border-amber-500 text-white shadow-lg shadow-amber-600/30' : 'bg-amber-500/10 border-amber-500/20 text-amber-300 hover:bg-amber-500/20' }}">
                <i class="fi fi-rr-inbox-in text-xs"></i>
                <span>ALINDI</span>
                <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-200 text-[10px] font-mono border border-amber-500/30">{{ $stats['received'] }}</span>
            </a>

            <!-- 2. HAZIRLANIYOR -->
            <a href="{{ route('kitchen.index', ['status' => 'preparing']) }}" 
               class="px-4 py-2 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'preparing' ? 'bg-sky-600 border-sky-500 text-white shadow-lg shadow-sky-600/30' : 'bg-sky-500/10 border-sky-500/20 text-sky-300 hover:bg-sky-500/20' }}">
                <i class="fi fi-rr-flame text-xs"></i>
                <span>HAZIRLANIYOR</span>
                <span class="px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-200 text-[10px] font-mono border border-sky-500/30">{{ $stats['preparing'] }}</span>
            </a>

            <!-- 3. TESLİM EDİLDİ -->
            <a href="{{ route('kitchen.index', ['status' => 'delivered']) }}" 
               class="px-4 py-2 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'delivered' ? 'bg-emerald-600 border-emerald-500 text-white shadow-lg shadow-emerald-600/30' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300 hover:bg-emerald-500/20' }}">
                <i class="fi fi-rr-check-circle text-xs"></i>
                <span>TESLİM EDİLDİ</span>
                <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-200 text-[10px] font-mono border border-emerald-500/30">{{ $stats['delivered'] }}</span>
            </a>

            <!-- 4. İPTAL -->
            <a href="{{ route('kitchen.index', ['status' => 'cancelled']) }}" 
               class="px-4 py-2 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'cancelled' ? 'bg-rose-600 border-rose-500 text-white shadow-lg shadow-rose-600/30' : 'bg-rose-500/10 border-rose-500/20 text-rose-300 hover:bg-rose-500/20' }}">
                <i class="fi fi-rr-cross-circle text-xs"></i>
                <span>İPTAL</span>
                <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-200 text-[10px] font-mono border border-rose-500/30">{{ $stats['cancelled'] }}</span>
            </a>
        </div>
    </div>

    <!-- Notification Toast Container -->
    <div id="toastContainer" class="fixed top-24 right-6 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- MAIN ORDERS VIEW (SIDE-BY-SIDE KANBAN COLUMNS) -->
    <div class="flex-1 overflow-x-auto p-4 sm:p-6 bg-[#07090e] custom-scrollbar">
        @if($checks->isEmpty())
            <div class="h-full min-h-[400px] flex flex-col items-center justify-center text-center p-8 text-slate-500">
                <div class="w-20 h-20 rounded-3xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-600 mb-4">
                    <i class="fi fi-rr-restaurant text-4xl"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-300">
                    Seçilen kategoride kayıtlı sipariş bulunmuyor.
                </h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm">
                    Garsonlar masalardan "Mutfak'a Gönder" butonuna bastığında siparişler anında ekrana sesli olarak düşecektir.
                </p>
            </div>
        @else
            <!-- 4 SIDE-BY-SIDE COLUMNS (KANBAN BOARD) -->
            <div class="flex gap-5 h-full min-w-max">
                
                @php
                    $columns = [
                        [
                            'key' => 'received',
                            'title' => '1. ALINDI',
                            'subtitle' => 'Yeni mutfağa düşenler',
                            'icon' => 'fi-rr-inbox-in',
                            'badgeClass' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                            'count' => $stats['received'],
                        ],
                        [
                            'key' => 'preparing',
                            'title' => '2. HAZIRLANIYOR',
                            'subtitle' => 'Ocakta hazırlananlar',
                            'icon' => 'fi-rr-flame',
                            'badgeClass' => 'bg-sky-500/20 text-sky-300 border-sky-500/30',
                            'count' => $stats['preparing'],
                        ],
                        [
                            'key' => 'delivered',
                            'title' => '3. TESLİM EDİLDİ',
                            'subtitle' => 'Servise hazır / teslim edilenler',
                            'icon' => 'fi-rr-check-circle',
                            'badgeClass' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                            'count' => $stats['delivered'],
                        ],
                        [
                            'key' => 'cancelled',
                            'title' => '4. İPTAL EDİLENLER',
                            'subtitle' => 'İptal edilen siparişler',
                            'icon' => 'fi-rr-cross-circle',
                            'badgeClass' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                            'count' => $stats['cancelled'],
                        ]
                    ];
                @endphp

                @foreach($columns as $col)
                    @if($selectedStatus === 'all' || $selectedStatus === $col['key'])
                        <div class="w-80 sm:w-96 flex flex-col bg-[#0f1320] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl shrink-0">
                            <!-- Column Header -->
                            <div class="p-4 bg-[#14192b] border-b border-slate-800/80 flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <span class="w-8 h-8 rounded-xl {{ $col['badgeClass'] }} flex items-center justify-center text-sm font-bold border">
                                        <i class="fi {{ $col['icon'] }}"></i>
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-extrabold text-white">{{ $col['title'] }}</h3>
                                        <p class="text-[10px] text-slate-400">{{ $col['subtitle'] }}</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full {{ $col['badgeClass'] }} border text-xs font-mono font-bold">
                                    {{ $col['count'] }}
                                </span>
                            </div>

                            <!-- Column Cards Body -->
                            <div class="p-4 flex-1 overflow-y-auto space-y-4 custom-scrollbar">
                                @php $colCheckCount = 0; @endphp

                                @foreach($checks as $check)
                                    @php
                                        $colItems = $check->items->filter(function($i) use ($col) {
                                            $st = $i->is_cancelled ? 'cancelled' : ($i->kitchen_status ?: 'received');
                                            if ($st === 'sent' || $st === 'pending') $st = 'received';
                                            if ($st === 'ready' || $st === 'served') $st = 'delivered';

                                            if ($col['key'] === 'cancelled') {
                                                return $i->is_cancelled || $st === 'cancelled';
                                            }
                                            return !$i->is_cancelled && $st === $col['key'];
                                        });
                                    @endphp

                                    @if($colItems->isNotEmpty())
                                        @php
                                            $colCheckCount++;
                                            $table = $check->diningTable;
                                            $tableName = $table ? $table->name : 'Tezgah / Hızlı Satış';
                                            $hallName = $table?->hall?->name ?: 'Genel';
                                            $elapsedMinutes = $check->kitchen_sent_at ? (int) $check->kitchen_sent_at->diffInMinutes(now()) : 0;
                                            $isUrgent = $elapsedMinutes >= 15;
                                        @endphp

                                        <div id="check-card-{{ $col['key'] }}-{{ $check->id }}" class="kitchen-card flex flex-col bg-[#111524] border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                                            
                                            <!-- Ticket Header -->
                                            <div class="p-3.5 border-b border-slate-800 flex items-center justify-between {{ $isUrgent ? 'bg-rose-950/40' : 'bg-[#15192b]' }}">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-8 h-8 rounded-xl {{ $isUrgent ? 'bg-rose-600' : 'bg-indigo-600' }} text-white font-black flex items-center justify-center text-xs shadow-md">
                                                        {{ preg_replace('/[^0-9]/', '', $tableName) ?: 'M' }}
                                                    </div>
                                                    <div>
                                                        <h4 class="text-xs font-extrabold text-white leading-tight flex items-center gap-1.5">
                                                            {{ $tableName }}
                                                            <span class="text-[9px] font-semibold text-slate-400">({{ $hallName }})</span>
                                                        </h4>
                                                        <p class="text-[10px] text-slate-400">
                                                            {{ $check->waiter?->name ?? 'Garson' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="text-right">
                                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold border {{ $isUrgent ? 'bg-rose-500/20 text-rose-300 border-rose-500/30 animate-pulse' : 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' }}">
                                                        <i class="fi fi-rr-clock text-[9px] me-0.5"></i>{{ $elapsedMinutes }} dk
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Ticket Items -->
                                            <div class="p-3 flex flex-col gap-2">
                                                @foreach($colItems as $item)
                                                    @php
                                                        $itemStatus = $item->is_cancelled ? 'cancelled' : ($item->kitchen_status ?: 'received');
                                                        if ($itemStatus === 'sent' || $itemStatus === 'pending') $itemStatus = 'received';
                                                        if ($itemStatus === 'ready' || $itemStatus === 'served') $itemStatus = 'delivered';
                                                    @endphp

                                                    <div id="item-row-{{ $item->id }}" class="p-2.5 rounded-xl bg-[#161a2e] border border-slate-800 flex flex-col gap-2">
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="flex-1 min-w-0">
                                                                <div class="flex items-center gap-1.5">
                                                                    <span class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 text-xs font-black shrink-0">
                                                                        {{ number_format($item->quantity, 0) }}x
                                                                    </span>
                                                                    <span class="font-bold text-xs text-slate-100 {{ $itemStatus === 'cancelled' ? 'line-through text-rose-400' : '' }}">
                                                                        {{ $item->product_name }}
                                                                    </span>
                                                                </div>
                                                                @if($item->notes)
                                                                    <p class="text-[10px] text-amber-300 font-medium mt-1 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/20 inline-block">
                                                                        <i class="fi fi-rr-document text-[9px] me-1"></i>{{ $item->notes }}
                                                                    </p>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <!-- Quick Action Button per Column -->
                                                        <div class="flex items-center gap-1 pt-1 border-t border-slate-800/60">
                                                            @if($col['key'] === 'received')
                                                                <button onclick="setItemKitchenStatus({{ $item->id }}, 'preparing')" class="w-full py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-[10px] transition flex items-center justify-center gap-1 shadow cursor-pointer">
                                                                    <i class="fi fi-rr-flame text-[10px]"></i>
                                                                    <span>Hazırlanıyor Yap</span>
                                                                </button>
                                                            @elseif($col['key'] === 'preparing')
                                                                <button onclick="setItemKitchenStatus({{ $item->id }}, 'delivered')" class="w-full py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] transition flex items-center justify-center gap-1 shadow cursor-pointer">
                                                                    <i class="fi fi-rr-check-circle text-[10px]"></i>
                                                                    <span>Teslim Edildi Yap</span>
                                                                </button>
                                                            @elseif($col['key'] === 'delivered')
                                                                <button onclick="setItemKitchenStatus({{ $item->id }}, 'preparing')" class="w-full py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-[10px] transition flex items-center justify-center gap-1 cursor-pointer">
                                                                    <i class="fi fi-rr-undo text-[10px]"></i>
                                                                    <span>Geri Al</span>
                                                                </button>
                                                            @else
                                                                <button onclick="setItemKitchenStatus({{ $item->id }}, 'received')" class="w-full py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-[10px] transition flex items-center justify-center gap-1 cursor-pointer">
                                                                    <i class="fi fi-rr-refresh text-[10px]"></i>
                                                                    <span>Tekrar Aç</span>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <!-- Ticket Footer: Bulk Actions -->
                                            <div class="p-2.5 bg-[#15192b] border-t border-slate-800 flex items-center justify-between">
                                                <span class="text-[9px] text-slate-400 font-mono">#{{ $check->check_number }}</span>
                                                
                                                @if($col['key'] === 'received')
                                                    <button onclick="setCheckKitchenStatus({{ $check->id }}, 'preparing')" class="px-2.5 py-1 rounded-lg bg-sky-500/20 text-sky-300 hover:bg-sky-500 hover:text-white border border-sky-500/30 text-[9px] font-bold transition cursor-pointer">
                                                        Tümünü Hazırla
                                                    </button>
                                                @elseif($col['key'] === 'preparing')
                                                    <button onclick="setCheckKitchenStatus({{ $check->id }}, 'delivered')" class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500 hover:text-white border border-emerald-500/30 text-[9px] font-bold transition cursor-pointer">
                                                        Tümünü Tamamla
                                                    </button>
                                                @endif
                                            </div>

                                        </div>
                                    @endif
                                @endforeach

                                @if($colCheckCount === 0)
                                    <div class="py-12 text-center text-slate-500">
                                        <i class="fi {{ $col['icon'] }} text-2xl mb-2 block opacity-40"></i>
                                        <p class="text-xs">Bu kolonda sipariş yok.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach

            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    let isSoundEnabled = true;
    let lastKitchenTime = "{{ $latestKitchenTime ?? '' }}";

    // Web Audio Synthesizer Sound Bell (DING DONG Ring)
    function playKitchenChimeSound() {
        if (!isSoundEnabled) return;

        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();

            // First Bell Tone (High C)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(880, ctx.currentTime); // A5
            gain1.gain.setValueAtTime(0.3, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.6);

            // Second Bell Tone (Higher E)
            setTimeout(() => {
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(1320, ctx.currentTime); // E6
                gain2.gain.setValueAtTime(0.4, ctx.currentTime);
                gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(ctx.currentTime);
                osc2.stop(ctx.currentTime + 0.8);
            }, 150);
        } catch(e) {
            console.error('Audio play error:', e);
        }
    }

    function toggleAudioSound() {
        isSoundEnabled = !isSoundEnabled;
        const icon = document.getElementById('soundIcon');
        const text = document.getElementById('soundText');
        const btn = document.getElementById('btnSoundToggle');

        if (isSoundEnabled) {
            icon.innerText = '🔊';
            text.innerText = 'Sesli Uyarı Açık';
            btn.className = 'px-3.5 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 hover:bg-emerald-500 hover:text-white transition-all flex items-center gap-2 text-xs font-bold shadow-sm';
            playKitchenChimeSound();
        } else {
            icon.innerText = '🔇';
            text.innerText = 'Sesli Uyarı Kapalı';
            btn.className = 'px-3.5 py-2 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 transition-all flex items-center gap-2 text-xs font-bold';
        }
    }

    // Live Polling Servisi (Her 3 Saniyede Bir Kontrol Et)
    setInterval(async function() {
        try {
            const res = await fetch(`/kitchen/poll?last_time=${encodeURIComponent(lastKitchenTime)}`);
            const data = await res.json();

            if (data.has_new) {
                lastKitchenTime = data.latest_time;
                playKitchenChimeSound();
                showToast(`🔔 YENİ SİPARİŞ DÜŞTÜ! (${data.table_name})`);
                setTimeout(() => window.location.reload(), 800);
            }
        } catch(e) {
            console.error('Polling error:', e);
        }
    }, 3000);

    // Single Item Status Update
    async function setItemKitchenStatus(itemId, newStatus) {
        try {
            const response = await fetch(`/kitchen/items/${itemId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status: newStatus })
            });

            const data = await response.json();
            if (data.success) {
                showToast(`Durum: ${newStatus.toUpperCase()}`);
                setTimeout(() => window.location.reload(), 250);
            }
        } catch (e) {
            console.error('Kitchen item update error:', e);
        }
    }

    // Mass Check Items Status Update (4 Categories)
    async function setCheckKitchenStatus(checkId, newStatus) {
        try {
            const response = await fetch(`/kitchen/${checkId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status: newStatus })
            });

            const data = await response.json();
            if (data.success) {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 250);
            }
        } catch (e) {
            console.error('Kitchen check update error:', e);
        }
    }

    function showToast(msg) {
        const container = document.getElementById('toastContainer');
        const alert = document.createElement('div');
        alert.className = `bg-indigo-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl backdrop-blur-md text-xs font-black flex items-center gap-2.5 border border-indigo-400/40 animate-bounce`;
        alert.innerHTML = `<i class="fi fi-rr-bell text-base text-amber-300"></i> ${msg}`;
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 3500);
    }
</script>
@endsection
