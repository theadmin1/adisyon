@extends('layouts.app')

@section('title', 'Paket Servis Sipariş Ekranı - Adisyon POS')

@section('styles')
<style>
    .channel-logo-card {
        background: transparent;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .logo-img-sm {
        height: 14px;
        max-width: 65px;
        object-fit: contain;
    }
    .logo-img-table {
        height: 16px;
        max-width: 70px;
        object-fit: contain;
    }
    /* 🎨 KOYU TEMAYA UYGUN ÖZEL SCROLLBAR STİLLERİ */
    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }
    ::-webkit-scrollbar-track {
        background: rgba(15, 18, 29, 0.4);
        border-radius: 9999px;
    }
    ::-webkit-scrollbar-thumb {
        background: rgba(51, 65, 85, 0.5);
        border-radius: 9999px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    ::-webkit-scrollbar-thumb:hover {
        background: rgba(56, 189, 248, 0.7);
    }

    * {
        scrollbar-width: thin;
        scrollbar-color: rgba(51, 65, 85, 0.5) rgba(15, 18, 29, 0.4);
    }
</style>
@endsection

@section('content')
<div class="min-h-screen flex flex-col bg-[#07090e] text-slate-100 font-sans selection:bg-sky-500 selection:text-white">

    <!-- 🔝 TOP NAVIGATION HEADER -->
    <header class="bg-[#0f121d] border-b border-slate-800/90 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 shrink-0 shadow-2xl z-20">
        <!-- LEFT: Home Button -->
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition cursor-pointer" title="Ana Sayfaya Dön (Dashboard)">
                <i class="fi fi-rr-home text-sm"></i>
            </a>
        </div>

        <!-- CENTER: TOP QUICK CONTROLS DROPDOWNS -->
        <div class="hidden xl:flex items-center gap-2.5 text-xs">
            
            <!-- 🏪 RESTORAN & PLATFORM DURUMU ÖZEL DROPDOWN -->
            <div class="relative" id="restaurantDropdownContainer">
                <button type="button" onclick="toggleRestaurantDropdown(event)" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-950/40 border border-emerald-500/30 text-emerald-400 hover:bg-emerald-900/40 transition cursor-pointer font-extrabold text-[11px] shadow-md">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span id="headerRestStatusLabel">Restoran: Açık</span>
                    <i class="fi fi-rr-angle-small-down text-xs ml-0.5"></i>
                </button>

                <!-- DROPDOWN POPUP MENU -->
                <div id="restaurantDropdownMenu" onclick="event.stopPropagation()" class="absolute top-full left-0 mt-2.5 w-80 bg-[#121525] border border-slate-800 rounded-2xl shadow-2xl p-4 space-y-3.5 z-50 hidden animate-fade-in">
                    
                    <!-- Dropdown Header -->
                    <div class="border-b border-slate-800 pb-2.5 flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-black text-white uppercase tracking-wider">Restoran & Platform Durumu</h4>
                            <p class="text-[10px] text-slate-400">Sipariş alımlarını platform bazlı yönetin</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-400 text-[9px] font-mono font-bold">CANLI</span>
                    </div>

                    <!-- Master Toggle: Genel Restoran -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-xs">
                                <i class="fi fi-rr-shop text-xs"></i>
                            </div>
                            <div>
                                <div class="font-extrabold text-white text-xs">Tüm Restoran Alımı</div>
                                <div id="allStatusText" class="text-[10px] text-emerald-400 font-bold">Tüm Kanallar Açık</div>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" id="toggle-input-all" checked onchange="toggleChannel('all', this.checked)" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        </label>
                    </div>

                    <!-- PLATFORMS VERTICAL LIST (ALT ALTA PLATFORMLAR & TOGGLES) -->
                    <div class="space-y-2.5 pt-1">
                        <div class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider">Online Entegrasyon Kanalları:</div>
                        
                        <!-- 1. Trendyol Go -->
                        @php $tyActive = ($integrations['trendyol']->is_active ?? true); @endphp
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition">
                            <div class="flex items-center gap-2.5">
                                <div class="channel-logo-card">
                                    <img src="{{ asset('images/logos/trendyol-go.png') }}" class="logo-img-sm" alt="Trendyol Go">
                                </div>
                                <span id="status-text-trendyol" class="text-[11px] font-bold {{ $tyActive ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ $tyActive ? 'Açık' : 'Kapalı' }}
                                </span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" id="toggle-input-trendyol" {{ $tyActive ? 'checked' : '' }} onchange="toggleChannel('trendyol', this.checked)" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                            </label>
                        </div>

                        <!-- 2. Yemeksepeti -->
                        @php $ysActive = ($integrations['yemeksepeti']->is_active ?? true); @endphp
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition">
                            <div class="flex items-center gap-2.5">
                                <div class="channel-logo-card">
                                    <img src="{{ asset('images/logos/yemeksepeti.png') }}" class="logo-img-sm" alt="Yemeksepeti">
                                </div>
                                <span id="status-text-yemeksepeti" class="text-[11px] font-bold {{ $ysActive ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ $ysActive ? 'Açık' : 'Kapalı' }}
                                </span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" id="toggle-input-yemeksepeti" {{ $ysActive ? 'checked' : '' }} onchange="toggleChannel('yemeksepeti', this.checked)" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500"></div>
                            </label>
                        </div>

                        <!-- 3. GetirYemek -->
                        @php $gtrActive = ($integrations['getir']->is_active ?? true); @endphp
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition">
                            <div class="flex items-center gap-2.5">
                                <div class="channel-logo-card">
                                    <img src="{{ asset('images/logos/getir-yemek.png') }}" class="logo-img-sm" alt="GetirYemek">
                                </div>
                                <span id="status-text-getir" class="text-[11px] font-bold {{ $gtrActive ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ $gtrActive ? 'Açık' : 'Kapalı' }}
                                </span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" id="toggle-input-getir" {{ $gtrActive ? 'checked' : '' }} onchange="toggleChannel('getir', this.checked)" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                            </label>
                        </div>

                        <!-- 4. Migros Yemek -->
                        @php $mgrActive = ($integrations['migros']->is_active ?? true); @endphp
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/80 border border-slate-800 hover:border-slate-700 transition">
                            <div class="flex items-center gap-2.5">
                                <div class="channel-logo-card">
                                    <img src="{{ asset('images/logos/migros-yemek.png') }}" class="logo-img-sm" alt="Migros Yemek">
                                </div>
                                <span id="status-text-migros" class="text-[11px] font-bold {{ $mgrActive ? 'text-emerald-400' : 'text-slate-500' }}">
                                    {{ $mgrActive ? 'Açık' : 'Kapalı' }}
                                </span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" id="toggle-input-migros" {{ $mgrActive ? 'checked' : '' }} onchange="toggleChannel('migros', this.checked)" class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                            </label>
                        </div>

                    </div>

                </div>
            </div>

            <!-- ⚡ OTOMATİK ONAY ÖZEL DROPDOWN -->
            <div class="relative" id="autoAcceptDropdownContainer">
                <button type="button" onclick="toggleAutoAcceptDropdown(event)" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-sky-950/40 border border-sky-500/30 text-sky-300 hover:bg-sky-900/40 transition cursor-pointer font-extrabold text-[11px] shadow-md">
                    <i class="fi fi-rr-bolt text-xs text-sky-400"></i>
                    <span id="headerAutoAcceptLabel">Otomatik Onay: Açık</span>
                    <i class="fi fi-rr-angle-small-down text-xs ml-0.5"></i>
                </button>

                <!-- DROPDOWN POPUP MENU -->
                <div id="autoAcceptDropdownMenu" onclick="event.stopPropagation()" class="absolute top-full left-0 mt-2.5 w-72 bg-[#121525] border border-slate-800 rounded-2xl shadow-2xl p-4 space-y-3 z-50 hidden animate-fade-in">
                    <div class="border-b border-slate-800 pb-2">
                        <h4 class="text-xs font-black text-white uppercase tracking-wider">Otomatik Sipariş Onayı</h4>
                        <p class="text-[10px] text-slate-400">Gelen siparişlerin onay sürecini yönetin</p>
                    </div>

                    <div class="space-y-2">
                        <button type="button" onclick="setAutoAcceptMode(true)" class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-900 border border-sky-500/40 transition text-left cursor-pointer group">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold text-xs">
                                    <i class="fi fi-rr-bolt"></i>
                                </div>
                                <div>
                                    <div class="font-extrabold text-white text-xs">Otomatik Onay (Aktif)</div>
                                    <div class="text-[10px] text-slate-400">Siparişler doğrudan mutfağa düşer</div>
                                </div>
                            </div>
                            <span id="auto-accept-check-true" class="text-sky-400"><i class="fi fi-rr-check text-xs"></i></span>
                        </button>

                        <button type="button" onclick="setAutoAcceptMode(false)" class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition text-left cursor-pointer group">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center font-bold text-xs">
                                    <i class="fi fi-rr-hand"></i>
                                </div>
                                <div>
                                    <div class="font-extrabold text-white text-xs">Manuel Onay (Pasif)</div>
                                    <div class="text-[10px] text-slate-400">Kasiyer onayı beklenir</div>
                                </div>
                            </div>
                            <span id="auto-accept-check-false" class="text-sky-400 hidden"><i class="fi fi-rr-check text-xs"></i></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ⏱️ ORTALAMA TESLİM SÜRESİ ÖZEL DROPDOWN -->
            <div class="relative" id="deliveryTimeDropdownContainer">
                <button type="button" onclick="toggleDeliveryTimeDropdown(event)" class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-amber-950/40 border border-amber-500/30 text-amber-300 hover:bg-amber-900/40 transition cursor-pointer font-extrabold text-[11px] shadow-md">
                    <i class="fi fi-rr-clock text-xs text-amber-400"></i>
                    <span id="headerDeliveryTimeLabel">Ort. Teslim: <strong class="text-white font-mono">30 dk</strong></span>
                    <i class="fi fi-rr-angle-small-down text-xs ml-0.5"></i>
                </button>

                <!-- DROPDOWN POPUP MENU -->
                <div id="deliveryTimeDropdownMenu" onclick="event.stopPropagation()" class="absolute top-full left-0 mt-2.5 w-72 bg-[#121525] border border-slate-800 rounded-2xl shadow-2xl p-4 space-y-3 z-50 hidden animate-fade-in">
                    <div class="border-b border-slate-800 pb-2">
                        <h4 class="text-xs font-black text-white uppercase tracking-wider">Ortalama Teslimat Süresi</h4>
                        <p class="text-[10px] text-slate-400">Müşteriye gösterilen tahmini teslimat süresi</p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" onclick="setDeliveryTime('20 dk')" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/50 hover:bg-amber-950/30 transition text-center cursor-pointer">
                            <div class="font-mono font-black text-amber-400 text-sm">20 dk</div>
                            <div class="text-[9px] text-slate-400">Hızlı</div>
                        </button>
                        <button type="button" onclick="setDeliveryTime('30 dk')" class="p-2.5 rounded-xl bg-slate-900 border border-amber-500/40 bg-amber-950/30 transition text-center cursor-pointer">
                            <div class="font-mono font-black text-amber-400 text-sm">30 dk</div>
                            <div class="text-[9px] text-slate-400">Standart</div>
                        </button>
                        <button type="button" onclick="setDeliveryTime('45 dk')" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/50 hover:bg-amber-950/30 transition text-center cursor-pointer">
                            <div class="font-mono font-black text-amber-400 text-sm">45 dk</div>
                            <div class="text-[9px] text-slate-400">Yoğun</div>
                        </button>
                        <button type="button" onclick="setDeliveryTime('60 dk')" class="p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-amber-500/50 hover:bg-amber-950/30 transition text-center cursor-pointer">
                            <div class="font-mono font-black text-amber-400 text-sm">60 dk</div>
                            <div class="text-[9px] text-slate-400">Aşırı Yoğun</div>
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT: Action Buttons, Simulator & View Mode Toggle -->
        <div class="flex items-center gap-2 ml-auto lg:ml-0">
            
            <!-- 🧪 TEST & SİMÜLATÖR BUTTON -->
            <button onclick="openTestModal()" class="px-3.5 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/40 text-purple-300 text-xs font-extrabold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                <i class="fi fi-rr-flask text-xs"></i>
                <span>Test & Simülatör</span>
            </button>

            <!-- 🕒 GEÇMİŞ SİPARİŞLER -->
            <a href="{{ route('delivery.history') }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                <i class="fi fi-rr-time-past text-xs text-amber-400"></i>
                <span class="hidden sm:inline">Geçmiş Siparişler</span>
            </a>

            <!-- 📊 RAPORLAR & Z-RAPORU -->
            <a href="{{ route('reports.index') }}" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                <i class="fi fi-rr-chart-histogram text-xs text-indigo-400"></i>
                <span class="hidden sm:inline">Raporlar</span>
            </a>

            <!-- 📞 YENİ TELEFON SİPARİŞİ -->
            <button onclick="openPhoneOrderModal()" class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-extrabold shadow-lg shadow-sky-900/30 transition flex items-center gap-1.5 cursor-pointer shrink-0">
                <i class="fi fi-rr-phone-call text-xs"></i>
                <span>Telefon Siparişi</span>
            </button>

            <!-- VIEW MODE TOGGLE BUTTONS (EN SAĞDA - SADECE İKONLAR) -->
            <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800 ml-1">
                <button onclick="switchViewMode('kanban')" id="btnKanbanView" title="Yana Yana (Kolon) Görünümü" class="w-8 h-8 rounded-lg font-extrabold transition bg-sky-600 text-white shadow flex items-center justify-center cursor-pointer">
                    <i class="fi fi-rr-apps text-sm"></i>
                </button>
                <button onclick="switchViewMode('table')" id="btnTableView" title="Liste (Tablo) Görünümü" class="w-8 h-8 rounded-lg font-extrabold transition text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                    <i class="fi fi-rr-list text-sm"></i>
                </button>
            </div>
        </div>
    </header>

    @php
        $newOrders = $orders->where('status', 'new');
        $preparingOrders = $orders->where('status', 'preparing');
        $onTheWayOrders = $orders->where('status', 'on_the_way');
        $completedOrders = $orders->whereIn('status', ['delivered', 'cancelled']);
    @endphp

    <!-- 🖥️ VIEW 1: YANA YANA KANBAN COLUMNS WORKSPACE (DEFAULT) -->
    <main id="kanbanWorkspace" class="flex-1 p-4 sm:p-6 overflow-x-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 min-w-[1000px] h-full items-start">
            
            <!-- COLUMN 1: YENİ SİPARİŞLER (ONAY BEKLEYENLER) -->
            <div class="flex flex-col rounded-3xl bg-[#0e111d] border border-rose-500/30 shadow-2xl overflow-hidden max-h-[85vh]">
                <!-- Column Header -->
                <div class="p-4 bg-rose-950/40 border-b border-rose-500/30 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-3 h-3 rounded-full bg-rose-500 animate-ping"></span>
                        <i class="fi fi-rr-time-five text-rose-400 text-sm"></i>
                        <h3 class="text-xs font-black text-rose-300 uppercase tracking-wider">Yeni Siparişler</h3>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-400 text-xs font-extrabold font-mono border border-rose-500/30">
                        {{ $newOrders->count() }}
                    </span>
                </div>

                <!-- Column Cards Stream -->
                <div class="p-3.5 space-y-3.5 overflow-y-auto flex-1">
                    @forelse($newOrders as $order)
                        @php
                            $logo = match($order->channel) {
                                'trendyol' => 'trendyol-go.png',
                                'yemeksepeti' => 'yemeksepeti.png',
                                'getir' => 'getir-yemek.png',
                                'migros' => 'migros-yemek.png',
                                default => null,
                            };
                        @endphp
                        <div class="order-card-item p-4 rounded-2xl bg-slate-900/90 border border-slate-800 hover:border-rose-500/50 transition-all space-y-3 shadow-lg">
                            <!-- Card Header -->
                            <div class="flex items-center justify-between">
                                @if($logo)
                                    <div class="channel-logo-card">
                                        <img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-sm" alt="{{ $order->channel }}">
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-[10px] font-bold border border-blue-500/30">
                                        <i class="fi fi-rr-phone-call text-[10px]"></i> Telefon
                                    </span>
                                @endif
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-black text-white">#{{ $order->order_number }}</span>
                                    <span class="order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold flex items-center gap-1 border border-slate-700 bg-slate-800 text-slate-300 transition-all" data-created-at="{{ $order->created_at->toIso8601String() }}" data-status="{{ $order->status }}">
                                        <i class="fi fi-rr-clock text-[9px]"></i>
                                        <span class="timer-text">--:--</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Customer & Address -->
                            <div class="space-y-1 text-xs">
                                <div class="font-extrabold text-slate-100 flex items-center justify-between">
                                    <span>{{ $order->customer_name }}</span>
                                    <span class="font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</span>
                                </div>
                                <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">{{ $order->delivery_address }}</p>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="pt-2.5 border-t border-slate-800/80 flex items-center justify-between gap-1.5 text-xs">
                                <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">
                                    Detay
                                </button>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="updateOrderStatus({{ $order->id }}, 'cancelled')" class="px-2.5 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 font-bold text-xs border border-rose-500/30 transition">
                                        İptal
                                    </button>
                                    <button onclick="updateOrderStatus({{ $order->id }}, 'preparing')" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-md transition">
                                        Kabul Et
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-500 text-xs bg-slate-900/40 rounded-2xl border border-slate-800/60 flex items-center justify-center gap-2">
                            <i class="fi fi-rr-info text-rose-400 text-sm"></i>
                            <span>Onay bekleyen sipariş yok.</span>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- COLUMN 2: MUTFAKTA HAZIRLANIYOR -->
            <div class="flex flex-col rounded-3xl bg-[#0e111d] border border-amber-500/30 shadow-2xl overflow-hidden max-h-[85vh]">
                <!-- Column Header -->
                <div class="p-4 bg-amber-950/40 border-b border-amber-500/30 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <i class="fi fi-rr-time-fast text-amber-400 text-sm"></i>
                        <h3 class="text-xs font-black text-amber-300 uppercase tracking-wider">Mutfakta Hazırlanıyor</h3>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-xs font-extrabold font-mono border border-amber-500/30">
                        {{ $preparingOrders->count() }}
                    </span>
                </div>

                <!-- Column Cards Stream -->
                <div class="p-3.5 space-y-3.5 overflow-y-auto flex-1">
                    @forelse($preparingOrders as $order)
                        @php
                            $logo = match($order->channel) {
                                'trendyol' => 'trendyol-go.png',
                                'yemeksepeti' => 'yemeksepeti.png',
                                'getir' => 'getir-yemek.png',
                                'migros' => 'migros-yemek.png',
                                default => null,
                            };
                        @endphp
                        <div class="order-card-item p-4 rounded-2xl bg-slate-900/90 border border-slate-800 hover:border-amber-500/50 transition-all space-y-3 shadow-lg">
                            <!-- Card Header -->
                            <div class="flex items-center justify-between">
                                @if($logo)
                                    <div class="channel-logo-card">
                                        <img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-sm" alt="{{ $order->channel }}">
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-[10px] font-bold border border-blue-500/30">
                                        <i class="fi fi-rr-phone-call text-[10px]"></i> Telefon
                                    </span>
                                @endif
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-black text-white">#{{ $order->order_number }}</span>
                                    <span class="order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold flex items-center gap-1 border border-slate-700 bg-slate-800 text-slate-300 transition-all" data-created-at="{{ $order->created_at->toIso8601String() }}" data-status="{{ $order->status }}">
                                        <i class="fi fi-rr-clock text-[9px]"></i>
                                        <span class="timer-text">--:--</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Customer & Address -->
                            <div class="space-y-1 text-xs">
                                <div class="font-extrabold text-slate-100 flex items-center justify-between">
                                    <span>{{ $order->customer_name }}</span>
                                    <span class="font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</span>
                                </div>
                                <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">{{ $order->delivery_address }}</p>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="pt-2.5 border-t border-slate-800/80 flex items-center justify-between gap-1.5 text-xs">
                                <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">
                                    Detay
                                </button>
                                <div class="flex items-center gap-1.5">
                                    <button onclick="updateOrderStatus({{ $order->id }}, 'cancelled')" class="px-2.5 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 font-bold text-xs border border-rose-500/30 transition">
                                        İptal
                                    </button>
                                    <button onclick="updateOrderStatus({{ $order->id }}, 'on_the_way')" class="px-3.5 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-extrabold text-xs shadow-md transition">
                                        Yola Çıkar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-500 text-xs bg-slate-900/40 rounded-2xl border border-slate-800/60 flex items-center justify-center gap-2">
                            <i class="fi fi-rr-info text-amber-400 text-sm"></i>
                            <span>Mutfakta hazırlanan sipariş yok.</span>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- COLUMN 3: KURYEDE / YOLDA -->
            <div class="flex flex-col rounded-3xl bg-[#0e111d] border border-sky-500/30 shadow-2xl overflow-hidden max-h-[85vh]">
                <!-- Column Header -->
                <div class="p-4 bg-sky-950/40 border-b border-sky-500/30 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <i class="fi fi-rr-motorcycle text-sky-400 text-sm"></i>
                        <h3 class="text-xs font-black text-sky-300 uppercase tracking-wider">Kuryede / Yolda</h3>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-400 text-xs font-extrabold font-mono border border-sky-500/30">
                        {{ $onTheWayOrders->count() }}
                    </span>
                </div>

                <!-- Column Cards Stream -->
                <div class="p-3.5 space-y-3.5 overflow-y-auto flex-1">
                    @forelse($onTheWayOrders as $order)
                        @php
                            $logo = match($order->channel) {
                                'trendyol' => 'trendyol-go.png',
                                'yemeksepeti' => 'yemeksepeti.png',
                                'getir' => 'getir-yemek.png',
                                'migros' => 'migros-yemek.png',
                                default => null,
                            };
                        @endphp
                        <div class="order-card-item p-4 rounded-2xl bg-slate-900/90 border border-slate-800 hover:border-sky-500/50 transition-all space-y-3 shadow-lg">
                            <!-- Card Header -->
                            <div class="flex items-center justify-between">
                                @if($logo)
                                    <div class="channel-logo-card">
                                        <img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-sm" alt="{{ $order->channel }}">
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-[10px] font-bold border border-blue-500/30">
                                        <i class="fi fi-rr-phone-call text-[10px]"></i> Telefon
                                    </span>
                                @endif
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-black text-white">#{{ $order->order_number }}</span>
                                    <span class="order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-extrabold flex items-center gap-1 border border-slate-700 bg-slate-800 text-slate-300 transition-all" data-created-at="{{ $order->created_at->toIso8601String() }}" data-status="{{ $order->status }}">
                                        <i class="fi fi-rr-clock text-[9px]"></i>
                                        <span class="timer-text">--:--</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Customer & Address -->
                            <div class="space-y-1 text-xs">
                                <div class="font-extrabold text-slate-100 flex items-center justify-between">
                                    <span>{{ $order->customer_name }}</span>
                                    <span class="font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</span>
                                </div>
                                <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">{{ $order->delivery_address }}</p>
                            </div>

                            <!-- Card Footer Actions -->
                            <div class="pt-2.5 border-t border-slate-800/80 flex items-center justify-between gap-1.5 text-xs">
                                <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition">
                                    Detay
                                </button>
                                <button onclick="updateOrderStatus({{ $order->id }}, 'delivered')" class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-md transition">
                                    Teslim Et
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-500 text-xs bg-slate-900/40 rounded-2xl border border-slate-800/60 flex items-center justify-center gap-2">
                            <i class="fi fi-rr-info text-sky-400 text-sm"></i>
                            <span>Kuryede sipariş yok.</span>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- COLUMN 4: TESLİM EDİLENLER / GEÇMİŞ -->
            <div class="flex flex-col rounded-3xl bg-[#0e111d] border border-emerald-500/30 shadow-2xl overflow-hidden max-h-[85vh]">
                <!-- Column Header -->
                <div class="p-4 bg-emerald-950/40 border-b border-emerald-500/30 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <i class="fi fi-rr-check-circle text-emerald-400 text-sm"></i>
                        <h3 class="text-xs font-black text-emerald-300 uppercase tracking-wider">Teslim Edilenler</h3>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-xs font-extrabold font-mono border border-emerald-500/30">
                        {{ $completedOrders->count() }}
                    </span>
                </div>

                <!-- Column Cards Stream -->
                <div class="p-3.5 space-y-3.5 overflow-y-auto flex-1 opacity-90">
                    @forelse($completedOrders as $order)
                        @php
                            $logo = match($order->channel) {
                                'trendyol' => 'trendyol-go.png',
                                'yemeksepeti' => 'yemeksepeti.png',
                                'getir' => 'getir-yemek.png',
                                'migros' => 'migros-yemek.png',
                                default => null,
                            };
                        @endphp
                        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                @if($logo)
                                    <div class="channel-logo-card">
                                        <img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-sm" alt="{{ $order->channel }}">
                                    </div>
                                @else
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-[10px] font-bold">Telefon</span>
                                @endif
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-black text-white">#{{ $order->order_number }}</span>
                                    <span class="order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-bold flex items-center gap-1 border border-slate-800 bg-slate-950 text-slate-500" data-created-at="{{ $order->created_at->toIso8601String() }}" data-status="{{ $order->status }}">
                                        <i class="fi fi-rr-clock text-[9px]"></i>
                                        <span class="timer-text">--:--</span>
                                    </span>
                                </div>
                            </div>
                            <div class="font-extrabold text-slate-100 flex items-center justify-between">
                                <span>{{ $order->customer_name }}</span>
                                <span class="font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-800/60 text-[11px]">
                                @if($order->status === 'delivered')
                                    <span class="text-emerald-400 font-bold flex items-center gap-1"><i class="fi fi-rr-check text-xs"></i> Teslim Edildi</span>
                                @else
                                    <span class="text-rose-400 font-bold flex items-center gap-1"><i class="fi fi-rr-cross text-xs"></i> İptal</span>
                                @endif
                                <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="text-slate-400 hover:text-white font-bold transition">Detay Gör</button>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-500 text-xs bg-slate-900/40 rounded-2xl border border-slate-800/60 flex items-center justify-center gap-2">
                            <i class="fi fi-rr-info text-emerald-400 text-sm"></i>
                            <span>Geçmiş sipariş yok.</span>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </main>

    <!-- 🖥️ VIEW 2: CATEGORIZED TABLES WORKSPACE (TOGGLED) -->
    <main id="tableWorkspace" class="hidden flex-1 p-4 sm:p-6 space-y-6 overflow-y-auto">
        <!-- 🔴 CATEGORY 1 TABLE -->
        <section class="bg-[#0e111d] border border-slate-800/90 rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-4 bg-rose-950/30 border-b border-rose-500/20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-3 h-3 rounded-full bg-rose-500 animate-ping"></span>
                    <h2 class="text-sm sm:text-base font-black text-rose-300 uppercase tracking-wider">Yeni Siparişler (Onay Bekleyen)</h2>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-rose-500/20 text-rose-400 text-xs font-extrabold font-mono border border-rose-500/30">{{ $newOrders->count() }} Sipariş</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900/90 text-slate-400 font-extrabold uppercase text-[11px] border-b border-slate-800">
                        <tr>
                            <th class="p-3.5 pl-5">Platform</th>
                            <th class="p-3.5">Sipariş No</th>
                            <th class="p-3.5">Müşteri</th>
                            <th class="p-3.5">Adres</th>
                            <th class="p-3.5">Tutar</th>
                            <th class="p-3.5">Ödeme</th>
                            <th class="p-3.5 text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($newOrders as $order)
                            @php
                                $logo = match($order->channel) {
                                    'trendyol' => 'trendyol-go.png',
                                    'yemeksepeti' => 'yemeksepeti.png',
                                    'getir' => 'getir-yemek.png',
                                    'migros' => 'migros-yemek.png',
                                    default => null,
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/60 transition">
                                <td class="p-3.5 pl-5">
                                    @if($logo)
                                        <div class="channel-logo-card"><img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-table" alt="{{ $order->channel }}"></div>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 font-bold border border-blue-500/30"><i class="fi fi-rr-phone-call text-[10px]"></i> Telefon</span>
                                    @endif
                                </td>
                                <td class="p-3.5 font-mono font-extrabold text-white">#{{ $order->order_number }}</td>
                                <td class="p-3.5 font-bold text-slate-100">{{ $order->customer_name }}</td>
                                <td class="p-3.5 text-slate-300 max-w-xs truncate">{{ $order->delivery_address }}</td>
                                <td class="p-3.5 font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</td>
                                <td class="p-3.5 font-bold text-slate-300 uppercase">{{ $order->payment_method }}</td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick="updateOrderStatus({{ $order->id }}, 'cancelled')" class="px-3 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600/40 text-rose-300 font-bold text-xs border border-rose-500/30 transition">İptal Et</button>
                                        <button onclick="updateOrderStatus({{ $order->id }}, 'preparing')" class="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-md transition">Kabul Et</button>
                                        <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 font-bold text-xs transition">Detay</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-6 text-center text-slate-500">🔴 Onay bekleyen sipariş bulunmamaktadır.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 🟡 CATEGORY 2 TABLE -->
        <section class="bg-[#0e111d] border border-slate-800/90 rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-4 bg-amber-950/30 border-b border-amber-500/20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fi fi-rr-time-fast text-amber-400 text-lg"></i>
                    <h2 class="text-sm sm:text-base font-black text-amber-300 uppercase tracking-wider">Yola Çıkarılması Gereken Siparişler (Mutfakta Hazırlanıyor)</h2>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-xs font-extrabold font-mono border border-amber-500/30">{{ $preparingOrders->count() }} Sipariş</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900/90 text-slate-400 font-extrabold uppercase text-[11px] border-b border-slate-800">
                        <tr>
                            <th class="p-3.5 pl-5">Platform</th>
                            <th class="p-3.5">Sipariş No</th>
                            <th class="p-3.5">Müşteri</th>
                            <th class="p-3.5">Adres</th>
                            <th class="p-3.5">Tutar</th>
                            <th class="p-3.5 text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($preparingOrders as $order)
                            @php
                                $logo = match($order->channel) {
                                    'trendyol' => 'trendyol-go.png',
                                    'yemeksepeti' => 'yemeksepeti.png',
                                    'getir' => 'getir-yemek.png',
                                    'migros' => 'migros-yemek.png',
                                    default => null,
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/60 transition">
                                <td class="p-3.5 pl-5">
                                    @if($logo)
                                        <div class="channel-logo-card"><img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-table" alt="{{ $order->channel }}"></div>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 font-bold border border-blue-500/30"><i class="fi fi-rr-phone-call text-[10px]"></i> Telefon</span>
                                    @endif
                                </td>
                                <td class="p-3.5 font-mono font-extrabold text-white">#{{ $order->order_number }}</td>
                                <td class="p-3.5 font-bold text-slate-100">{{ $order->customer_name }}</td>
                                <td class="p-3.5 text-slate-300 max-w-xs truncate">{{ $order->delivery_address }}</td>
                                <td class="p-3.5 font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick="updateOrderStatus({{ $order->id }}, 'cancelled')" class="px-3 py-1.5 rounded-xl bg-rose-600/20 text-rose-300 font-bold text-xs transition">İptal</button>
                                        <button onclick="updateOrderStatus({{ $order->id }}, 'on_the_way')" class="px-4 py-1.5 rounded-xl bg-sky-600 text-white font-extrabold text-xs shadow-md transition">Yola Çıkar</button>
                                        <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 font-bold text-xs transition">Detay</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-6 text-center text-slate-500">🟡 Mutfakta hazırlanan sipariş bulunmamaktadır.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 🔵 CATEGORY 3 TABLE -->
        <section class="bg-[#0e111d] border border-slate-800/90 rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-4 bg-sky-950/30 border-b border-sky-500/20 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <i class="fi fi-rr-motorcycle text-sky-400 text-lg"></i>
                    <h2 class="text-sm sm:text-base font-black text-sky-300 uppercase tracking-wider">Teslim Edilmesi Gereken Siparişler (Kuryede / Yolda)</h2>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-400 text-xs font-extrabold font-mono border border-sky-500/30">{{ $onTheWayOrders->count() }} Sipariş</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900/90 text-slate-400 font-extrabold uppercase text-[11px] border-b border-slate-800">
                        <tr>
                            <th class="p-3.5 pl-5">Platform</th>
                            <th class="p-3.5">Sipariş No</th>
                            <th class="p-3.5">Müşteri</th>
                            <th class="p-3.5">Adres</th>
                            <th class="p-3.5">Tutar</th>
                            <th class="p-3.5 text-center">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($onTheWayOrders as $order)
                            @php
                                $logo = match($order->channel) {
                                    'trendyol' => 'trendyol-go.png',
                                    'yemeksepeti' => 'yemeksepeti.png',
                                    'getir' => 'getir-yemek.png',
                                    'migros' => 'migros-yemek.png',
                                    default => null,
                                };
                            @endphp
                            <tr class="hover:bg-slate-900/60 transition">
                                <td class="p-3.5 pl-5">
                                    @if($logo)
                                        <div class="channel-logo-card"><img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-table" alt="{{ $order->channel }}"></div>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 font-bold border border-blue-500/30"><i class="fi fi-rr-phone-call text-[10px]"></i> Telefon</span>
                                    @endif
                                </td>
                                <td class="p-3.5 font-mono font-extrabold text-white">#{{ $order->order_number }}</td>
                                <td class="p-3.5 font-bold text-slate-100">{{ $order->customer_name }}</td>
                                <td class="p-3.5 text-slate-300 max-w-xs truncate">{{ $order->delivery_address }}</td>
                                <td class="p-3.5 font-mono font-black text-emerald-400 text-sm">₺{{ number_format($order->total, 2) }}</td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick="updateOrderStatus({{ $order->id }}, 'delivered')" class="px-4 py-1.5 rounded-xl bg-emerald-600 text-white font-extrabold text-xs shadow-md transition">Teslim Et</button>
                                        <button onclick="openOrderDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 font-bold text-xs transition">Detay</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-6 text-center text-slate-500">🔵 Kuryede teslim edilmeyi bekleyen sipariş bulunmamaktadır.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- 📋 SİPARİŞ DETAY MODALI (DETAY BUTONUNA BASINCA AÇILIR) -->
<div id="orderDetailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col space-y-0">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <div class="flex items-center gap-3">
                <div id="modalLogoContainer" class="w-10 h-10 rounded-2xl bg-white flex items-center justify-center p-1 shadow-md">
                    <!-- LOGO HERE -->
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <span>Sipariş Detayı</span>
                        <span id="modalOrderNumber" class="font-mono text-sky-400">#---</span>
                    </h3>
                    <p id="modalCustomerName" class="text-xs text-slate-400">---</p>
                </div>
            </div>
            <button onclick="closeOrderDetailModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[75vh]">
            
            <!-- CUSTOMER & ADDRESS -->
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400 font-bold">Telefon:</span>
                    <a id="modalCustomerPhone" href="#" class="font-mono font-bold text-sky-400 hover:underline">---</a>
                </div>
                <div class="pt-2 border-t border-slate-800">
                    <span class="text-slate-400 font-bold block mb-1">Teslimat Adresi:</span>
                    <p id="modalDeliveryAddress" class="text-slate-200 leading-relaxed">---</p>
                </div>
                <div id="modalAddressNoteContainer" class="hidden p-2 rounded-xl bg-amber-950/30 border border-amber-500/20 text-amber-300">
                    <strong>Adres Notu:</strong> <span id="modalAddressNote">---</span>
                </div>
            </div>

            <!-- ORDER ITEMS LIST -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
                <div class="p-3 border-b border-slate-800 font-extrabold text-slate-300 uppercase">Sipariş Kalemleri</div>
                <div id="modalItemList" class="divide-y divide-slate-800 text-xs">
                    <!-- ITEMS DYNAMICALLY INSERTED -->
                </div>
                <div class="p-4 bg-slate-950 border-t border-slate-800 flex justify-between items-center text-sm font-black">
                    <span>GENEL TOPLAM:</span>
                    <span id="modalTotalAmount" class="font-mono text-emerald-400 text-base">₺0.00</span>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeOrderDetailModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold transition">Kapat</button>
            </div>
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
            <div>
                <label class="block font-bold text-slate-300 mb-2">Canlı Test Siparişi Düşür (1-Tık Webhook Simülasyonu):</label>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="simulateOrder('trendyol')" class="p-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/trendyol-go.png') }}" class="logo-img-sm" alt="Trendyol Go">
                        <i class="fi fi-rr-play text-orange-400 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                    <button onclick="simulateOrder('yemeksepeti')" class="p-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/yemeksepeti.png') }}" class="logo-img-sm" alt="Yemeksepeti">
                        <i class="fi fi-rr-play text-pink-400 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                    <button onclick="simulateOrder('getir')" class="p-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/getir-yemek.png') }}" class="logo-img-sm" alt="GetirYemek">
                        <i class="fi fi-rr-play text-purple-400 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                    <button onclick="simulateOrder('migros')" class="p-4 rounded-2xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 transition group cursor-pointer shadow-md flex items-center justify-between">
                        <img src="{{ asset('images/logos/migros-yemek.png') }}" class="logo-img-sm" alt="Migros Yemek">
                        <i class="fi fi-rr-play text-amber-400 group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>
            </div>

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

    function toggleRestaurantDropdown(e) {
        if (e) e.stopPropagation();
        document.getElementById('autoAcceptDropdownMenu')?.classList.add('hidden');
        document.getElementById('deliveryTimeDropdownMenu')?.classList.add('hidden');
        document.getElementById('restaurantDropdownMenu')?.classList.toggle('hidden');
    }

    function toggleAutoAcceptDropdown(e) {
        if (e) e.stopPropagation();
        document.getElementById('restaurantDropdownMenu')?.classList.add('hidden');
        document.getElementById('deliveryTimeDropdownMenu')?.classList.add('hidden');
        document.getElementById('autoAcceptDropdownMenu')?.classList.toggle('hidden');
    }

    function toggleDeliveryTimeDropdown(e) {
        if (e) e.stopPropagation();
        document.getElementById('restaurantDropdownMenu')?.classList.add('hidden');
        document.getElementById('autoAcceptDropdownMenu')?.classList.add('hidden');
        document.getElementById('deliveryTimeDropdownMenu')?.classList.toggle('hidden');
    }

    function setAutoAcceptMode(active) {
        document.getElementById('headerAutoAcceptLabel').innerText = active ? 'Otomatik Onay: Açık' : 'Otomatik Onay: Kapalı';
        document.getElementById('auto-accept-check-true')?.classList.toggle('hidden', !active);
        document.getElementById('auto-accept-check-false')?.classList.toggle('hidden', active);
        document.getElementById('autoAcceptDropdownMenu')?.classList.add('hidden');
        showAlert(active ? '⚡ Otomatik onay modu aktif.' : '🛑 Manuel onay modu aktif.', 'info');
    }

    function setDeliveryTime(timeStr) {
        document.getElementById('headerDeliveryTimeLabel').innerHTML = `Ort. Teslim: <strong class="text-white font-mono">${timeStr}</strong>`;
        document.getElementById('deliveryTimeDropdownMenu')?.classList.add('hidden');
        showAlert(`⏱️ Ortalama teslimat süresi ${timeStr} olarak ayarlandı.`, 'info');
    }

    document.addEventListener('click', (e) => {
        ['restaurantDropdown', 'autoAcceptDropdown', 'deliveryTimeDropdown'].forEach(prefix => {
            const container = document.getElementById(`${prefix}Container`);
            const menu = document.getElementById(`${prefix}Menu`);
            if (container && menu && !container.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    });

    async function toggleChannel(channel, isActive) {
        try {
            const response = await fetch("{{ route('delivery.toggle_channel') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ channel: channel, is_active: isActive })
            });

            const data = await response.json();
            if (data.success) {
                showAlert(data.message, 'success');
                
                if (channel === 'all') {
                    ['trendyol', 'yemeksepeti', 'getir', 'migros'].forEach(ch => {
                        const input = document.getElementById(`toggle-input-${ch}`);
                        const text = document.getElementById(`status-text-${ch}`);
                        if (input) input.checked = isActive;
                        if (text) {
                            text.innerText = isActive ? 'Açık' : 'Kapalı';
                            text.className = `text-[11px] font-bold ${isActive ? 'text-emerald-400' : 'text-slate-500'}`;
                        }
                    });
                    const allText = document.getElementById('allStatusText');
                    if (allText) {
                        allText.innerText = isActive ? 'Tüm Kanallar Açık' : 'Tüm Kanallar Kapalı';
                        allText.className = `text-[10px] font-bold ${isActive ? 'text-emerald-400' : 'text-rose-400'}`;
                    }
                } else {
                    const text = document.getElementById(`status-text-${channel}`);
                    if (text) {
                        text.innerText = isActive ? 'Açık' : 'Kapalı';
                        text.className = `text-[11px] font-bold ${isActive ? 'text-emerald-400' : 'text-slate-500'}`;
                    }
                }

                // Header Restoran label update
                const anyActive = ['trendyol', 'yemeksepeti', 'getir', 'migros'].some(ch => {
                    const input = document.getElementById(`toggle-input-${ch}`);
                    return input ? input.checked : false;
                });
                const headerLabel = document.getElementById('headerRestStatusLabel');
                if (headerLabel) {
                    headerLabel.innerText = anyActive ? 'Restoran: Açık' : 'Restoran: Kapalı';
                }
            } else {
                showAlert('Kanal durumu güncellenemedi.', 'danger');
            }
        } catch (err) {
            showAlert('Sunucu hatası oluştu.', 'danger');
        }
    }

    function switchViewMode(mode) {
        const kanban = document.getElementById('kanbanWorkspace');
        const table = document.getElementById('tableWorkspace');
        const btnKanban = document.getElementById('btnKanbanView');
        const btnTable = document.getElementById('btnTableView');

        if (mode === 'kanban') {
            kanban.classList.remove('hidden');
            table.classList.add('hidden');
            btnKanban.className = 'px-3 py-1 rounded-lg font-extrabold text-xs transition bg-sky-600 text-white shadow';
            btnTable.className = 'px-3 py-1 rounded-lg font-extrabold text-xs transition text-slate-400 hover:text-white';
        } else {
            kanban.classList.add('hidden');
            table.classList.remove('hidden');
            btnTable.className = 'px-3 py-1 rounded-lg font-extrabold text-xs transition bg-sky-600 text-white shadow';
            btnKanban.className = 'px-3 py-1 rounded-lg font-extrabold text-xs transition text-slate-400 hover:text-white';
        }
    }

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

    function openOrderDetailModal(order) {
        document.getElementById('modalOrderNumber').innerText = '#' + order.order_number;
        document.getElementById('modalCustomerName').innerText = order.customer_name;
        document.getElementById('modalCustomerPhone').innerText = order.customer_phone;
        document.getElementById('modalCustomerPhone').href = 'tel:' + order.customer_phone;
        document.getElementById('modalDeliveryAddress').innerText = order.delivery_address;
        
        if (order.address_note) {
            document.getElementById('modalAddressNoteContainer').classList.remove('hidden');
            document.getElementById('modalAddressNote').innerText = order.address_note;
        } else {
            document.getElementById('modalAddressNoteContainer').classList.add('hidden');
        }

        const logoContainer = document.getElementById('modalLogoContainer');
        const logos = {
            'trendyol': 'trendyol-go.png',
            'yemeksepeti': 'yemeksepeti.png',
            'getir': 'getir-yemek.png',
            'migros': 'migros-yemek.png'
        };

        if (logos[order.channel]) {
            logoContainer.innerHTML = `<img src="/images/logos/${logos[order.channel]}" class="h-6 object-contain" alt="${order.channel}">`;
        } else {
            logoContainer.innerHTML = `<i class="fi fi-rr-phone-call text-xl text-blue-600"></i>`;
        }

        const itemList = document.getElementById('modalItemList');
        itemList.innerHTML = '';
        if (Array.isArray(order.items)) {
            order.items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'p-3 flex items-center justify-between';
                div.innerHTML = `
                    <div>
                        <div class="font-bold text-white">${item.name || 'Ürün'} x ${item.quantity || 1}</div>
                        ${item.note ? `<div class="text-[10px] text-amber-400">📝 ${item.note}</div>` : ''}
                    </div>
                    <div class="font-mono font-bold text-white">₺${((item.price || 0) * (item.quantity || 1)).toFixed(2)}</div>
                `;
                itemList.appendChild(div);
            });
        }

        document.getElementById('modalTotalAmount').innerText = '₺' + (parseFloat(order.total) || 0).toFixed(2);
        document.getElementById('orderDetailModal').classList.remove('hidden');
    }

    function closeOrderDetailModal() {
        document.getElementById('orderDetailModal').classList.add('hidden');
    }

    function toggleAutoAccept() {
        showAlert('⚡ Otomatik Onay (Auto-Accept) durumu güncellendi.', 'info');
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

    /* ⏱️ CANLI SİPARİŞ YAŞLANDIRMA SAYAÇLARI & GECİKME UYARISI */
    function updateOrderTimers() {
        const badges = document.querySelectorAll('.order-age-badge');
        const now = new Date();

        badges.forEach(badge => {
            const createdAtStr = badge.getAttribute('data-created-at');
            const status = badge.getAttribute('data-status');
            const textEl = badge.querySelector('.timer-text');
            const cardEl = badge.closest('.order-card-item');

            if (!createdAtStr || !textEl) return;

            if (status === 'delivered' || status === 'cancelled') {
                badge.className = 'order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-bold flex items-center gap-1 border bg-slate-950 text-slate-500 border-slate-800';
                textEl.innerText = status === 'delivered' ? 'Teslim Edildi' : 'İptal';
                return;
            }

            const createdAt = new Date(createdAtStr);
            const diffMs = now - createdAt;
            const diffSec = Math.max(0, Math.floor(diffMs / 1000));
            const mins = Math.floor(diffSec / 60);
            const secs = diffSec % 60;
            const formattedSecs = secs < 10 ? '0' + secs : secs;

            if (mins < 10) {
                badge.className = 'order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-bold flex items-center gap-1 border bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
                textEl.innerText = `${mins}dk ${formattedSecs}s`;
                if (cardEl) {
                    cardEl.classList.remove('border-rose-500/80', 'animate-pulse', 'shadow-[0_0_15px_rgba(244,63,94,0.35)]');
                }
            } else if (mins < 20) {
                badge.className = 'order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-bold flex items-center gap-1 border bg-amber-500/20 text-amber-400 border-amber-500/30';
                textEl.innerText = `⚠️ ${mins}dk ${formattedSecs}s`;
                if (cardEl) {
                    cardEl.classList.remove('border-rose-500/80', 'animate-pulse', 'shadow-[0_0_15px_rgba(244,63,94,0.35)]');
                }
            } else {
                badge.className = 'order-age-badge px-2 py-0.5 rounded-full text-[10px] font-mono font-black flex items-center gap-1 border bg-rose-500/30 text-rose-300 border-rose-500/60 animate-pulse shadow-md shadow-rose-950/50';
                textEl.innerText = `🚨 ${mins}dk GECİKTİ!`;
                if (cardEl) {
                    cardEl.classList.add('border-rose-500/80', 'animate-pulse', 'shadow-[0_0_15px_rgba(244,63,94,0.35)]');
                }
            }
        });
    }

    setInterval(updateOrderTimers, 1000);
    document.addEventListener('DOMContentLoaded', updateOrderTimers);
</script>
@endsection
