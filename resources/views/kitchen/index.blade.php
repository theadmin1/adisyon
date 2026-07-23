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
               class="px-4 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'all' ? 'bg-indigo-600 border-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'bg-slate-900/80 border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800' }}">
                <i class="fi fi-rr-apps text-sm"></i>
                <span>TÜM SİPARİŞLER</span>
                <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-mono">{{ $stats['total'] }}</span>
            </a>

            <div class="h-6 w-[1px] bg-slate-800 mx-1"></div>

            <!-- 1. ALINDI -->
            <a href="{{ route('kitchen.index', ['status' => 'received']) }}" 
               class="px-4 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'received' ? 'bg-amber-600 border-amber-500 text-white shadow-lg shadow-amber-600/30' : 'bg-amber-500/10 border-amber-500/20 text-amber-300 hover:bg-amber-500/20' }}">
                <span class="text-sm">📥</span>
                <span>ALINDI</span>
                <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-200 text-[10px] font-mono border border-amber-500/30">{{ $stats['received'] }}</span>
            </a>

            <!-- 2. HAZIRLANIYOR -->
            <a href="{{ route('kitchen.index', ['status' => 'preparing']) }}" 
               class="px-4 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'preparing' ? 'bg-sky-600 border-sky-500 text-white shadow-lg shadow-sky-600/30' : 'bg-sky-500/10 border-sky-500/20 text-sky-300 hover:bg-sky-500/20' }}">
                <span class="text-sm">🔥</span>
                <span>HAZIRLANIYOR</span>
                <span class="px-2 py-0.5 rounded-full bg-sky-500/20 text-sky-200 text-[10px] font-mono border border-sky-500/30">{{ $stats['preparing'] }}</span>
            </a>

            <!-- 3. TESLİM EDİLDİ -->
            <a href="{{ route('kitchen.index', ['status' => 'delivered']) }}" 
               class="px-4 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'delivered' ? 'bg-emerald-600 border-emerald-500 text-white shadow-lg shadow-emerald-600/30' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300 hover:bg-emerald-500/20' }}">
                <span class="text-sm">✅</span>
                <span>TESLİM EDİLDİ</span>
                <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-200 text-[10px] font-mono border border-emerald-500/30">{{ $stats['delivered'] }}</span>
            </a>

            <!-- 4. İPTAL -->
            <a href="{{ route('kitchen.index', ['status' => 'cancelled']) }}" 
               class="px-4 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $selectedStatus === 'cancelled' ? 'bg-rose-600 border-rose-500 text-white shadow-lg shadow-rose-600/30' : 'bg-rose-500/10 border-rose-500/20 text-rose-300 hover:bg-rose-500/20' }}">
                <span class="text-sm">❌</span>
                <span>İPTAL</span>
                <span class="px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-200 text-[10px] font-mono border border-rose-500/30">{{ $stats['cancelled'] }}</span>
            </a>
        </div>
    </div>

    <!-- Notification Toast Container -->
    <div id="toastContainer" class="fixed top-24 right-6 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- MAIN ORDERS VIEW (TICKETS) -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-6 bg-[#07090e]">
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
            <!-- Grid of Kitchen Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach($checks as $check)
                    @php
                        $table = $check->diningTable;
                        $tableName = $table ? $table->name : 'Tezgah / Hızlı Satış';
                        $hallName = $table?->hall?->name ?: 'Genel';
                        $elapsedMinutes = $check->kitchen_sent_at ? (int) $check->kitchen_sent_at->diffInMinutes(now()) : 0;
                        $isUrgent = $elapsedMinutes >= 15;
                    @endphp

                    <div id="check-card-{{ $check->id }}" class="kitchen-card flex flex-col bg-[#111524] border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
                        
                        <!-- Ticket Header -->
                        <div class="p-4 border-b border-slate-800 flex items-center justify-between {{ $isUrgent ? 'bg-rose-950/40' : 'bg-[#15192b]' }}">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl {{ $isUrgent ? 'bg-rose-600' : 'bg-indigo-600' }} text-white font-black flex items-center justify-center text-base shadow-lg">
                                    {{ preg_replace('/[^0-9]/', '', $tableName) ?: 'M' }}
                                </div>
                                <div>
                                    <h3 class="text-base font-extrabold text-white leading-tight flex items-center gap-2">
                                        {{ $tableName }}
                                        <span class="text-[10px] font-semibold text-slate-400">({{ $hallName }})</span>
                                    </h3>
                                    <p class="text-[11px] text-slate-400">
                                        Garson: <strong class="text-slate-200">{{ $check->waiter?->name ?? 'Garson' }}</strong>
                                    </p>
                                </div>
                            </div>

                            <div class="text-right">
                                <span class="px-2.5 py-1 rounded-xl text-xs font-mono font-bold border {{ $isUrgent ? 'bg-rose-500/20 text-rose-300 border-rose-500/30 animate-pulse' : 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' }}">
                                    ⏱️ {{ $elapsedMinutes }} dk
                                </span>
                                <span class="block text-[10px] text-slate-500 font-mono mt-1">
                                    {{ $check->kitchen_sent_at ? $check->kitchen_sent_at->format('H:i') : '--:--' }}
                                </span>
                            </div>
                        </div>

                        <!-- Ticket Items List -->
                        <div class="p-4 flex-1 flex flex-col gap-3 overflow-y-auto max-h-96 custom-scrollbar">
                            @foreach($check->items as $item)
                                @php
                                    $itemStatus = $item->is_cancelled ? 'cancelled' : ($item->kitchen_status ?: 'received');
                                    if ($itemStatus === 'sent' || $itemStatus === 'pending') $itemStatus = 'received';
                                    if ($itemStatus === 'ready' || $itemStatus === 'served') $itemStatus = 'delivered';
                                @endphp

                                <div id="item-row-{{ $item->id }}" class="p-3.5 rounded-2xl bg-[#161a2e] border border-slate-800 flex flex-col gap-2.5">
                                    <!-- Item Header Info -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2.5 py-1 rounded-lg bg-indigo-500/20 text-indigo-300 text-xs font-black shrink-0">
                                                    {{ number_format($item->quantity, 0) }}x
                                                </span>
                                                <span class="font-black text-sm text-slate-100 {{ $itemStatus === 'cancelled' ? 'line-through text-rose-400' : '' }}">
                                                    {{ $item->product_name }}
                                                </span>
                                            </div>
                                            @if($item->notes)
                                                <p class="text-[11px] text-amber-300 font-medium mt-1 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/20 inline-block">
                                                    📝 {{ $item->notes }}
                                                </p>
                                            @endif
                                            @if($item->product?->kitchen_department)
                                                <span class="block text-[10px] text-slate-500 mt-0.5">
                                                    {{ $item->product->kitchen_department }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- 4 KATEGORİ ANLIK DURUM SEÇİCİSİ (ALINDI / HAZIRLANIYOR / TESLİM EDİLDİ / İPTAL) -->
                                    <div class="grid grid-cols-4 gap-1 p-1 bg-slate-900/90 rounded-xl border border-slate-800">
                                        <button onclick="setItemKitchenStatus({{ $item->id }}, 'received')" 
                                                class="py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center cursor-pointer {{ $itemStatus === 'received' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-400 hover:text-amber-300 hover:bg-amber-500/10' }}">
                                            📥 ALINDI
                                        </button>
                                        <button onclick="setItemKitchenStatus({{ $item->id }}, 'preparing')" 
                                                class="py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center cursor-pointer {{ $itemStatus === 'preparing' ? 'bg-sky-500 text-slate-950 shadow' : 'text-slate-400 hover:text-sky-300 hover:bg-sky-500/10' }}">
                                            🔥 HAZIRL.
                                        </button>
                                        <button onclick="setItemKitchenStatus({{ $item->id }}, 'delivered')" 
                                                class="py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center cursor-pointer {{ $itemStatus === 'delivered' ? 'bg-emerald-500 text-slate-950 shadow' : 'text-slate-400 hover:text-emerald-300 hover:bg-emerald-500/10' }}">
                                            ✅ TESLİM
                                        </button>
                                        <button onclick="setItemKitchenStatus({{ $item->id }}, 'cancelled')" 
                                                class="py-1.5 rounded-lg text-[10px] font-black transition-all flex items-center justify-center cursor-pointer {{ $itemStatus === 'cancelled' ? 'bg-rose-600 text-white shadow' : 'text-slate-400 hover:text-rose-400 hover:bg-rose-500/10' }}">
                                            ❌ İPTAL
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Ticket Footer: MASADAKİ TÜM SİPARİŞİ TEK SEFERDE TOPLU SEÇME (4 KATEGORİ) -->
                        <div class="p-3 bg-[#15192b] border-t border-slate-800 flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] text-slate-400 font-mono">
                                    #{{ $check->check_number }} · TOPLU İŞLEM:
                                </span>
                            </div>

                            <div class="grid grid-cols-4 gap-1">
                                <!-- 1. TÜMÜNÜ ALINDI YAP -->
                                <button onclick="setCheckKitchenStatus({{ $check->id }}, 'received')" 
                                        class="py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-slate-950 border border-amber-500/20 text-[9px] font-black transition-all text-center"
                                        title="Masadaki tüm ürünleri ALINDI yap">
                                    📥 ALINDI
                                </button>

                                <!-- 2. TÜMÜNÜ HAZIRLA -->
                                <button onclick="setCheckKitchenStatus({{ $check->id }}, 'preparing')" 
                                        class="py-1.5 rounded-lg bg-sky-500/10 hover:bg-sky-500 text-sky-400 hover:text-slate-950 border border-sky-500/20 text-[9px] font-black transition-all text-center"
                                        title="Masadaki tüm ürünleri HAZIRLANIYOR yap">
                                    🔥 HAZIRLA
                                </button>

                                <!-- 3. TÜMÜNÜ TESLİM ET -->
                                <button onclick="setCheckKitchenStatus({{ $check->id }}, 'delivered')" 
                                        class="py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-white border border-emerald-500/20 text-[9px] font-black transition-all text-center"
                                        title="Masadaki tüm ürünleri TESLİM EDİLDİ yap">
                                    ✅ TESLİM
                                </button>

                                <!-- 4. TÜMÜNÜ İPTAL ET -->
                                <button onclick="setCheckKitchenStatus({{ $check->id }}, 'cancelled')" 
                                        class="py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-600 text-rose-400 hover:text-white border border-rose-500/20 text-[9px] font-black transition-all text-center"
                                        title="Masadaki tüm ürünleri İPTAL ET">
                                    ❌ İPTAL
                                </button>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    let isSoundEnabled = true;
    let lastKitchenTime = "{{ $latestKitchenTime?->toIso8601String() ?? '' }}";

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
