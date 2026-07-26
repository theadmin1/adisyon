@extends('layouts.app')

@section('title', 'Geçmiş Paket Siparişleri & Arşiv - Adisyon POS')

@section('styles')
<style>
    .channel-logo-card {
        background: transparent;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .logo-img-table { height: 16px; max-width: 70px; object-fit: contain; }

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
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition cursor-pointer" title="Ana Sayfaya Dön (Dashboard)">
                <i class="fi fi-rr-home text-sm"></i>
            </a>
            <div>
                <h1 class="text-sm sm:text-base font-black text-white leading-tight tracking-wide uppercase flex items-center gap-2">
                    <i class="fi fi-rr-time-past text-sky-400"></i>
                    <span>Geçmiş Paket Siparişleri & Arşiv</span>
                </h1>
                <p class="text-[11px] text-slate-400">Tamamlanan ve geçmiş tarihli online & telefon siparişleri</p>
            </div>
        </div>

        <!-- RIGHT ACTION BUTTONS -->
        <div class="flex flex-wrap items-center justify-end gap-2 ml-auto lg:ml-0">
            <button type="button" onclick="toggleTheme()" title="Beyaz / Karanlık Mod"
                class="w-9 h-9 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700/50 text-slate-300 hover:text-white transition-all flex items-center justify-center shrink-0">
                <i class="fi fi-rr-moon text-indigo-400 text-sm theme-toggle-icon"></i>
                <span class="sr-only theme-toggle-text">Karanlık Mod</span>
            </button>
            <!-- 📊 GENEL RAPORLAR LINK -->
            <a href="{{ route('reports.index') }}" class="px-3.5 py-2 rounded-xl bg-indigo-600/20 hover:bg-indigo-600/30 border border-indigo-500/40 text-indigo-300 text-xs font-extrabold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                <i class="fi fi-rr-chart-histogram text-xs"></i>
                <span>Tüm Raporlar & Z-Raporu</span>
            </a>

            <!-- 🛵 CANLI POS KONSOLU -->
            <a href="{{ route('delivery.index') }}" class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-extrabold shadow-lg shadow-sky-900/30 transition flex items-center gap-1.5 cursor-pointer">
                <i class="fi fi-rr-motorcycle text-xs"></i>
                <span>Canlı POS Konsolu</span>
            </a>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 space-y-6 overflow-y-auto">
        
        <!-- 📊 KPI SUMMARY CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Orders -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Toplam Sipariş</div>
                    <div class="text-2xl font-black text-white font-mono">{{ number_format($stats['total_count']) }}</div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-sky-500/20 text-sky-400 border border-sky-500/30 flex items-center justify-center text-lg">
                    <i class="fi fi-rr-box"></i>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Teslim Ciro</div>
                    <div class="text-2xl font-black text-emerald-400 font-mono">₺{{ number_format($stats['total_revenue'], 2) }}</div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-lg">
                    <i class="fi fi-rr-sack-dollar"></i>
                </div>
            </div>

            <!-- Delivered Count -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Başarılı Teslimat</div>
                    <div class="text-2xl font-black text-emerald-400 font-mono">{{ number_format($stats['delivered_count']) }}</div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-lg">
                    <i class="fi fi-rr-check-circle"></i>
                </div>
            </div>

            <!-- Cancelled Count -->
            <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">İptal Edilenler</div>
                    <div class="text-2xl font-black text-rose-400 font-mono">{{ number_format($stats['cancelled_count']) }}</div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-rose-500/20 text-rose-400 border border-rose-500/30 flex items-center justify-center text-lg">
                    <i class="fi fi-rr-cross-circle"></i>
                </div>
            </div>
        </div>

        <!-- 🔍 FILTERING & SEARCH FORM BAR -->
        <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3 shadow-2xl">
            <form action="{{ route('delivery.history') }}" method="GET" class="flex flex-wrap items-center justify-between gap-3 text-xs">
                
                <!-- Period Filter Quick Buttons -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    @php
                        $periods = [
                            'today' => 'Bugün',
                            'yesterday' => 'Dün',
                            'this_week' => 'Bu Hafta',
                            'this_month' => 'Bu Ay',
                        ];
                    @endphp
                    @foreach($periods as $key => $label)
                        <a href="{{ route('delivery.history', array_merge(request()->query(), ['period' => $key])) }}" 
                           class="px-3 py-1.5 rounded-xl font-bold transition border {{ $period === $key ? 'bg-sky-600 text-white border-sky-500 shadow-md' : 'bg-slate-800 text-slate-400 hover:text-white border-slate-700' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                <!-- Dropdown Selectors & Search Input -->
                <div class="flex flex-wrap items-center gap-2.5 flex-1 justify-end">
                    
                    <!-- Channel Filter -->
                    <select name="channel" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-slate-300 font-bold text-xs focus:outline-none focus:border-sky-500">
                        <option value="all" {{ $channelFilter === 'all' ? 'selected' : '' }}>Tüm Kanallar</option>
                        <option value="trendyol" {{ $channelFilter === 'trendyol' ? 'selected' : '' }}>Trendyol Go</option>
                        <option value="yemeksepeti" {{ $channelFilter === 'yemeksepeti' ? 'selected' : '' }}>Yemeksepeti</option>
                        <option value="getir" {{ $channelFilter === 'getir' ? 'selected' : '' }}>GetirYemek</option>
                        <option value="migros" {{ $channelFilter === 'migros' ? 'selected' : '' }}>Migros Yemek</option>
                        <option value="phone" {{ $channelFilter === 'phone' ? 'selected' : '' }}>Telefon Siparişi</option>
                    </select>

                    <!-- Status Filter -->
                    <select name="status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-slate-300 font-bold text-xs focus:outline-none focus:border-sky-500">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Tüm Durumlar</option>
                        <option value="delivered" {{ $statusFilter === 'delivered' ? 'selected' : '' }}>Teslim Edildi</option>
                        <option value="cancelled" {{ $statusFilter === 'cancelled' ? 'selected' : '' }}>İptal Edildi</option>
                        <option value="preparing" {{ $statusFilter === 'preparing' ? 'selected' : '' }}>Hazırlanıyor</option>
                        <option value="on_the_way" {{ $statusFilter === 'on_the_way' ? 'selected' : '' }}>Yolda</option>
                        <option value="new" {{ $statusFilter === 'new' ? 'selected' : '' }}>Yeni Onay Bekleyen</option>
                    </select>

                    <!-- Live Search Box -->
                    <div class="relative w-full sm:w-64">
                        <input type="text" name="search" value="{{ $searchQuery }}" placeholder="Sipariş No, Müşteri, Adres..." class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-4 py-1.5 text-white text-xs placeholder:text-slate-500 focus:outline-none focus:border-sky-500 transition">
                        <i class="fi fi-rr-search absolute left-3 top-2 text-slate-500 text-xs"></i>
                    </div>

                    <button type="submit" class="px-4 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-extrabold transition">Filtrele</button>
                </div>
            </form>
        </div>

        <!-- 📋 ORDERS ARCHIVE TABLE -->
        <section class="bg-[#0e111d] border border-slate-800/90 rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-4 bg-slate-900/60 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fi fi-rr-list text-sky-400"></i>
                    <h2 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider">Sipariş Kayıtları Listesi</h2>
                </div>
                <span class="text-xs text-slate-400 font-mono font-bold">{{ $orders->total() }} Kayıt Bulundu</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950/80 text-slate-400 font-extrabold uppercase text-[11px] border-b border-slate-800">
                        <tr>
                            <th class="p-3.5 pl-5">Platform</th>
                            <th class="p-3.5">Sipariş No</th>
                            <th class="p-3.5">Tarih / Saat</th>
                            <th class="p-3.5">Müşteri & Telefon</th>
                            <th class="p-3.5">Teslimat Adresi</th>
                            <th class="p-3.5">Tutar</th>
                            <th class="p-3.5">Durum</th>
                            <th class="p-3.5 text-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($orders as $order)
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
                                        <div class="channel-logo-card">
                                            <img src="{{ asset('images/logos/' . $logo) }}" class="logo-img-table" alt="{{ $order->channel }}">
                                        </div>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-[10px] font-bold border border-blue-500/30">
                                            <i class="fi fi-rr-phone-call text-[10px]"></i> Telefon
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 font-mono font-black text-white">#{{ $order->order_number }}</td>
                                <td class="p-3.5 text-slate-300 font-mono">
                                    {{ $order->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="p-3.5">
                                    <div class="font-extrabold text-white">{{ $order->customer_name }}</div>
                                    <div class="font-mono text-[11px] text-sky-400">{{ $order->customer_phone }}</div>
                                </td>
                                <td class="p-3.5 text-slate-300 max-w-xs truncate" title="{{ $order->delivery_address }}">
                                    {{ $order->delivery_address }}
                                </td>
                                <td class="p-3.5 font-mono font-black text-emerald-400">₺{{ number_format($order->total, 2) }}</td>
                                <td class="p-3.5">
                                    @if($order->status === 'delivered')
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 font-bold text-[10px] flex items-center gap-1 w-fit">
                                            <i class="fi fi-rr-check text-[10px]"></i> Teslim Edildi
                                        </span>
                                    @elseif($order->status === 'cancelled')
                                        <span class="px-2.5 py-1 rounded-full bg-rose-500/20 text-rose-400 border border-rose-500/30 font-bold text-[10px] flex items-center gap-1 w-fit">
                                            <i class="fi fi-rr-cross text-[10px]"></i> İptal
                                        </span>
                                    @elseif($order->status === 'on_the_way')
                                        <span class="px-2.5 py-1 rounded-full bg-sky-500/20 text-sky-400 border border-sky-500/30 font-bold text-[10px] flex items-center gap-1 w-fit">
                                            <i class="fi fi-rr-motorcycle text-[10px]"></i> Yolda
                                        </span>
                                    @elseif($order->status === 'preparing')
                                        <span class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30 font-bold text-[10px] flex items-center gap-1 w-fit">
                                            <i class="fi fi-rr-time-fast text-[10px]"></i> Hazırlanıyor
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full bg-rose-500/20 text-rose-400 border border-rose-500/30 font-bold text-[10px] flex items-center gap-1 w-fit">
                                            <i class="fi fi-rr-time-five text-[10px]"></i> Yeni Onay Bekliyor
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-center">
                                    <button onclick="openHistoryDetailModal({{ json_encode($order) }})" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs transition border border-slate-700">
                                        Detay
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-slate-500 font-bold">
                                    Seçilen filtrelere uygun geçmiş sipariş kaydı bulunamadı.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="p-4 bg-slate-950 border-t border-slate-800">
                {{ $orders->links() }}
            </div>
        </section>

    </main>
</div>

<!-- 📦 ORDER DETAIL MODAL -->
<div id="historyDetailModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col space-y-0 animate-fade-in">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
            <div class="flex items-center gap-3">
                <div id="modalHistoryLogo" class="flex items-center"></div>
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <span>Sipariş Detayı</span>
                        <span id="modalHistoryNumber" class="font-mono text-sky-400">#---</span>
                    </h3>
                    <p id="modalHistoryCustomer" class="text-xs text-slate-400">---</p>
                </div>
            </div>
            <button onclick="closeHistoryDetailModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 text-xs overflow-y-auto max-h-[75vh]">
            <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400 font-bold">Telefon:</span>
                    <a id="modalHistoryPhone" href="#" class="font-mono font-bold text-sky-400 hover:underline">---</a>
                </div>
                <div class="pt-2 border-t border-slate-800">
                    <span class="text-slate-400 font-bold block mb-1">Teslimat Adresi:</span>
                    <p id="modalHistoryAddress" class="text-slate-200 leading-relaxed">---</p>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
                <div class="p-3 border-b border-slate-800 font-extrabold text-slate-300 uppercase">Sipariş Kalemleri</div>
                <div id="modalHistoryItemList" class="divide-y divide-slate-800 text-xs"></div>
                <div class="p-4 bg-slate-950 border-t border-slate-800 flex justify-between items-center text-sm font-black">
                    <span>GENEL TOPLAM:</span>
                    <span id="modalHistoryTotal" class="font-mono text-emerald-400 text-base">₺0.00</span>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="closeHistoryDetailModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 font-bold transition">Kapat</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openHistoryDetailModal(order) {
        document.getElementById('modalHistoryNumber').innerText = '#' + order.order_number;
        document.getElementById('modalHistoryCustomer').innerText = order.customer_name;
        document.getElementById('modalHistoryPhone').innerText = order.customer_phone;
        document.getElementById('modalHistoryPhone').href = 'tel:' + order.customer_phone;
        document.getElementById('modalHistoryAddress').innerText = order.delivery_address;

        const logoContainer = document.getElementById('modalHistoryLogo');
        const logos = {
            'trendyol': 'trendyol-go.png',
            'yemeksepeti': 'yemeksepeti.png',
            'getir': 'getir-yemek.png',
            'migros': 'migros-yemek.png'
        };

        if (logos[order.channel]) {
            logoContainer.innerHTML = `<img src="/images/logos/${logos[order.channel]}" class="logo-img-table" alt="${order.channel}">`;
        } else {
            logoContainer.innerHTML = `<i class="fi fi-rr-phone-call text-lg text-sky-400"></i>`;
        }

        const itemList = document.getElementById('modalHistoryItemList');
        itemList.innerHTML = '';
        if (Array.isArray(order.items)) {
            order.items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'p-3 flex items-center justify-between';
                div.innerHTML = `
                    <div>
                        <div class="font-bold text-white">${item.name || 'Ürün'} x ${item.quantity || 1}</div>
                        ${item.note ? `<div class="text-[10px] text-amber-400">Not: ${item.note}</div>` : ''}
                    </div>
                    <div class="font-mono font-bold text-white">₺${((item.price || 0) * (item.quantity || 1)).toFixed(2)}</div>
                `;
                itemList.appendChild(div);
            });
        }

        document.getElementById('modalHistoryTotal').innerText = '₺' + (parseFloat(order.total) || 0).toFixed(2);
        document.getElementById('historyDetailModal').classList.remove('hidden');
    }

    function closeHistoryDetailModal() {
        document.getElementById('historyDetailModal').classList.add('hidden');
    }
</script>
@endsection
