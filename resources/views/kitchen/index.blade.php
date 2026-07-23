@extends('layouts.app')

@section('title', '👨‍🍳 Mutfak Sipariş Ekranı (KDS)')

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
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .kitchen-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 32px -10px rgba(16, 185, 129, 0.2);
    }
</style>
@endsection

@section('content')
<div class="flex flex-col h-screen bg-[#07090e] text-slate-100 font-sans overflow-hidden">
    
    <!-- Top Header Bar -->
    <header class="h-16 bg-[#0f131f]/90 border-b border-slate-800/80 px-4 sm:px-6 flex items-center justify-between z-20 shrink-0 backdrop-blur-md">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-all border border-slate-700/50">
                <i class="fi fi-rr-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-lg font-extrabold tracking-tight text-white flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <i class="fi fi-rr-restaurant"></i>
                    </span>
                    Mutfak Sipariş Ekranı (KDS)
                </h1>
                <p class="text-xs text-slate-400">Canlı Mutfak Hazırlık & Sipariş Takip Portalı</p>
            </div>
        </div>

        <!-- Right Tools & Filter Tabs -->
        <div class="flex items-center gap-3">
            <div class="hidden md:flex items-center gap-3 px-3 py-1.5 rounded-xl bg-slate-900/80 border border-slate-800 text-xs text-slate-300">
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span>Bekleyen Kalem: <strong class="text-emerald-400 font-mono text-sm">{{ $stats['pending_items'] }}</strong></span>
            </div>

            <!-- View Status Filter -->
            <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-xl border border-slate-800">
                <a href="{{ route('kitchen.index', ['status' => 'active']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $status === 'active' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-400 hover:text-white' }}">
                    Bekleyenler ({{ $stats['total_kitchen_orders'] }})
                </a>
                <a href="{{ route('kitchen.index', ['status' => 'completed']) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all {{ $status === 'completed' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-400 hover:text-white' }}">
                    Tamamlananlar
                </a>
            </div>

            <!-- Auto Refresh Button -->
            <button onclick="window.location.reload()" class="p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700/50 transition-all" title="Sayfayı Yenile">
                <i class="fi fi-rr-refresh text-sm"></i>
            </button>
        </div>
    </header>

    <!-- Notification Toast Container -->
    <div id="toastContainer" class="fixed top-20 right-6 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- Main Kitchen Orders Container -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-4 sm:p-6 bg-[#090b13]">
        @if($checks->isEmpty())
            <div class="h-full min-h-[400px] flex flex-col items-center justify-center text-center p-8 text-slate-500">
                <div class="w-20 h-20 rounded-3xl bg-slate-900/80 border border-slate-800/80 flex items-center justify-center text-slate-600 mb-4">
                    <i class="fi fi-rr-restaurant text-4xl"></i>
                </div>
                <h3 class="text-lg font-extrabold text-slate-300">
                    {{ $status === 'completed' ? 'Tamamlanmış sipariş bulunmuyor.' : 'Mutfakta bekleyen aktif sipariş yok.' }}
                </h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm">
                    Garsonlar masalardan "Mutfak'a Gönder" butonuna bastığında siparişler anında bu ekrana düşecektir.
                </p>
            </div>
        @else
            <!-- Grid of Kitchen Ticket Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                @foreach($checks as $check)
                    @php
                        $table = $check->diningTable;
                        $tableName = $table ? $table->name : 'Tezgah / Hızlı Satış';
                        $hallName = $table?->hall?->name ?: 'Genel';
                        $elapsedMinutes = $check->kitchen_sent_at ? (int) $check->kitchen_sent_at->diffInMinutes(now()) : 0;
                        $isUrgent = $elapsedMinutes >= 15;
                    @endphp

                    <div id="check-card-{{ $check->id }}" class="kitchen-card flex flex-col bg-[#111524] border border-slate-800/90 rounded-3xl overflow-hidden shadow-xl">
                        
                        <!-- Ticket Header -->
                        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between {{ $isUrgent ? 'bg-rose-950/40' : 'bg-slate-900/70' }}">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl {{ $isUrgent ? 'bg-rose-600' : 'bg-emerald-600' }} text-white font-black flex items-center justify-center text-base shadow-md">
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
                                <span class="px-2.5 py-1 rounded-xl text-xs font-mono font-bold border {{ $isUrgent ? 'bg-rose-500/20 text-rose-300 border-rose-500/30 animate-pulse' : 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' }}">
                                    ⏱️ {{ $elapsedMinutes }} dk
                                </span>
                                <span class="block text-[10px] text-slate-500 font-mono mt-1">
                                    {{ $check->kitchen_sent_at ? $check->kitchen_sent_at->format('H:i') : '--:--' }}
                                </span>
                            </div>
                        </div>

                        <!-- Ticket Items List -->
                        <div class="p-4 flex-1 flex flex-col gap-2.5 overflow-y-auto max-h-80 custom-scrollbar">
                            @foreach($check->items as $item)
                                @php
                                    $itemStatus = $item->kitchen_status ?: 'sent';
                                    $statusBadge = match($itemStatus) {
                                        'sent' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        'preparing' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                                        'ready' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30 line-through opacity-60',
                                        'served' => 'bg-slate-800 text-slate-500 border-slate-700 line-through opacity-40',
                                        default => 'bg-slate-800 text-slate-400',
                                    };
                                    $statusLabel = match($itemStatus) {
                                        'sent' => 'Bekliyor',
                                        'preparing' => 'Hazırlanıyor',
                                        'ready' => 'Hazır ✓',
                                        'served' => 'Teslim Edildi',
                                        default => 'Bekliyor',
                                    };
                                @endphp

                                <div id="item-row-{{ $item->id }}" class="flex items-center justify-between p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-lg bg-emerald-500/20 text-emerald-300 text-xs font-black shrink-0">
                                                {{ number_format($item->quantity, 0) }}x
                                            </span>
                                            <span class="font-extrabold text-sm text-slate-100 truncate">
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

                                    <!-- Item Action Button -->
                                    <button onclick="cycleItemStatus({{ $item->id }}, '{{ $itemStatus }}')" id="item-btn-{{ $item->id }}"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all shrink-0 cursor-pointer {{ $statusBadge }}">
                                        {{ $statusLabel }}
                                    </button>
                                </div>
                            @endforeach
                        </div>

                        <!-- Ticket Footer -->
                        <div class="p-3 bg-slate-900/90 border-t border-slate-800/80 flex items-center justify-between">
                            <span class="text-[11px] text-slate-400 font-mono">
                                #{{ $check->check_number }}
                            </span>
                            <button onclick="completeCheckKitchen({{ $check->id }})" class="px-3.5 py-1.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-300 hover:text-white text-xs font-bold transition-all flex items-center gap-1.5">
                                <i class="fi fi-rr-check"></i>
                                <span>Tümünü Hazır Et</span>
                            </button>
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
    // Auto Refresh page every 10 seconds to get live kitchen orders
    setInterval(function() {
        window.location.reload();
    }, 10000);

    // Cycle Item Kitchen Status (sent -> preparing -> ready -> served)
    async function cycleItemStatus(itemId, currentStatus) {
        const nextMap = {
            'sent': 'preparing',
            'preparing': 'ready',
            'ready': 'served',
            'served': 'sent'
        };
        const nextStatus = nextMap[currentStatus] || 'preparing';

        try {
            const response = await fetch(`/kitchen/items/${itemId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status: nextStatus })
            });

            const data = await response.json();
            if(data.success) {
                showToast('Ürün durumu güncellendi.');
                setTimeout(() => window.location.reload(), 300);
            }
        } catch(e) {
            console.error(e);
        }
    }

    // Complete Entire Check in Kitchen
    async function completeCheckKitchen(checkId) {
        try {
            const response = await fetch(`/kitchen/${checkId}/complete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            const data = await response.json();
            if(data.success) {
                showToast(data.message);
                setTimeout(() => window.location.reload(), 300);
            }
        } catch(e) {
            console.error(e);
        }
    }

    function showToast(msg) {
        const container = document.getElementById('toastContainer');
        const alert = document.createElement('div');
        alert.className = `bg-emerald-600/90 text-white px-4 py-3 rounded-2xl shadow-xl backdrop-blur-md text-xs font-semibold flex items-center gap-2 transition-all duration-300`;
        alert.innerHTML = `<i class="fi fi-rr-check-circle text-base"></i> ${msg}`;
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 2500);
    }
</script>
@endsection
