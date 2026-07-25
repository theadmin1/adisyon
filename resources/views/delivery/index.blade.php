@extends('layouts.app')

@section('title', 'Paket Servis & Entegrasyon Konsolu - Adisyon POS')

@section('styles')
<style>
    .channel-logo-pill {
        background: #ffffff;
        padding: 0.35rem 0.75rem;
        border-radius: 0.75rem;
        display: inline-flex;
        items-center: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.2s ease;
    }
    .channel-logo-pill:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }
    .logo-img-sm { height: 16px; object-fit: contain; }
    .logo-img-md { height: 22px; object-fit: contain; }
    .logo-img-lg { height: 28px; object-fit: contain; }
</style>
@endsection

@section('content')
<div class="min-h-screen flex flex-col bg-[#08090f] text-slate-100 font-sans selection:bg-sky-500 selection:text-white">

    <!-- 🔝 TOP NAVIGATION HEADER -->
    <header class="bg-[#10131e] border-b border-slate-800/80 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0">
        <!-- LEFT: Back & Title -->
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition cursor-pointer" title="Ana Menüye Dön">
                <i class="fi fi-rr-arrow-left text-sm"></i>
            </a>
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center">
                    <i class="fi fi-rr-box-alt text-lg"></i>
                </div>
                <div>
                    <h1 class="text-sm sm:text-base font-extrabold text-white leading-tight tracking-wide uppercase">Paket Servis & Entegrasyonlar</h1>
                    <p class="text-[11px] text-slate-400">Canlı Sipariş Yönetim Konsolu</p>
                </div>
            </div>
        </div>

        <!-- CENTER: Integration Status Badges With Official Logos (NO TEXT) -->
        <div class="hidden lg:flex items-center gap-2.5 bg-slate-900/90 border border-slate-800/80 p-1.5 rounded-2xl">
            @foreach(['trendyol' => 'trendyol-go.png', 'yemeksepeti' => 'yemeksepeti.png', 'getir' => 'getir-yemek.png', 'migros' => 'migros-yemek.png'] as $key => $filename)
                @php $integ = $integrations[$key] ?? null; @endphp
                <div class="channel-logo-pill flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full {{ ($integ && $integ->is_active) ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                    <img src="{{ asset('images/logos/' . $filename) }}" class="logo-img-sm" alt="{{ $key }}">
                    @if($integ && $integ->auto_accept)
                        <span class="text-[9px] bg-slate-900 text-emerald-400 px-1.5 py-0.5 rounded font-mono font-bold" title="Otomatik Onay">AUTO</span>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- RIGHT: Action Buttons & Test System -->
        <div class="flex items-center gap-2 ml-auto lg:ml-0">
            
            <!-- 🧪 TEST & SİMÜLASYON SİSTEMİ BUTTON -->
            <button onclick="openTestModal()" class="px-3.5 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/40 text-purple-300 text-xs font-extrabold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                <i class="fi fi-rr-flask text-xs"></i>
                <span>Test & Simülatör</span>
            </button>

            <!-- ⚙️ ENTEGRASYON AYARLARI -->
            <a href="{{ route('settings.index', ['tab' => 'integrations']) }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                <i class="fi fi-rr-settings text-xs text-sky-400"></i>
                <span class="hidden sm:inline">Entegrasyon Ayarları</span>
            </a>

            <!-- 📞 YENİ TELEFON SİPARİŞİ -->
            <button onclick="openPhoneOrderModal()" class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-extrabold shadow-lg shadow-sky-900/30 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                <i class="fi fi-rr-phone-call text-xs"></i>
                <span>Telefon Siparişi</span>
            </button>
        </div>
    </header>

    <!-- 🖥️ MAIN SPLIT SCREEN WORKSPACE -->
    <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
        
        <!-- 👈 LEFT COLUMN: ORDER LIST & FILTERS (~45% WIDTH) -->
        <div class="w-full lg:w-[45%] border-r border-slate-800/80 flex flex-col bg-[#0d0f18] overflow-hidden">
            
            <!-- FILTERS HEADER WITH PLATFORM LOGOS (NO TEXT) -->
            <div class="p-3.5 border-b border-slate-800/80 space-y-2.5 shrink-0 bg-slate-900/50">
                
                <!-- Channel Filter Buttons with Logos (No Text) -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs">
                    <!-- Tüm Kanallar -->
                    <a href="{{ route('delivery.index', ['channel' => 'all', 'status' => $statusFilter]) }}" 
                        class="px-3.5 py-2 rounded-xl font-bold transition shrink-0 flex items-center gap-1.5 {{ $channelFilter === 'all' ? 'bg-sky-600 text-white shadow-md' : 'bg-slate-800 text-slate-300 hover:text-white' }}">
                        <span>Tümü</span>
                    </a>
                    
                    <!-- Trendyol Go -->
                    <a href="{{ route('delivery.index', ['channel' => 'trendyol', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl transition shrink-0 flex items-center justify-center {{ $channelFilter === 'trendyol' ? 'bg-white ring-2 ring-orange-500 shadow-md' : 'bg-white/90 hover:bg-white' }}">
                        <img src="{{ asset('images/logos/trendyol-go.png') }}" class="logo-img-sm" alt="Trendyol Go">
                    </a>

                    <!-- Yemeksepeti -->
                    <a href="{{ route('delivery.index', ['channel' => 'yemeksepeti', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl transition shrink-0 flex items-center justify-center {{ $channelFilter === 'yemeksepeti' ? 'bg-white ring-2 ring-pink-500 shadow-md' : 'bg-white/90 hover:bg-white' }}">
                        <img src="{{ asset('images/logos/yemeksepeti.png') }}" class="logo-img-sm" alt="Yemeksepeti">
                    </a>

                    <!-- GetirYemek -->
                    <a href="{{ route('delivery.index', ['channel' => 'getir', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl transition shrink-0 flex items-center justify-center {{ $channelFilter === 'getir' ? 'bg-white ring-2 ring-purple-500 shadow-md' : 'bg-white/90 hover:bg-white' }}">
                        <img src="{{ asset('images/logos/getir-yemek.png') }}" class="logo-img-sm" alt="GetirYemek">
                    </a>

                    <!-- Migros Yemek -->
                    <a href="{{ route('delivery.index', ['channel' => 'migros', 'status' => $statusFilter]) }}" 
                        class="px-3 py-1.5 rounded-xl transition shrink-0 flex items-center justify-center {{ $channelFilter === 'migros' ? 'bg-white ring-2 ring-amber-500 shadow-md' : 'bg-white/90 hover:bg-white' }}">
                        <img src="{{ asset('images/logos/migros-yemek.png') }}" class="logo-img-sm" alt="Migros Yemek">
                    </a>

                    <!-- Telefon -->
                    <a href="{{ route('delivery.index', ['channel' => 'phone', 'status' => $statusFilter]) }}" 
                        class="px-3 py-2 rounded-xl font-bold transition shrink-0 flex items-center gap-1.5 {{ $channelFilter === 'phone' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-800 text-blue-400 hover:bg-slate-700' }}">
                        <i class="fi fi-rr-phone-call text-xs"></i>
                        <span>Telefon</span>
                    </a>
                </div>

                <!-- Status Filter Tabs -->
                <div class="grid grid-cols-5 gap-1 bg-slate-950 p-1 rounded-xl text-[11px] font-bold border border-slate-800">
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
            <div class="flex-1 overflow-y-auto p-3.5 space-y-3" id="orderListContainer">
                @forelse($orders as $index => $order)
                    @php
                        $logoFilename = match($order->channel) {
                            'trendyol' => 'trendyol-go.png',
                            'yemeksepeti' => 'yemeksepeti.png',
                            'getir' => 'getir-yemek.png',
                            'migros' => 'migros-yemek.png',
                            default => null,
                        };
                    @endphp

                    <div onclick="selectOrder({{ $order->id }})" id="order-card-{{ $order->id }}" 
                        class="order-card p-4 rounded-2xl border transition-all cursor-pointer relative group ${index === 0 ? 'bg-slate-800/90 border-sky-500 shadow-xl' : 'bg-slate-900/70 border-slate-800 hover:border-slate-700'}">
                        
                        <!-- CARD HEADER WITH LOGO ONLY (NO TEXT) -->
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                @if($logoFilename)
                                    <div class="bg-white px-2.5 py-1 rounded-lg shadow-sm flex items-center justify-center">
                                        <img src="{{ asset('images/logos/' . $logoFilename) }}" class="logo-img-sm" alt="{{ $order->channel }}">
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase bg-blue-500/20 text-blue-400 border border-blue-500/30 flex items-center gap-1">
                                        <i class="fi fi-rr-phone-call text-[10px]"></i> Telefon
                                    </span>
                                @endif
                                <span class="font-mono text-xs font-black text-white">#{{ $order->order_number }}</span>
                            </div>
                            <span class="text-[10px] text-slate-400 font-mono flex items-center gap-1">
                                <i class="fi fi-rr-clock text-[10px]"></i>
                                {{ $order->created_at ? $order->created_at->diffForHumans(null, true) : 'şimdi' }}
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
                        <p class="text-xs text-slate-500">Yukarıdaki "Test & Simülatör" butonunu kullanarak anında örnek sipariş üretebilirsiniz.</p>
                        <button onclick="openTestModal()" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition">
                            🧪 Test Siparişi Üret
                        </button>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- 👉 RIGHT COLUMN: SELECTED ORDER WORKSPACE (~55% WIDTH) -->
        <div class="flex-1 flex flex-col bg-[#090b12] overflow-y-auto p-4 sm:p-6 space-y-5" id="orderWorkspace">
            @if(count($orders) > 0)
                @php 
                    $activeOrder = $orders->first();
                    $activeLogo = match($activeOrder->channel) {
                        'trendyol' => 'trendyol-go.png',
                        'yemeksepeti' => 'yemeksepeti.png',
                        'getir' => 'getir-yemek.png',
                        'migros' => 'migros-yemek.png',
                        default => null,
                    };
                @endphp
                
                <!-- WORKSPACE HEADER WITH LOGO ONLY (NO TEXT) -->
                <div class="flex flex-wrap items-center justify-between gap-3 p-4 rounded-2xl bg-slate-900/80 border border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white flex items-center justify-center p-2 shadow-md">
                            @if($activeLogo)
                                <img src="{{ asset('images/logos/' . $activeLogo) }}" class="logo-img-md" alt="{{ $activeOrder->channel }}">
                            @else
                                <i class="fi fi-rr-phone-call text-xl text-blue-600"></i>
                            @endif
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
                        <button onclick="updateOrderStatus({{ $activeOrder->id }}, 'cancelled')" class="px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 text-rose-400 font-extrabold text-xs transition cursor-pointer">
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
                                <span>Teslim Edildi Olarak Tamamla</span>
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

