@extends('layouts.app')

@section('title', 'Raporlar & Gün Sonu — Adisyon POS')

@section('styles')
<style>
    .report-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
    .report-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .report-scrollbar::-webkit-scrollbar-thumb { background: rgba(99,102,241,0.25); border-radius: 99px; }
    .report-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(99,102,241,0.45); }

    .kpi-card { position: relative; overflow: hidden; }
    .kpi-card::before {
        content: '';
        position: absolute;
        width: 120px; height: 120px;
        border-radius: 50%;
        filter: blur(40px);
        opacity: 0.15;
        pointer-events: none;
        transition: opacity 0.4s ease;
    }
    .kpi-card:hover::before { opacity: 0.28; }
    .kpi-emerald::before { background: #10b981; right: -30px; bottom: -30px; }
    .kpi-indigo::before { background: #6366f1; right: -30px; bottom: -30px; }
    .kpi-amber::before { background: #f59e0b; right: -30px; bottom: -30px; }
    .kpi-rose::before { background: #f43f5e; right: -30px; bottom: -30px; }

    .bar-chart-col {
        transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
    }
    .bar-chart-col:hover {
        filter: brightness(1.25);
        transform: scaleY(1.04);
        transform-origin: bottom;
    }

    .table-row-hover {
        transition: background 0.2s ease;
    }
    .table-row-hover:hover {
        background: rgba(99,102,241,0.04);
    }

    /* Termal Yazıcı Baskı Kuralları */
    @media print {
        body * { visibility: hidden !important; }
        #zReportPrintArea, #zReportPrintArea * { visibility: visible !important; }
        #zReportPrintArea {
            position: absolute !important; left: 0 !important; top: 0 !important;
            width: 80mm !important; max-width: 80mm !important;
            margin: 0 !important; padding: 8px !important;
            background: white !important; color: black !important;
            font-family: 'Courier New', Courier, monospace !important;
            font-size: 11px !important; line-height: 1.3 !important;
            border: none !important; box-shadow: none !important;
        }
        .no-print { display: none !important; }
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInUp 0.45s cubic-bezier(0.16,1,0.3,1) forwards; }
    .animate-delay-1 { animation-delay: 0.06s; }
    .animate-delay-2 { animation-delay: 0.12s; }
    .animate-delay-3 { animation-delay: 0.18s; }
    .animate-delay-4 { animation-delay: 0.24s; }
</style>
@endsection

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans antialiased selection:bg-indigo-500 selection:text-white">

    {{-- ══════════════════════════════════════════════════════
         HEADER — Dashboard ile uyumlu üst navigasyon çubuğu
    ══════════════════════════════════════════════════════ --}}
    <header class="bg-transparent px-4 lg:px-8 py-3 flex items-center justify-between no-print">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-10 h-10 rounded-2xl bg-slate-900/60 hover:bg-slate-800 border border-slate-800/60 text-slate-300 hover:text-white transition-all group">
                <i class="fi fi-rr-arrow-left text-base group-hover:-translate-x-0.5 transition-transform"></i>
            </a>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold tracking-tight text-white flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-fuchsia-500/10 border border-fuchsia-500/20 text-fuchsia-400">
                        <i class="fi fi-rr-chart-pie-alt text-sm"></i>
                    </span>
                    Raporlar & Gün Sonu
                </h1>
                <p class="text-[11px] text-slate-500 hidden sm:block mt-0.5">Ciro, Satış, Ödeme, Personel ve İptal Analizleri</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <button onclick="toggleTheme()" class="px-3 py-1.5 rounded-xl bg-slate-900/80 hover:bg-slate-800 border border-slate-800 text-xs font-bold transition flex items-center gap-2 cursor-pointer">
                <i class="fi fi-rr-moon text-indigo-400 text-sm theme-toggle-icon"></i>
                <span class="theme-toggle-text text-slate-300 hidden sm:inline">Mod</span>
            </button>

            <button onclick="openZReportModal()" class="px-4 py-2 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 text-emerald-400 hover:text-emerald-300 text-xs font-bold transition flex items-center gap-2 cursor-pointer">
                <i class="fi fi-rr-receipt text-sm"></i>
                <span class="hidden sm:inline">Z-Raporu</span>
            </button>
        </div>
    </header>

    {{-- ══════════════════════════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════════════════════════ --}}
    <main class="flex-1 px-4 sm:px-6 lg:px-8 py-4 sm:py-6 space-y-6 max-w-[1400px] w-full mx-auto">

        {{-- ── 1. DÖNEM FİLTRESİ ── --}}
        <div class="flex flex-col md:flex-row items-center justify-between gap-3 no-print animate-fade-in">
            {{-- Quick Presets --}}
            <div class="flex items-center gap-1.5 overflow-x-auto max-w-full pb-1 md:pb-0 report-scrollbar">
                @php
                    $periods = [
                        'today'      => ['label' => 'Bugün',    'icon' => 'fi-rr-calendar'],
                        'yesterday'  => ['label' => 'Dün',      'icon' => 'fi-rr-time-past'],
                        'this_week'  => ['label' => 'Bu Hafta', 'icon' => 'fi-rr-calendar-lines'],
                        'this_month' => ['label' => 'Bu Ay',    'icon' => 'fi-rr-chart-histogram'],
                    ];
                @endphp
                @foreach($periods as $key => $info)
                    <a href="{{ route('reports.index', ['period' => $key]) }}"
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition border whitespace-nowrap flex items-center gap-1.5
                              {{ $period === $key
                                  ? 'bg-indigo-600 border-indigo-500 text-white shadow-lg shadow-indigo-600/25'
                                  : 'bg-slate-900/60 border-slate-800/60 text-slate-400 hover:text-white hover:border-slate-700' }}">
                        <i class="fi {{ $info['icon'] }} text-xs"></i>
                        {{ $info['label'] }}
                    </a>
                @endforeach
            </div>

            {{-- Custom Date Range --}}
            <form method="GET" action="{{ route('reports.index') }}" class="flex items-center gap-1.5 w-full md:w-auto">
                <input type="hidden" name="period" value="custom">
                <div class="flex items-center gap-1.5 bg-slate-900/60 border border-slate-800/60 p-1 rounded-xl text-xs w-full md:w-auto">
                    <input type="date" name="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}"
                           class="bg-slate-800/60 border border-slate-700/40 text-white px-2 py-1.5 rounded-lg outline-none focus:border-indigo-500 text-xs flex-1 md:flex-none md:w-32">
                    <span class="text-slate-600 font-bold">→</span>
                    <input type="date" name="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}"
                           class="bg-slate-800/60 border border-slate-700/40 text-white px-2 py-1.5 rounded-lg outline-none focus:border-indigo-500 text-xs flex-1 md:flex-none md:w-32">
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold transition cursor-pointer text-xs">
                        <i class="fi fi-rr-filter text-[10px]"></i>
                    </button>
                </div>
            </form>
        </div>

        {{-- Aktif Dönem Rozeti --}}
        <div class="flex items-center justify-between text-[11px] text-slate-500 no-print">
            <span>Dönem: <strong class="text-indigo-400 font-mono">{{ $startDate->format('d.m.Y') }} — {{ $endDate->format('d.m.Y') }}</strong></span>
            <span class="font-mono">{{ now()->format('H:i') }}</span>
        </div>

        {{-- ── 2. KPI STAT CARDS ── --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

            {{-- NET CİRO --}}
            <div class="kpi-card kpi-emerald group flex flex-col rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:border-emerald-500/30 hover:shadow-emerald-500/5 animate-fade-in">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Net Ciro</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white transition-all">
                        <i class="fi fi-rr-dollar text-sm"></i>
                    </div>
                </div>
                <span class="text-xl sm:text-2xl font-black text-emerald-400 font-mono tracking-tight leading-none">
                    ₺{{ number_format($stats['total_revenue'], 2) }}
                </span>
                <div class="mt-3 pt-2.5 border-t border-slate-800/60 flex justify-between text-[10px] text-slate-500">
                    <span>{{ $stats['total_checks_count'] }} adisyon</span>
                    <span>Ort: ₺{{ number_format($stats['avg_check_amount'], 0) }}</span>
                </div>
            </div>

            {{-- NAKİT TAHSİLAT --}}
            <div class="kpi-card kpi-indigo group flex flex-col rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:border-indigo-500/30 hover:shadow-indigo-500/5 animate-fade-in animate-delay-1">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Toplam Tahsilat</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 group-hover:bg-indigo-500 group-hover:text-white transition-all">
                        <i class="fi fi-rr-wallet text-sm"></i>
                    </div>
                </div>
                <span class="text-xl sm:text-2xl font-black text-indigo-400 font-mono tracking-tight leading-none">
                    ₺{{ number_format($paymentBreakdown['total'], 2) }}
                </span>
                <div class="mt-3 pt-2.5 border-t border-slate-800/60 grid grid-cols-3 text-[10px] text-slate-500 gap-1">
                    <div class="text-center">
                        <div class="text-emerald-400 font-bold font-mono">₺{{ number_format($paymentBreakdown['nakit'], 0) }}</div>
                        <div class="text-[9px]">Nakit</div>
                    </div>
                    <div class="text-center">
                        <div class="text-indigo-300 font-bold font-mono">₺{{ number_format($paymentBreakdown['kredi_karti'], 0) }}</div>
                        <div class="text-[9px]">Kart</div>
                    </div>
                    <div class="text-center">
                        <div class="text-amber-400 font-bold font-mono">₺{{ number_format($paymentBreakdown['yemek_karti'], 0) }}</div>
                        <div class="text-[9px]">Yemek</div>
                    </div>
                </div>
            </div>

            {{-- İSKONTO & İKRAM --}}
            <div class="kpi-card kpi-amber group flex flex-col rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:border-amber-500/30 hover:shadow-amber-500/5 animate-fade-in animate-delay-2">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">İndirimler</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 group-hover:bg-amber-500 group-hover:text-white transition-all">
                        <i class="fi fi-rr-badge-percent text-sm"></i>
                    </div>
                </div>
                <span class="text-xl sm:text-2xl font-black text-amber-400 font-mono tracking-tight leading-none">
                    ₺{{ number_format($stats['total_discounts'], 2) }}
                </span>
                <div class="mt-3 pt-2.5 border-t border-slate-800/60 flex justify-between text-[10px] text-slate-500">
                    <span>İkram: ₺{{ number_format($stats['complimentary_total_amount'], 0) }}</span>
                    <span>{{ $stats['complimentary_count'] }} adet</span>
                </div>
            </div>

            {{-- İPTAL KAYBI --}}
            <div class="kpi-card kpi-rose group flex flex-col rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-0.5 hover:border-rose-500/30 hover:shadow-rose-500/5 animate-fade-in animate-delay-3">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">İptal / Zayi</span>
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 group-hover:bg-rose-500 group-hover:text-white transition-all">
                        <i class="fi fi-rr-cross-circle text-sm"></i>
                    </div>
                </div>
                <span class="text-xl sm:text-2xl font-black text-rose-400 font-mono tracking-tight leading-none">
                    ₺{{ number_format($stats['cancelled_loss_amount'], 2) }}
                </span>
                <div class="mt-3 pt-2.5 border-t border-slate-800/60 flex justify-between text-[10px] text-slate-500">
                    <span>{{ $stats['cancelled_items_count'] }} iptal kalem</span>
                    <span class="text-rose-400/60">Kayıp</span>
                </div>
            </div>
        </div>

        {{-- ── 3. SAATLİK SATIŞ YOĞUNLUĞU (24 SAAT BAR CHART) ── --}}
        <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg animate-fade-in animate-delay-4">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-fuchsia-500/10 border border-fuchsia-500/20 text-fuchsia-400">
                        <i class="fi fi-rr-time-twenty-four text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Saatlik Satış Yoğunluğu</h3>
                        <p class="text-[10px] text-slate-500">Günün 24 saati ciro dağılımı</p>
                    </div>
                </div>
            </div>

            @php $maxHourlyAmount = collect($hourlyData)->max('amount') ?: 1; @endphp

            <div class="grid grid-cols-24 gap-[3px] items-end h-32 sm:h-40 pt-4 px-1 bg-slate-950/40 rounded-xl border border-slate-800/40 overflow-hidden">
                @foreach($hourlyData as $item)
                    @php
                        $heightPercent = ($item['amount'] / $maxHourlyAmount) * 100;
                        $hasSales = $item['amount'] > 0;
                    @endphp
                    <div class="flex flex-col items-center gap-0.5 group relative h-full justify-end">
                        {{-- Tooltip --}}
                        <div class="absolute -top-14 hidden group-hover:flex flex-col items-center bg-slate-900 border border-slate-700 px-2 py-1.5 rounded-lg text-[10px] font-mono z-30 shadow-xl whitespace-nowrap pointer-events-none">
                            <span class="font-bold text-fuchsia-300">{{ $item['hour'] }}</span>
                            <span class="text-emerald-400 font-extrabold">₺{{ number_format($item['amount'], 2) }}</span>
                            <span class="text-slate-400">{{ $item['count'] }} adisyon</span>
                        </div>

                        <div class="w-full rounded-t bar-chart-col {{ $hasSales ? 'bg-gradient-to-t from-indigo-600 to-fuchsia-500' : 'bg-slate-800/30' }}"
                             style="height: {{ max(3, $heightPercent) }}%; min-height: 3px"></div>

                        <span class="text-[7px] sm:text-[8px] font-mono text-slate-600 leading-none">{{ substr($item['hour'], 0, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ── 4. ÜRÜN PERFORMANSI & KATEGORİ DAĞILIMI (GRID) ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-5">

            {{-- Ürün Bazlı Satış Performansı --}}
            <div class="lg:col-span-2 rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl shadow-lg overflow-hidden flex flex-col">
                <div class="p-4 border-b border-slate-800/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                            <i class="fi fi-rr-box-alt text-sm"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Ürün Satış Performansı</h3>
                            <p class="text-[10px] text-slate-500">Satılan adet, ciro ve iptal detayları</p>
                        </div>
                    </div>
                    <input type="text" id="productReportSearch" onkeyup="filterReportProducts()" placeholder="Ürün ara..."
                           class="px-3 py-1.5 bg-slate-950/50 border border-slate-800/60 text-xs text-white placeholder-slate-600 rounded-xl outline-none focus:border-indigo-500 w-full sm:w-44 transition">
                </div>

                <div class="overflow-y-auto flex-1 max-h-80 report-scrollbar">
                    <table id="productReportTable" class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/30 text-slate-500 uppercase tracking-wider text-[10px] font-bold border-b border-slate-800/40 sticky top-0 z-10">
                            <tr>
                                <th class="py-2.5 px-4">Ürün Adı</th>
                                <th class="py-2.5 px-4 text-center">Adet</th>
                                <th class="py-2.5 px-4 text-center">İptal</th>
                                <th class="py-2.5 px-4 text-right">Ciro</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/30 font-medium">
                            @forelse($productStats as $stat)
                                <tr class="table-row-hover product-report-row">
                                    <td class="py-2.5 px-4 font-bold text-white text-xs">{{ $stat->product_name }}</td>
                                    <td class="py-2.5 px-4 text-center text-emerald-400 font-bold font-mono">{{ number_format($stat->sold_qty, 0) }}</td>
                                    <td class="py-2.5 px-4 text-center {{ $stat->cancelled_qty > 0 ? 'text-rose-400 font-bold' : 'text-slate-700' }} font-mono">{{ number_format($stat->cancelled_qty, 0) }}</td>
                                    <td class="py-2.5 px-4 text-right font-mono font-bold text-cyan-400">₺{{ number_format($stat->total_revenue, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-600 text-xs">Satış kaydı bulunmuyor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Kategori Ciro Dağılımı --}}
            <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg flex flex-col">
                <div class="flex items-center gap-2.5 mb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        <i class="fi fi-rr-apps text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-white">Kategori Dağılımı</h3>
                        <p class="text-[10px] text-slate-500">Cirodaki pay oranları</p>
                    </div>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto max-h-72 report-scrollbar pr-1">
                    @forelse($categoryStatsMap as $cat)
                        @php $catPercent = $stats['total_revenue'] > 0 ? ($cat['total_revenue'] / $stats['total_revenue']) * 100 : 0; @endphp
                        <div class="p-3 rounded-xl bg-slate-950/30 border border-slate-800/40 space-y-2 group hover:border-indigo-500/20 transition">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-white">{{ $cat['category_name'] }}</span>
                                <span class="font-mono font-bold text-emerald-400">₺{{ number_format($cat['total_revenue'], 2) }}</span>
                            </div>
                            <div class="w-full h-1.5 rounded-full bg-slate-800/60 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-indigo-500 to-fuchsia-500 rounded-full transition-all duration-500" style="width: {{ $catPercent }}%"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-slate-500 font-mono">
                                <span>{{ number_format($cat['sold_qty'], 0) }} adet</span>
                                <span>%{{ number_format($catPercent, 1) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-10 text-center text-slate-600 text-xs">Veri yok.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── 5. ADİSYON GEÇMİŞİ TABLOSU ── --}}
        <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl shadow-lg overflow-hidden">
            <div class="p-4 border-b border-slate-800/60 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400">
                    <i class="fi fi-rr-receipt text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Adisyon Geçmişi</h3>
                    <p class="text-[10px] text-slate-500">Saat, masa, personel, ürünler ve ödeme detayları</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/30 text-slate-500 uppercase tracking-wider text-[10px] font-bold border-b border-slate-800/40">
                        <tr>
                            <th class="py-3 px-4">Saat</th>
                            <th class="py-3 px-4">Adisyon</th>
                            <th class="py-3 px-4">Masa</th>
                            <th class="py-3 px-4">Personel</th>
                            <th class="py-3 px-4">Ürünler</th>
                            <th class="py-3 px-4 text-center">Ödeme</th>
                            <th class="py-3 px-4 text-right">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/30 font-medium">
                        @forelse($checksHistory as $checkItem)
                            @php
                                $paymentsList = $checkItem->payments;
                                $paymentTypes = $paymentsList->pluck('payment_method')->unique()->toArray();
                            @endphp
                            <tr class="table-row-hover">
                                <td class="py-3 px-4 font-mono">
                                    <span class="text-sm font-bold text-white block">{{ $checkItem->opened_at ? $checkItem->opened_at->format('H:i') : '--:--' }}</span>
                                    <span class="text-[9px] text-slate-600">{{ $checkItem->opened_at ? $checkItem->opened_at->format('d.m') : '' }}</span>
                                </td>
                                <td class="py-3 px-4 font-mono text-indigo-400 font-bold text-[11px]">#{{ $checkItem->check_number }}</td>
                                <td class="py-3 px-4">
                                    @if($checkItem->diningTable)
                                        <span class="px-2 py-0.5 rounded-lg bg-indigo-500/10 text-indigo-300 font-bold border border-indigo-500/15 text-[11px]">
                                            {{ $checkItem->diningTable->name }}
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-lg bg-amber-500/10 text-amber-300 font-bold border border-amber-500/15 text-[11px] inline-flex items-center gap-1">
                                            <i class="fi fi-rr-bolt text-[9px]"></i> Tezgah
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-slate-400 text-[11px]">{{ $checkItem->waiter?->name ?: 'Kasiyer' }}</td>
                                <td class="py-3 px-4 max-w-[220px]">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($checkItem->items->take(4) as $it)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-800/60 text-slate-300 text-[10px] font-medium border border-slate-700/30">
                                                {{ number_format($it->quantity, 0) }}x {{ Str::limit(!empty($it->product_name) ? $it->product_name : ($it->product?->name ?: 'Ürün'), 15) }}
                                            </span>
                                        @endforeach
                                        @if($checkItem->items->count() > 4)
                                            <span class="px-1.5 py-0.5 rounded bg-slate-800/40 text-slate-500 text-[10px]">+{{ $checkItem->items->count() - 4 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if(in_array('nakit', $paymentTypes))
                                        <span class="px-2 py-0.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/15 text-[10px] font-bold">Nakit</span>
                                    @elseif(in_array('kredi_karti', $paymentTypes))
                                        <span class="px-2 py-0.5 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/15 text-[10px] font-bold">Kart</span>
                                    @elseif(in_array('yemek_karti', $paymentTypes))
                                        <span class="px-2 py-0.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/15 text-[10px] font-bold">Yemek</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-lg bg-slate-800/40 text-slate-600 text-[10px] font-bold">Ödendi</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-emerald-400">
                                    ₺{{ number_format($checkItem->total, 2) }}
                                    @if($checkItem->discount_total > 0)
                                        <span class="block text-[9px] text-amber-400/70 font-normal">-₺{{ number_format($checkItem->discount_total, 2) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-14 text-center text-slate-600 text-xs">Sipariş kaydı bulunmuyor.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-t border-slate-800/40 no-print">
                {{ $checksHistory->links() }}
            </div>
        </div>

        {{-- ── 6. PERSONEL PERFORMANSI ── --}}
        <div class="rounded-2xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl shadow-lg overflow-hidden">
            <div class="p-4 border-b border-slate-800/60 flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400">
                    <i class="fi fi-rr-user-time text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white">Personel Performansı</h3>
                    <p class="text-[10px] text-slate-500">İşlem sayısı ve üretilen ciro</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 p-4">
                @forelse($waiterStats as $w)
                    @php $avgWaiter = $w->check_count > 0 ? ($w->total_sales / $w->check_count) : 0; @endphp
                    <div class="group flex items-center gap-3 p-3.5 rounded-xl bg-slate-950/30 border border-slate-800/40 hover:border-indigo-500/20 transition-all">
                        <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-300 font-bold flex items-center justify-center border border-indigo-500/20 group-hover:bg-indigo-600 group-hover:text-white transition-all text-sm shrink-0">
                            {{ mb_substr($w->waiter?->name ?: 'G', 0, 1) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-white truncate">{{ $w->waiter?->name ?: 'Tezgah Kasiyeri' }}</div>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-slate-500">
                                <span>{{ $w->check_count }} adisyon</span>
                                <span class="text-slate-700">•</span>
                                <span>Ort: ₺{{ number_format($avgWaiter, 0) }}</span>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-sm font-black text-emerald-400 font-mono">₺{{ number_format($w->total_sales, 0) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-10 text-center text-slate-600 text-xs">Personel verisi yok.</div>
                @endforelse
            </div>
        </div>

    </main>
</div>

{{-- ══════════════════════════════════════════════════════
     Z-RAPORU TERMAL FİŞ MODALI
══════════════════════════════════════════════════════ --}}
<div id="zReportModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm no-print" onclick="if(event.target===this)closeZReportModal()">
    <div class="relative w-full max-w-md bg-slate-900/95 border border-slate-800 rounded-2xl p-5 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">

        <div class="flex items-center justify-between pb-3 border-b border-slate-800 shrink-0">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fi fi-rr-receipt text-emerald-400"></i>
                Gün Sonu Z-Raporu
            </h3>
            <button onclick="closeZReportModal()" class="text-slate-500 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition text-sm">✕</button>
        </div>

        <div class="flex-1 overflow-y-auto py-4 report-scrollbar">
            <div id="zReportPrintArea" class="bg-white text-slate-900 p-5 rounded-xl shadow-inner font-mono text-xs border border-slate-200 mx-auto max-w-[300px] leading-relaxed">

                <div class="text-center font-bold text-sm uppercase tracking-wider mb-2">
                    ADİSYON POS<br>
                    *** GÜN SONU Z-RAPORU ***
                </div>

                <div class="text-center text-[10px] text-slate-600 mb-3 border-b border-dashed border-slate-400 pb-2">
                    DÖNEM: {{ $startDate->format('d.m.Y H:i') }} - {{ $endDate->format('d.m.Y H:i') }}<br>
                    Z-RAPOR NO: #Z-{{ $startDate->format('Ymd') }}
                </div>

                <div class="space-y-1 mb-3">
                    <div class="font-bold border-b border-slate-300 pb-1 mb-1">--- KASA TAHSİLAT ---</div>
                    <div class="flex justify-between"><span>NAKİT</span><span class="font-bold">₺{{ number_format($paymentBreakdown['nakit'], 2) }}</span></div>
                    <div class="flex justify-between"><span>KREDİ KARTI</span><span class="font-bold">₺{{ number_format($paymentBreakdown['kredi_karti'], 2) }}</span></div>
                    <div class="flex justify-between"><span>YEMEK KARTI</span><span class="font-bold">₺{{ number_format($paymentBreakdown['yemek_karti'], 2) }}</span></div>
                    <div class="flex justify-between font-bold text-sm border-t border-slate-800 pt-1 mt-1"><span>TOPLAM</span><span>₺{{ number_format($paymentBreakdown['total'], 2) }}</span></div>
                </div>

                <div class="space-y-1 mb-3">
                    <div class="font-bold border-b border-slate-300 pb-1 mb-1">--- CİRO ÖZETİ ---</div>
                    <div class="flex justify-between"><span>ADİSYON SAYISI</span><span class="font-bold">{{ $stats['total_checks_count'] }}</span></div>
                    <div class="flex justify-between"><span>ORT. MASA TUTARI</span><span class="font-bold">₺{{ number_format($stats['avg_check_amount'], 2) }}</span></div>
                    <div class="flex justify-between"><span>İSKONTO</span><span class="font-bold">-₺{{ number_format($stats['total_discounts'], 2) }}</span></div>
                    <div class="flex justify-between"><span>İKRAM ({{ $stats['complimentary_count'] }})</span><span class="font-bold">₺{{ number_format($stats['complimentary_total_amount'], 2) }}</span></div>
                    <div class="flex justify-between"><span>İPTAL ({{ $stats['cancelled_items_count'] }})</span><span class="font-bold">-₺{{ number_format($stats['cancelled_loss_amount'], 2) }}</span></div>
                    <div class="flex justify-between font-extrabold text-sm border-t border-b border-dashed border-slate-800 py-1 my-1"><span>NET CİRO</span><span>₺{{ number_format($stats['total_revenue'], 2) }}</span></div>
                </div>

                <div class="space-y-1 mb-3">
                    <div class="font-bold border-b border-slate-300 pb-1 mb-1">--- KATEGORİLER ---</div>
                    @foreach($categoryStatsMap as $cat)
                        <div class="flex justify-between"><span>{{ mb_strtoupper($cat['category_name']) }} ({{ $cat['sold_qty'] }})</span><span>₺{{ number_format($cat['total_revenue'], 2) }}</span></div>
                    @endforeach
                </div>

                <div class="space-y-1 mb-3">
                    <div class="font-bold border-b border-slate-300 pb-1 mb-1">--- TOP ÜRÜNLER ---</div>
                    @foreach($productStats->take(5) as $topProd)
                        <div class="flex justify-between"><span>{{ $topProd->sold_qty }}x {{ mb_strtoupper($topProd->product_name) }}</span><span>₺{{ number_format($topProd->total_revenue, 2) }}</span></div>
                    @endforeach
                </div>

                <div class="mt-5 pt-3 border-t border-dashed border-slate-400 text-center space-y-3">
                    <div>{{ now()->format('d.m.Y H:i:s') }}</div>
                    <div class="pt-5 font-bold">__________________________<br>Kasa Sorumlusu</div>
                    <div class="text-[9px] text-slate-500">Adisyon POS — Z-Raporu</div>
                </div>
            </div>
        </div>

        <div class="pt-3 border-t border-slate-800 flex justify-between items-center shrink-0">
            <button onclick="closeZReportModal()" class="px-3 py-1.5 rounded-xl text-slate-500 hover:bg-slate-800 text-xs font-bold transition">Kapat</button>
            <button onclick="printZReportSlip()" class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-lg shadow-emerald-600/25 transition flex items-center gap-2 cursor-pointer">
                <i class="fi fi-rr-print"></i>
                Yazdır
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function filterReportProducts() {
        const input = document.getElementById('productReportSearch');
        const filter = input.value.toLowerCase();
        const rows = document.getElementsByClassName('product-report-row');
        for (let i = 0; i < rows.length; i++) {
            const name = rows[i].getElementsByTagName('td')[0].innerText.toLowerCase();
            rows[i].style.display = name.includes(filter) ? '' : 'none';
        }
    }

    function openZReportModal() {
        document.getElementById('zReportModal').classList.remove('hidden');
    }

    function closeZReportModal() {
        document.getElementById('zReportModal').classList.add('hidden');
    }

    function printZReportSlip() {
        window.print();
    }
</script>
@endsection
