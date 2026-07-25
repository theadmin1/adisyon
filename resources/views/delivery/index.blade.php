@extends('layouts.app')

@section('title', 'Paket Servis & Entegrasyon Konsolu - Adisyon POS')

@section('styles')
<style>
    .channel-badge-trendyol { background: rgba(249, 115, 22, 0.15); color: #fb923c; border: 1px solid rgba(249, 115, 22, 0.3); }
    .channel-badge-yemeksepeti { background: rgba(236, 72, 153, 0.15); color: #f472b6; border: 1px solid rgba(236, 72, 153, 0.3); }
    .channel-badge-getir { background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); }
    .channel-badge-migros { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
    .channel-badge-phone { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
</style>
@endsection

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans selection:bg-sky-500 selection:text-white">

    <!-- 🔝 TOP NAVIGATION HEADER -->
    <header class="bg-[#10131e] border-b border-slate-800/80 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
        <!-- LEFT: Back & Title -->
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition cursor-pointer" title="Ana Menüye Dön">
                <i class="fi fi-rr-arrow-left text-sm"></i>
            </a>
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center">
                    <i class="fi fi-rr-box-alt text-lg"></i>
                </div>
                <div>
                    <h1 class="text-sm sm:text-base font-extrabold text-white leading-tight tracking-wide uppercase">Paket Servis & Entegrasyonlar</h1>
                    <p class="text-[11px] text-slate-400">Trendyol, Yemeksepeti, Getir, Migros & Telefon Siparişleri</p>
                </div>
            </div>
        </div>

        <!-- CENTER: Integration Status Badges -->
        <div class="hidden lg:flex items-center gap-2 bg-slate-900/90 border border-slate-800/80 p-1.5 rounded-2xl text-xs">
            @foreach(['trendyol' => 'Trendyol Go', 'yemeksepeti' => 'Yemeksepeti', 'getir' => 'GetirYemek', 'migros' => 'Migros Yemek'] as $key => $name)
                @php $integ = $integrations[$key] ?? null; @endphp
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-xl {{ $key === 'trendyol' ? 'channel-badge-trendyol' : ($key === 'yemeksepeti' ? 'channel-badge-yemeksepeti' : ($key === 'getir' ? 'channel-badge-getir' : 'channel-badge-migros')) }}">
                    <span class="w-2 h-2 rounded-full {{ ($integ && $integ->is_active) ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span>
                    <span class="font-bold text-[11px]">{{ $name }}</span>
                    @if($integ && $integ->auto_accept)
                        <span class="text-[9px] bg-slate-950/40 px-1.5 py-0.5 rounded font-mono font-bold" title="Otomatik Onay Açık">AUTO</span>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- RIGHT: Action Buttons & Simulator -->
        <div class="flex items-center gap-2 ml-auto lg:ml-0">
            
            <!-- SIMULATE DROP-DOWN (DEMO & TEST) -->
            <div class="relative group">
                <button type="button" class="px-3 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/30 text-purple-300 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fi fi-rr-bolt text-xs"></i>
                    <span>Sipariş Simüle Et</span>
                    <i class="fi fi-rr-angle-small-down text-xs"></i>
                </button>
                <div class="absolute right-0 mt-1 w-48 bg-[#141724] border border-slate-800 rounded-2xl shadow-2xl p-1.5 hidden group-hover:block z-50">
                    <button onclick="simulateOrder('trendyol')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-between cursor-pointer transition">
                        <span>Trendyol Go</span>
                        <i class="fi fi-rr-play text-[10px]"></i>
                    </button>
                    <button onclick="simulateOrder('yemeksepeti')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-pink-500/20 text-pink-400 text-xs font-bold flex items-center justify-between cursor-pointer transition">
                        <span>Yemeksepeti</span>
                        <i class="fi fi-rr-play text-[10px]"></i>
                    </button>
                    <button onclick="simulateOrder('getir')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-purple-500/20 text-purple-400 text-xs font-bold flex items-center justify-between cursor-pointer transition">
                        <span>GetirYemek</span>
                        <i class="fi fi-rr-play text-[10px]"></i>
                    </button>
                    <button onclick="simulateOrder('migros')" class="w-full text-left px-3 py-2 rounded-xl hover:bg-amber-500/20 text-amber-400 text-xs font-bold flex items-center justify-between cursor-pointer transition">
                        <span>Migros Yemek</span>
                        <i class="fi fi-rr-play text-[10px]"></i>
                    </button>
                </div>
            </div>

            <!-- ENTEGRASYON AYARLARI -->
            <button onclick="openIntegrationModal()" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                <i class="fi fi-rr-settings text-xs text-sky-400"></i>
                <span class="hidden sm:inline">Entegrasyon Ayarları</span>
            </button>

            <!-- YENİ TELEFON SİPARİŞİ -->
            <button onclick="openPhoneOrderModal()" class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-extrabold shadow-lg shadow-sky-900/30 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                <i class="fi fi-rr-phone-call text-xs"></i>
                <span>Telefon Siparişi</span>
            </button>
        </div>
    </header>

    <!-- 📊 STATS BANNER -->
    <div class="bg-[#0e111a] border-b border-slate-800/60 px-4 sm:px-6 py-2.5 grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs shrink-0">
        <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-slate-900/60 border border-slate-800">
            <i class="fi fi-rr-chart-histogram text-sky-400 text-base"></i>
            <div>
                <div class="text-[10px] text-slate-400 font-bold uppercase">Bugün Toplam</div>
                <div class="font-extrabold text-white text-sm font-mono">{{ $stats['total_today'] }} Sipariş</div>
            </div>
        </div>
        <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-rose-950/30 border border-rose-500/30">
            <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping"></span>
            <div>
                <div class="text-[10px] text-rose-400 font-bold uppercase">Onay Bekleyen (Yeni)</div>
                <div class="font-extrabold text-rose-300 text-sm font-mono">{{ $stats['new_count'] }} Sipariş</div>
            </div>
        </div>
        <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-amber-950/30 border border-amber-500/30">
            <i class="fi fi-rr-time-fast text-amber-400 text-base"></i>
            <div>
                <div class="text-[10px] text-amber-400 font-bold uppercase">Mutfakta Hazırlanıyor</div>
                <div class="font-extrabold text-amber-300 text-sm font-mono">{{ $stats['preparing_count'] }} Sipariş</div>
            </div>
        </div>
        <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-sky-950/30 border border-sky-500/30">
            <i class="fi fi-rr-motorcycle text-sky-400 text-base"></i>
            <div>
                <div class="text-[10px] text-sky-400 font-bold uppercase">Kuryede / Yolda</div>
                <div class="font-extrabold text-sky-300 text-sm font-mono">{{ $stats['on_the_way_count'] }} Sipariş</div>
            </div>
        </div>
        <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-xl bg-emerald-950/30 border border-emerald-500/30 col-span-2 sm:col-span-1">
            <i class="fi fi-rr-check-circle text-emerald-400 text-base"></i>
            <div>
                <div class="text-[10px] text-emerald-400 font-bold uppercase">Teslim Edildi</div>
                <div class="font-extrabold text-emerald-300 text-sm font-mono">{{ $stats['delivered_count'] }} Sipariş</div>
            </div>
        </div>
    </div>

    <!-- 🖥️ MAIN SPLIT SCREEN WORKSPACE -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
        
        <!-- 👈 LEFT COLUMN: ORDER LIST & FILTERS (~45% WIDTH) -->
        <div class="w-full lg:w-[45%] border-r border-slate-800/80 flex flex-col bg-[#0d0f18] overflow-hidden">
            
            <!-- FILTERS HEADER -->
            <div class="p-3.5 border-b border-slate-800/80 space-y-2.5 shrink-0 bg-slate-900/50">
                <!-- Channel Filter Buttons -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-xs">
                    <a href="{{ route('delivery.index', ['channel' => 'all', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 {{ $channelFilter === 'all' ? 'bg-sky-600 text-white shadow-md' : 'bg-slate-800 text-slate-400 hover:text-white' }}">
                        Tüm Kanallar
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => 'trendyol', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 {{ $channelFilter === 'trendyol' ? 'bg-orange-500 text-white shadow-md' : 'bg-slate-800 text-orange-400 hover:bg-slate-700' }}">
                        Trendyol Go
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => 'yemeksepeti', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 {{ $channelFilter === 'yemeksepeti' ? 'bg-pink-600 text-white shadow-md' : 'bg-slate-800 text-pink-400 hover:bg-slate-700' }}">
                        Yemeksepeti
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => 'getir', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 {{ $channelFilter === 'getir' ? 'bg-purple-600 text-white shadow-md' : 'bg-slate-800 text-purple-400 hover:bg-slate-700' }}">
                        GetirYemek
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => 'migros', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 {{ $channelFilter === 'migros' ? 'bg-amber-600 text-white shadow-md' : 'bg-slate-800 text-amber-400 hover:bg-slate-700' }}">
                        Migros Yemek
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => 'phone', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 {{ $channelFilter === 'phone' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-800 text-blue-400 hover:bg-slate-700' }}">
                        Telefon
                    </a>
                </div>

                <!-- Status Filter Tabs -->
                <div class="grid grid-cols-5 gap-1 bg-slate-950 p-1 rounded-xl text-[11px] font-bold">
                    <a href="{{ route('delivery.index', ['channel' => $channelFilter, 'status' => 'all']) }}" 
                        class="py-1.5 text-center rounded-lg transition {{ $statusFilter === 'all' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-slate-200' }}">
                        Tümü
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => $channelFilter, 'status' => 'new']) }}" 
                        class="py-1.5 text-center rounded-lg transition relative {{ $statusFilter === 'new' ? 'bg-rose-600 text-white' : 'text-rose-400 hover:text-rose-300' }}">
                        Yeni
                        @if($stats['new_count'] > 0)
                            <span class="ml-1 px-1 rounded bg-white/20 text-[9px] font-mono font-extrabold">{{ $stats['new_count'] }}</span>
                        @endif
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => $channelFilter, 'status' => 'preparing']) }}" 
                        class="py-1.5 text-center rounded-lg transition {{ $statusFilter === 'preparing' ? 'bg-amber-600 text-white' : 'text-amber-400 hover:text-amber-300' }}">
                        Mutfakta
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => $channelFilter, 'status' => 'on_the_way']) }}" 
                        class="py-1.5 text-center rounded-lg transition {{ $statusFilter === 'on_the_way' ? 'bg-sky-600 text-white' : 'text-sky-400 hover:text-sky-300' }}">
                        Kuryede
                    </a>
                    <a href="{{ route('delivery.index', ['channel' => $channelFilter, 'status' => 'delivered']) }}" 
                        class="py-1.5 text-center rounded-lg transition {{ $statusFilter === 'delivered' ? 'bg-emerald-600 text-white' : 'text-emerald-400 hover:text-emerald-300' }}">
                        Teslim
                    </a>
                </div>
            </div>

            <!-- ORDER CARDS SCROLLABLE LIST -->
            <div class="flex-1 overflow-y-auto p-3.5 space-y-3">
                @forelse($orders as $order)
                    @php
                        $badgeClass = match($order->channel) {
                            'trendyol' => 'channel-badge-trendyol',
                            'yemeksepeti' => 'channel-badge-yemeksepeti',
                            'getir' => 'channel-badge-getir',
                            'migros' => 'channel-badge-migros',
                            default => 'channel-badge-phone',
                        };
                        $channelName = match($order->channel) {
                            'trendyol' => 'Trendyol Go',
                            'yemeksepeti' => 'Yemeksepeti',
                            'getir' => 'GetirYemek',
                            'migros' => 'Migros Yemek',
                            default => 'Telefon Siparişi',
                        };
                    @endphp

                    <div onclick="selectOrder({{ $order->id }})" id="order-card-{{ $order->id }}" 
                        class="order-card p-4 rounded-2xl border transition-all cursor-pointer relative group ${orderId === {{ $order->id }} ? 'bg-slate-800/90 border-sky-500 shadow-xl' : 'bg-slate-900/70 border-slate-800 hover:border-slate-700'}">
                        
                        <!-- CARD HEADER -->
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-extrabold uppercase {{ $badgeClass }}">
                                    {{ $channelName }}
                                </span>
                                <span class="font-mono text-xs font-black text-white">#{{ $order->order_number }}</span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-mono flex items-center gap-1">
                                <i class="fi fi-rr-clock text-[10px]"></i>
                                {{ $order->created_at->diffForHumans(null, true) }}
                            </span>
                        </div>

                        <!-- CUSTOMER & ADDRESS SUMMARY -->
                        <div class="space-y-1 mb-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-extrabold text-sm text-slate-100 flex items-center gap-1.5">
                                    <i class="fi fi-rr-user text-xs text-sky-400"></i>
                                    {{ $order->customer_name }}
                                </h4>
                                <span class="font-mono font-extrabold text-emerald-400 text-xs">₺{{ number_format($order->total, 2) }}</span>
                            </div>
                            <p class="text-xs text-slate-400 line-clamp-1 flex items-start gap-1">
                                <i class="fi fi-rr-marker text-xs text-slate-500 shrink-0 mt-0.5"></i>
                                <span>{{ $order->delivery_address }}</span>
                            </p>
                        </div>

                        <!-- CARD FOOTER ACTIONS & STATUS -->
                        <div class="flex items-center justify-between pt-2 border-t border-slate-800/60 text-xs">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase
                                {{ $order->status === 'new' ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : '' }}
                                {{ $order->status === 'preparing' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : '' }}
                                {{ $order->status === 'on_the_way' ? 'bg-sky-500/20 text-sky-400 border border-sky-500/30' : '' }}
                                {{ $order->status === 'delivered' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : '' }}
                                {{ $order->status === 'cancelled' ? 'bg-slate-800 text-slate-400 border border-slate-700' : '' }}">
                                {{ match($order->status) {
                                    'new' => '🔴 Onay Bekliyor',
                                    'preparing' => '🟡 Hazırlanıyor',
                                    'on_the_way' => '🔵 Kuryede',
                                    'delivered' => '🟢 Teslim Edildi',
                                    'cancelled' => '❌ İptal',
                                    default => $order->status
                                } }}
                            </span>

                            <div class="flex items-center gap-1.5">
                                @if($order->status === 'new')
                                    <button onclick="event.stopPropagation(); updateOrderStatus({{ $order->id }}, 'preparing')" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] transition shadow">
                                        Kabul Et
                                    </button>
                                @elseif($order->status === 'preparing')
                                    <button onclick="event.stopPropagation(); updateOrderStatus({{ $order->id }}, 'on_the_way')" class="px-2.5 py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-[11px] transition shadow">
                                        Kuryeye Ver
                                    </button>
                                @elseif($order->status === 'on_the_way')
                                    <button onclick="event.stopPropagation(); updateOrderStatus({{ $order->id }}, 'delivered')" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] transition shadow">
                                        Teslim Edildi
                                    </button>
                                @endif
                            </div>
                        </div>

                    </div>
                @empty
                    <div class="p-8 text-center bg-slate-900/40 border border-slate-800/60 rounded-3xl space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-500 flex items-center justify-center mx-auto">
                            <i class="fi fi-rr-box-alt text-2xl"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-300">Sipariş Bulunarak Listelenemedi</h3>
                        <p class="text-xs text-slate-500">Filtreleri değiştirebilir veya yukarıdaki "Sipariş Simüle Et" butonu ile canlı sipariş simülasyonu yapabilirsiniz.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 👉 RIGHT COLUMN: SELECTED ORDER WORKSPACE (~55% WIDTH) -->
        <div class="flex-1 flex flex-col bg-[#090b12] overflow-y-auto p-4 sm:p-6 space-y-5" id="orderWorkspace">
            @if(count($orders) > 0)
                @php $activeOrder = $orders->first(); @endphp
                
                <!-- WORKSPACE HEADER -->
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-slate-900/80 border border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-400 border border-sky-500/20 flex items-center justify-center text-xl font-bold">
                            <i class="fi fi-rr-box text-xl"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-black text-white font-mono">#{{ $activeOrder->order_number }}</h2>
                                @if($activeOrder->platform_order_id)
                                    <span class="text-xs text-slate-400 font-mono">({{ $activeOrder->platform_order_id }})</span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400">Sipariş Zamanı: {{ $activeOrder->received_at ? $activeOrder->received_at->format('H:i - d.m.Y') : '--:--' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Print Job Action -->
                        <button onclick="printDeliveryReceipt({{ $activeOrder->id }})" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                            <i class="fi fi-rr-print text-xs text-indigo-400"></i>
                            <span>Fiş Yazdır</span>
                        </button>
                    </div>
                </div>

                <!-- CUSTOMER & ADDRESS DETAILS CARD -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Customer Card -->
                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-2">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                            <i class="fi fi-rr-user text-sky-400"></i>
                            Müşteri İletişim Bilgileri
                        </h4>
                        <div class="font-extrabold text-white text-sm">{{ $activeOrder->customer_name }}</div>
                        <div class="text-xs text-slate-300 font-mono flex items-center gap-1.5">
                            <i class="fi fi-rr-phone-call text-xs text-slate-400"></i>
                            <a href="tel:{{ $activeOrder->customer_phone }}" class="hover:underline text-sky-400">{{ $activeOrder->customer_phone }}</a>
                        </div>
                        <div class="pt-2 border-t border-slate-800/60 flex items-center justify-between text-xs">
                            <span class="text-slate-400">Ödeme Yöntemi:</span>
                            <span class="font-bold text-emerald-400 uppercase">
                                {{ match($activeOrder->payment_method) {
                                    'online' => '💳 Online Kredi Kartı (Ödendi)',
                                    'cash_on_delivery' => '💵 Kapıda Nakit',
                                    'pos_on_delivery' => '💳 Kapıda Kredi Kartı POS',
                                    default => $activeOrder->payment_method
                                } }}
                            </span>
                        </div>
                    </div>

                    <!-- Delivery Address Card -->
                    <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-2">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                            <i class="fi fi-rr-marker text-rose-400"></i>
                            Teslimat Adresi & Notu
                        </h4>
                        <p class="text-xs text-slate-200 leading-relaxed">{{ $activeOrder->delivery_address }}</p>
                        @if($activeOrder->address_note)
                            <div class="p-2 rounded-xl bg-amber-950/30 border border-amber-500/20 text-[11px] text-amber-300 flex items-start gap-1.5">
                                <i class="fi fi-rr-info text-xs shrink-0 mt-0.5"></i>
                                <span><strong>Adres Notu:</strong> {{ $activeOrder->address_note }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- ORDER ITEMS TABLE -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
                    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                            <i class="fi fi-rr-shopping-cart text-emerald-400"></i>
                            Sipariş Kalemleri ({{ is_array($activeOrder->items) ? count($activeOrder->items) : 0 }} Kalem)
                        </h4>
                    </div>

                    <div class="divide-y divide-slate-800/60 text-xs">
                        @if(is_array($activeOrder->items))
                            @foreach($activeOrder->items as $item)
                                <div class="p-3.5 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-7 h-7 rounded-lg bg-slate-800 text-slate-300 font-bold font-mono flex items-center justify-center">
                                            {{ $item['quantity'] ?? 1 }}x
                                        </div>
                                        <div>
                                            <div class="font-bold text-white text-xs sm:text-sm">{{ $item['name'] ?? 'Ürün' }}</div>
                                            @if(!empty($item['note']))
                                                <span class="text-[10px] text-amber-400 font-medium block">📝 {{ $item['note'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-bold text-white text-xs sm:text-sm">₺{{ number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono">₺{{ number_format($item['price'] ?? 0, 2) }} / adet</div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <!-- PRICING TOTAL SUMMARY -->
                    <div class="p-4 bg-slate-950/60 border-t border-slate-800 space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Ara Toplam:</span>
                            <span class="font-mono font-bold text-white">₺{{ number_format($activeOrder->subtotal, 2) }}</span>
                        </div>
                        @if($activeOrder->delivery_fee > 0)
                            <div class="flex justify-between text-slate-400">
                                <span>Getirme / Teslimat Ücreti:</span>
                                <span class="font-mono font-bold text-white">₺{{ number_format($activeOrder->delivery_fee, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-base font-black text-white pt-2 border-t border-slate-800">
                            <span>GENEL TOPLAM:</span>
                            <span class="font-mono text-emerald-400 text-lg">₺{{ number_format($activeOrder->total, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- MAIN WORKFLOW ACTION BAR -->
                <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 flex flex-wrap items-center justify-between gap-3 mt-auto">
                    <div class="flex items-center gap-2">
                        <button onclick="cancelOrderModal({{ $activeOrder->id }})" class="px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 font-extrabold text-xs transition cursor-pointer">
                            Siparişi İptal Et
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($activeOrder->status === 'new')
                            <button onclick="updateOrderStatus({{ $activeOrder->id }}, 'preparing')" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition shadow-lg shadow-emerald-900/30 cursor-pointer flex items-center gap-2">
                                <i class="fi fi-rr-check text-sm"></i>
                                <span>Siparişi Onayla & Mutfağa Gönder</span>
                            </button>
                        @elseif($activeOrder->status === 'preparing')
                            <button onclick="updateOrderStatus({{ $activeOrder->id }}, 'on_the_way')" class="px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-extrabold text-xs transition shadow-lg shadow-sky-900/30 cursor-pointer flex items-center gap-2">
                                <i class="fi fi-rr-motorcycle text-sm"></i>
                                <span>Kuryeye Teslim Et (Yola Çıktı)</span>
                            </button>
                        @elseif($activeOrder->status === 'on_the_way')
                            <button onclick="updateOrderStatus({{ $activeOrder->id }}, 'delivered')" class="px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition shadow-lg shadow-emerald-900/30 cursor-pointer flex items-center gap-2">
                                <i class="fi fi-rr-check-circle text-sm"></i>
                                <span>Teslim Edildilarak Tamamla</span>
                            </button>
                        @else
                            <span class="text-xs text-slate-400 font-bold">Bu sipariş tamamlanmıştır.</span>
                        @endif
                    </div>
                </div>

            @else
                <div class="h-full flex flex-col items-center justify-center p-12 text-center text-slate-500 space-y-3">
                    <i class="fi fi-rr-box-alt text-5xl"></i>
                    <h3 class="text-base font-bold text-slate-300">Detay Görmek İçin Sipariş Seçin</h3>
                </div>
            @endif
        </div>

    </div>
</div>

<!-- 📞 YENİ TELEFON SİPARİŞİ MODALI -->
<div id="phoneOrderModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col space-y-0">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-sky-500/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center">
                    <i class="fi fi-rr-phone-call text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Yeni Telefon Siparişi Oluştur</h3>
                    <p class="text-xs text-slate-400">Müşteri ve teslimat adresi bilgilerini girin</p>
                </div>
            </div>
            <button onclick="closePhoneOrderModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <form id="phoneOrderForm" onsubmit="submitPhoneOrder(event)" class="p-6 space-y-4 text-xs overflow-y-auto max-h-[75vh]">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Müşteri Adı Soyadı</label>
                    <input type="text" id="phoneCustName" required placeholder="Örn: Serkan Yılmaz" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Telefon Numarası</label>
                    <input type="text" id="phoneCustPhone" required placeholder="05XX XXX XX XX" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-300 mb-1">Açık Teslimat Adresi</label>
                <textarea id="phoneCustAddress" required rows="2" placeholder="Mahalle, Cadde, Sokak, Apt No ve Daire..." class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2 text-white focus:border-sky-500 focus:outline-none"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Ödeme Yöntemi</label>
                    <select id="phonePaymentMethod" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none">
                        <option value="cash_on_delivery">💵 Kapıda Nakit</option>
                        <option value="pos_on_delivery">💳 Kapıda Kredi Kartı (POS)</option>
                        <option value="online">🌐 Online Ödeme</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-slate-300 mb-1">Adres Notu / Tarif</label>
                    <input type="text" id="phoneAddressNote" placeholder="Örn: Zile basmayın" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white focus:border-sky-500 focus:outline-none">
                </div>
            </div>

            <!-- PRODUCT SELECTOR FOR PHONE ORDER -->
            <div class="pt-2 border-t border-slate-800">
                <label class="block font-bold text-slate-300 mb-2">Ürün Ekle</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-40 overflow-y-auto p-1 bg-slate-950 rounded-2xl border border-slate-800">
                    @foreach($products as $prod)
                        <button type="button" onclick="addPhoneProduct({{ $prod->id }}, '{{ addslashes($prod->name) }}', {{ $prod->effective_price }})" class="p-2 rounded-xl bg-slate-900 hover:bg-sky-600/30 border border-slate-800 hover:border-sky-500/50 text-left transition cursor-pointer">
                            <div class="font-bold text-white truncate text-[11px]">{{ $prod->name }}</div>
                            <div class="text-[10px] text-emerald-400 font-mono font-bold">₺{{ number_format($prod->effective_price, 2) }}</div>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- SELECTED ITEMS BASKET IN MODAL -->
            <div id="phoneOrderBasketContainer" class="space-y-1 bg-slate-900/60 p-3 rounded-2xl border border-slate-800">
                <div class="text-[11px] font-bold text-slate-400 uppercase mb-1">Seçilen Ürünler:</div>
                <div id="phoneOrderBasketList" class="space-y-1 text-xs">
                    <div class="text-slate-500 text-center py-2">Henüz ürün eklenmedi.</div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closePhoneOrderModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold transition">İptal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-extrabold transition">Siparişi Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- ⚙️ ENTEGRASYON AYARLARI MODALI -->
<div id="integrationModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col space-y-0">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center">
                    <i class="fi fi-rr-settings text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Online Kanal Entegrasyon Ayarları</h3>
                    <p class="text-xs text-slate-400">Trendyol, Yemeksepeti, Getir ve Migros API bilgileri</p>
                </div>
            </div>
            <button onclick="closeIntegrationModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <form id="integrationForm" onsubmit="submitIntegrations(event)" class="p-6 space-y-4 text-xs overflow-y-auto max-h-[75vh]">
            @foreach(['trendyol' => 'Trendyol Go', 'yemeksepeti' => 'Yemeksepeti', 'getir' => 'GetirYemek', 'migros' => 'Migros Yemek'] as $key => $name)
                @php $integ = $integrations[$key] ?? null; @endphp
                <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="font-extrabold text-sm text-white flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full {{ ($integ && $integ->is_active) ? 'bg-emerald-400' : 'bg-slate-600' }}"></span>
                            {{ $name }}
                        </div>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5 cursor-pointer text-[11px] font-bold text-slate-300">
                                <input type="checkbox" name="integ[{{ $key }}][is_active]" value="1" {{ ($integ && $integ->is_active) ? 'checked' : '' }} class="accent-emerald-500 rounded">
                                Kanal Aktif
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-[11px] font-bold text-sky-400">
                                <input type="checkbox" name="integ[{{ $key }}][auto_accept]" value="1" {{ ($integ && $integ->auto_accept) ? 'checked' : '' }} class="accent-sky-500 rounded">
                                Otomatik Onay
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-0.5">Mağaza ID / Kodu</label>
                            <input type="text" name="integ[{{ $key }}][store_id]" value="{{ $integ ? $integ->store_id : '' }}" placeholder="Mağaza ID" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 mb-0.5">API Anahtarı / Token</label>
                            <input type="password" name="integ[{{ $key }}][api_key]" value="{{ $integ ? $integ->api_key : '' }}" placeholder="API Key" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1.5 text-white font-mono">
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeIntegrationModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold transition">İptal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold transition">Ayarları Kaydet</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    let phoneBasket = [];
    let selectedOrderId = null;

    function openPhoneOrderModal() {
        phoneBasket = [];
        renderPhoneBasket();
        document.getElementById('phoneOrderModal').classList.remove('hidden');
    }

    function closePhoneOrderModal() {
        document.getElementById('phoneOrderModal').classList.add('hidden');
    }

    function openIntegrationModal() {
        document.getElementById('integrationModal').classList.remove('hidden');
    }

    function closeIntegrationModal() {
        document.getElementById('integrationModal').classList.add('hidden');
    }

    function addPhoneProduct(id, name, price) {
        const existing = phoneBasket.find(i => i.product_id === id);
        if (existing) {
            existing.quantity += 1;
        } else {
            phoneBasket.push({
                product_id: id,
                name: name,
                price: price,
                quantity: 1,
                note: ''
            });
        }
        renderPhoneBasket();
    }

    function renderPhoneBasket() {
        const list = document.getElementById('phoneOrderBasketList');
        if (phoneBasket.length === 0) {
            list.innerHTML = '<div class="text-slate-500 text-center py-2">Henüz ürün eklenmedi.</div>';
            return;
        }

        list.innerHTML = '';
        phoneBasket.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 rounded-xl bg-slate-950 border border-slate-800';
            div.innerHTML = `
                <div class="font-bold text-white">${item.name} x ${item.quantity}</div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-emerald-400 font-bold">₺${(item.price * item.quantity).toFixed(2)}</span>
                    <button type="button" onclick="phoneBasket.splice(${index}, 1); renderPhoneBasket();" class="text-rose-400 hover:text-white"><i class="fi fi-rr-cross-small"></i></button>
                </div>
            `;
            list.appendChild(div);
        });
    }

    async function submitPhoneOrder(e) {
        e.preventDefault();
        if (phoneBasket.length === 0) {
            showAlert('Lütfen siparişe en az bir ürün ekleyiniz.', 'danger');
            return;
        }

        const payload = {
            customer_name: document.getElementById('phoneCustName').value,
            customer_phone: document.getElementById('phoneCustPhone').value,
            delivery_address: document.getElementById('phoneCustAddress').value,
            address_note: document.getElementById('phoneAddressNote').value,
            payment_method: document.getElementById('phonePaymentMethod').value,
            items: phoneBasket
        };

        try {
            const response = await fetch("{{ route('delivery.phone.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                showAlert('📞 Telefon siparişi oluşturuldu!', 'success');
                closePhoneOrderModal();
                setTimeout(() => window.location.reload(), 500);
            } else {
                showAlert(data.message || 'Sipariş kaydedilemedi.', 'danger');
            }
        } catch (err) {
            showAlert('Sunucu hatası oluştu.', 'danger');
        }
    }

    async function updateOrderStatus(orderId, status) {
        try {
            const response = await fetch(`/delivery/${orderId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ status: status })
            });

            const data = await response.json();
            if (data.success) {
                showAlert('Sipariş durumu güncellendi.', 'success');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showAlert('Güncelleme başarısız.', 'danger');
            }
        } catch (err) {
            showAlert('Bağlantı hatası.', 'danger');
        }
    }

    async function simulateOrder(channel) {
        try {
            playAudioBeep();
            const response = await fetch("{{ route('delivery.simulate') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ channel: channel })
            });

            const data = await response.json();
            if (data.success) {
                showAlert(`🛵 ${data.message}`, 'success');
                setTimeout(() => window.location.reload(), 600);
            }
        } catch (err) {
            showAlert('Simülasyon başlatılamadı.', 'danger');
        }
    }

    function playAudioBeep() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.4);
        } catch (e) {}
    }

    function printDeliveryReceipt(orderId) {
        showAlert('🖨️ Fiş yazıcıya gönderildi.', 'info');
    }
</script>
@endsection