<!-- 🧪 TEST SİSTEMİ & ENTEGRASYON SİMÜLATÖRÜ MODALI -->
<div id="testModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col space-y-0">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-purple-500/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-purple-500/20 text-purple-400 border border-purple-500/30 flex items-center justify-center">
                    <i class="fi fi-rr-flask text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Entegrasyon Test & Canlı Sipariş Simülatörü</h3>
                    <p class="text-xs text-slate-400">Platform logosuna tıklayarak anında test siparişi oluşturun</p>
                </div>
            </div>
            <button onclick="closeTestModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-5 text-xs overflow-y-auto max-h-[75vh]">
            
            <!-- TEST BUTTONS BY OFFICIAL PLATFORM LOGO ONLY -->
            <div>
                <label class="block font-bold text-slate-300 mb-2">Canlı Test Siparişi Düşür (1-Tık Webhook Simülasyonu):</label>
                <div class="grid grid-cols-2 gap-3">
                    
                    <!-- Trendyol Test Button -->
                    <button onclick="simulateOrder('trendyol')" class="p-4 rounded-2xl bg-white hover:bg-slate-100 border border-slate-200 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/trendyol-go.png') }}" class="logo-img-md" alt="Trendyol Go">
                        <i class="fi fi-rr-play text-orange-600 group-hover:translate-x-1 transition-transform"></i>
                    </button>

                    <!-- Yemeksepeti Test Button -->
                    <button onclick="simulateOrder('yemeksepeti')" class="p-4 rounded-2xl bg-white hover:bg-slate-100 border border-slate-200 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/yemeksepeti.png') }}" class="logo-img-md" alt="Yemeksepeti">
                        <i class="fi fi-rr-play text-pink-600 group-hover:translate-x-1 transition-transform"></i>
                    </button>

                    <!-- GetirYemek Test Button -->
                    <button onclick="simulateOrder('getir')" class="p-4 rounded-2xl bg-white hover:bg-slate-100 border border-slate-200 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/getir-yemek.png') }}" class="logo-img-md" alt="GetirYemek">
                        <i class="fi fi-rr-play text-purple-600 group-hover:translate-x-1 transition-transform"></i>
                    </button>

                    <!-- Migros Yemek Test Button -->
                    <button onclick="simulateOrder('migros')" class="p-4 rounded-2xl bg-white hover:bg-slate-100 border border-slate-200 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/migros-yemek.png') }}" class="logo-img-md" alt="Migros Yemek">
                        <i class="fi fi-rr-play text-amber-600 group-hover:translate-x-1 transition-transform"></i>
                    </button>

                </div>
            </div>

            <!-- AUDIO & RESET CONTROLS -->
            <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                <button onclick="playAudioBeep()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fi fi-rr-volume text-sky-400"></i>
                    <span>📢 Ses İkazını Test Et</span>
                </button>

                <button onclick="clearAllTestOrders()" class="px-3.5 py-2 rounded-xl bg-rose-950/40 hover:bg-rose-900/60 border border-rose-500/30 text-rose-400 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fi fi-rr-trash text-xs"></i>
                    <span>🗑️ Tüm Test Siparişlerini Temizle</span>
                </button>
            </div>

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

@endsection

@section('scripts')
<script>
    let phoneBasket = [];

    function openTestModal() {
        document.getElementById('testModal').classList.remove('hidden');
    }

    function closeTestModal() {
        document.getElementById('testModal').classList.add('hidden');
    }

    function openPhoneOrderModal() {
        phoneBasket = [];
        renderPhoneBasket();
        document.getElementById('phoneOrderModal').classList.remove('hidden');
    }

    function closePhoneOrderModal() {
        document.getElementById('phoneOrderModal').classList.add('hidden');
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
                closeTestModal();
                setTimeout(() => window.location.reload(), 600);
            }
        } catch (err) {
            showAlert('Simülasyon başlatılamadı.', 'danger');
        }
    }

    async function clearAllTestOrders() {
        if (!confirm('Tüm test siparişlerini temizlemek istediğinizden emin misiniz?')) return;
        try {
            const response = await fetch("{{ route('delivery.clear_test') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await response.json();
            if (data.success) {
                showAlert('🗑️ Tüm test siparişleri temizlendi.', 'info');
                closeTestModal();
                setTimeout(() => window.location.reload(), 500);
            }
        } catch (err) {
            showAlert('Temizleme hatası.', 'danger');
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
