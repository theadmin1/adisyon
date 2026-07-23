@extends('layouts.app')

@section('title', '📊 Tüm Sistem Raporları & Gün Sonu Portalı')

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
        background: rgba(217, 70, 239, 0.3);
        border-radius: 8px;
    }
    @media print {
        header, .no-print {
            display: none !important;
        }
        body {
            background-color: white !important;
            color: black !important;
        }
        .print-only-bg {
            background: white !important;
            border: 1px solid #ddd !important;
            color: black !important;
        }
    }
</style>
@endsection

@section('content')
<div class="flex flex-col min-h-screen bg-[#07090e] text-slate-100 font-sans">
    
    <!-- Top Header Bar -->
    <header class="h-16 bg-[#0f131f]/95 border-b border-slate-800/80 px-4 sm:px-8 flex items-center justify-between z-30 shrink-0 backdrop-blur-md">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-all border border-slate-700/50">
                <i class="fi fi-rr-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold tracking-tight text-white flex items-center gap-2">
                    <span class="p-1 rounded-lg bg-fuchsia-500/10 text-fuchsia-400 border border-fuchsia-500/20">
                        <i class="fi fi-rr-chart-pie-alt"></i>
                    </span>
                    Tüm Sistem Raporları & Gün Sonu (Z-Raporu)
                </h1>
                <p class="text-[11px] text-slate-400 hidden sm:block">Restoran Ciro, Satış, Ödeme Yöntemleri, Adisyon Geçmişi ve İptal Analizi</p>
            </div>
        </div>

        <!-- Print & Refresh Tools -->
        <div class="flex items-center gap-3 no-print">
            <button onclick="window.print()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition flex items-center gap-2 border border-slate-700/50 cursor-pointer">
                <i class="fi fi-rr-print"></i>
                <span>Z-Raporu Yazdır</span>
            </button>
        </div>
    </header>

    <div class="p-4 sm:p-8 space-y-8 flex-1">

        <!-- 1. DATE RANGE FILTER BAR -->
        <div class="p-4 rounded-3xl bg-[#111524] border border-slate-800/80 shadow-xl flex flex-col md:flex-row items-center justify-between gap-4 no-print">
            
            <!-- Quick Preset Filters -->
            <div class="flex items-center gap-2 overflow-x-auto max-w-full pb-1 md:pb-0">
                <a href="{{ route('reports.index', ['period' => 'today']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition border {{ $period === 'today' ? 'bg-fuchsia-600 border-fuchsia-500 text-white shadow-lg shadow-fuchsia-600/30' : 'bg-[#161b2e] border-slate-800 text-slate-400 hover:text-white' }}">
                    📅 Bugün (Gün Sonu / Z)
                </a>

                <a href="{{ route('reports.index', ['period' => 'yesterday']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition border {{ $period === 'yesterday' ? 'bg-fuchsia-600 border-fuchsia-500 text-white shadow-lg shadow-fuchsia-600/30' : 'bg-[#161b2e] border-slate-800 text-slate-400 hover:text-white' }}">
                    ⏮️ Dün
                </a>

                <a href="{{ route('reports.index', ['period' => 'this_week']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition border {{ $period === 'this_week' ? 'bg-fuchsia-600 border-fuchsia-500 text-white shadow-lg shadow-fuchsia-600/30' : 'bg-[#161b2e] border-slate-800 text-slate-400 hover:text-white' }}">
                    🗓️ Bu Hafta
                </a>

                <a href="{{ route('reports.index', ['period' => 'this_month']) }}"
                   class="px-4 py-2 rounded-xl text-xs font-bold transition border {{ $period === 'this_month' ? 'bg-fuchsia-600 border-fuchsia-500 text-white shadow-lg shadow-fuchsia-600/30' : 'bg-[#161b2e] border-slate-800 text-slate-400 hover:text-white' }}">
                    📊 Bu Ay
                </a>
            </div>

            <!-- Custom Date Range Picker Form -->
            <form method="GET" action="{{ route('reports.index') }}" class="flex items-center gap-2 w-full md:w-auto">
                <input type="hidden" name="period" value="custom">
                <div class="flex items-center gap-2 bg-[#161b2e] border border-slate-800 p-1.5 rounded-2xl text-xs">
                    <input type="date" name="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}"
                           class="bg-slate-900 border border-slate-800 text-white px-2.5 py-1.5 rounded-xl outline-none focus:border-fuchsia-500">
                    <span class="text-slate-500 font-bold">-</span>
                    <input type="date" name="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}"
                           class="bg-slate-900 border border-slate-800 text-white px-2.5 py-1.5 rounded-xl outline-none focus:border-fuchsia-500">
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-fuchsia-600 hover:bg-fuchsia-500 text-white font-bold transition shadow cursor-pointer">
                        Filtrele
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Date Badge -->
        <div class="text-xs font-semibold text-slate-400 flex items-center justify-between">
            <span>Rapor Dönemi: <strong class="text-fuchsia-400 font-mono">{{ $startDate->format('d.m.Y H:i') }} - {{ $endDate->format('d.m.Y H:i') }}</strong></span>
            <span class="text-[11px] text-slate-500">Son güncelleme: {{ now()->format('H:i:s') }}</span>
        </div>

        <!-- 2. MAIN KPI STAT CARDS & Z-REPORT KASA SUMMARY -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            
            <!-- TOPLAM NET CİRO -->
            <div class="p-6 rounded-3xl bg-gradient-to-br from-[#13192e] to-[#18112e] border border-emerald-500/30 shadow-2xl relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 rounded-full bg-emerald-500/10 blur-2xl"></div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Toplam Net Ciro</span>
                    <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <i class="fi fi-rr-dollar text-xl"></i>
                    </span>
                </div>
                <div class="mt-4">
                    <span class="text-3xl sm:text-4xl font-black text-emerald-400 font-mono tracking-tight">
                        ₺{{ number_format($stats['total_revenue'], 2) }}
                    </span>
                </div>
                <div class="mt-3 flex items-center justify-between text-xs text-slate-400 border-t border-slate-800/80 pt-3">
                    <span>Adisyon Sayısı: <strong class="text-white font-mono">{{ $stats['total_checks_count'] }}</strong></span>
                    <span>Ortalama: <strong class="text-white font-mono">₺{{ number_format($stats['avg_check_amount'], 2) }}</strong></span>
                </div>
            </div>

            <!-- KASA ÖDEME DAĞILIMI (Z-RAPORU KASA ÖZETİ) -->
            <div class="p-6 rounded-3xl bg-[#111524] border border-slate-800/80 shadow-2xl col-span-1 md:col-span-2 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                        <i class="fi fi-rr-cash-register text-fuchsia-400"></i>
                        Kasa Ödeme Kırılımı (Z-Raporu Özeti)
                    </span>
                    <span class="text-xs font-mono font-bold text-slate-300">
                        Toplam Tahsilat: <strong class="text-emerald-400">₺{{ number_format($paymentBreakdown['total'], 2) }}</strong>
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-3 my-auto">
                    <!-- Nakit -->
                    <div class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-center">
                        <span class="text-[10px] font-bold text-emerald-300 uppercase block">💵 NAKİT</span>
                        <span class="text-lg font-black text-emerald-400 font-mono mt-0.5 block">
                            ₺{{ number_format($paymentBreakdown['nakit'], 2) }}
                        </span>
                    </div>

                    <!-- Kredi Kartı -->
                    <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-center">
                        <span class="text-[10px] font-bold text-indigo-300 uppercase block">💳 KREDİ KARTI</span>
                        <span class="text-lg font-black text-indigo-400 font-mono mt-0.5 block">
                            ₺{{ number_format($paymentBreakdown['kredi_karti'], 2) }}
                        </span>
                    </div>

                    <!-- Yemek Kartı -->
                    <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-center">
                        <span class="text-[10px] font-bold text-amber-300 uppercase block">🎫 YEMEK KARTI</span>
                        <span class="text-lg font-black text-amber-400 font-mono mt-0.5 block">
                            ₺{{ number_format($paymentBreakdown['yemek_karti'], 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- İSKONTO, İKRAM & İPTAL KAYBI -->
            <div class="p-6 rounded-3xl bg-[#111524] border border-slate-800/80 shadow-2xl flex flex-col justify-between space-y-3">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">İndirim & İptal Özeti</span>
                
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between items-center p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400">İskonto / İndirim:</span>
                        <span class="font-mono font-bold text-amber-400">₺{{ number_format($stats['total_discounts'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                        <span class="text-slate-400">İkram Edilen ({{ $stats['complimentary_count'] }} Adet):</span>
                        <span class="font-mono font-bold text-indigo-400">₺{{ number_format($stats['complimentary_total_amount'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-2 rounded-xl bg-rose-500/10 border border-rose-500/20">
                        <span class="text-rose-300 font-semibold">İptal/Zayi Kaybı ({{ $stats['cancelled_items_count'] }} Adet):</span>
                        <span class="font-mono font-bold text-rose-400">₺{{ number_format($stats['cancelled_loss_amount'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. TÜM ADİSYONLAR & SİPARİŞ GEÇMİŞİ TABLOSU (HER ADİSYON SAAT, TUTAR, ÖDEME YÖNTEMİ, MASA VE ÜRÜNLER İLE) -->
        <div class="bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl space-y-2">
            <div class="p-5 border-b border-slate-800/80 bg-[#161b2e] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <i class="fi fi-rr-receipt text-emerald-400 text-lg"></i>
                        Tüm Adisyonlar & Tekil Sipariş Geçmişi
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Seçilen dönemdeki her adisyonun saat kaçta açıldığı, hangi masada olduğu, hangi ürünleri içerdiği ve ne ile ödendiği</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-[#161b2e] text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-800">
                        <tr>
                            <th class="py-4 px-6">Saat / Tarih</th>
                            <th class="py-4 px-6">Adisyon No</th>
                            <th class="py-4 px-6">Masa / Konum</th>
                            <th class="py-4 px-6">Personel</th>
                            <th class="py-4 px-6">İçerdiği Ürünler</th>
                            <th class="py-4 px-6 text-center">Ödeme Yöntemi</th>
                            <th class="py-4 px-6 text-right">Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-semibold">
                        @forelse($checksHistory as $checkItem)
                            @php
                                $paymentsList = $checkItem->payments;
                                $paymentTypes = $paymentsList->pluck('payment_method')->unique()->toArray();
                            @endphp
                            <tr class="hover:bg-slate-900/40 transition">
                                <!-- Saat / Tarih -->
                                <td class="py-4 px-6 font-mono text-slate-300">
                                    <span class="text-sm font-black text-white block">{{ $checkItem->opened_at ? $checkItem->opened_at->format('H:i') : '--:--' }}</span>
                                    <span class="text-[10px] text-slate-500">{{ $checkItem->opened_at ? $checkItem->opened_at->format('d.m.Y') : '' }}</span>
                                </td>

                                <!-- Adisyon No -->
                                <td class="py-4 px-6 font-mono text-cyan-400 font-bold">
                                    #{{ $checkItem->check_number }}
                                </td>

                                <!-- Masa / Konum -->
                                <td class="py-4 px-6">
                                    @if($checkItem->diningTable)
                                        <span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-300 font-bold border border-indigo-500/20">
                                            {{ $checkItem->diningTable->name }}
                                            @if($checkItem->diningTable->hall)
                                                <span class="text-[10px] text-slate-400">({{ $checkItem->diningTable->hall->name }})</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-300 font-bold border border-amber-500/20">
                                            ⚡ Hızlı Satış (Tezgah)
                                        </span>
                                    @endif
                                </td>

                                <!-- Personel -->
                                <td class="py-4 px-6 text-slate-300">
                                    {{ $checkItem->waiter?->name ?: 'Kasiyer' }}
                                </td>

                                <!-- İçerdiği Ürünler -->
                                <td class="py-4 px-6 max-w-xs">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($checkItem->items as $it)
                                            <span class="px-2 py-0.5 rounded bg-slate-800 text-slate-200 text-[11px] font-semibold border border-slate-700/50">
                                                {{ number_format($it->quantity, 0) }}x {{ $it->product_name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>

                                <!-- Ödeme Yöntemi -->
                                <td class="py-4 px-6 text-center">
                                    @if(in_array('nakit', $paymentTypes))
                                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[11px] font-bold">
                                            💵 Nakit
                                        </span>
                                    @elseif(in_array('kredi_karti', $paymentTypes))
                                        <span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[11px] font-bold">
                                            💳 K. Kartı
                                        </span>
                                    @elseif(in_array('yemek_karti', $paymentTypes))
                                        <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[11px] font-bold">
                                            🎫 Yemek K.
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-500 text-[11px] font-bold">
                                            Ödendi
                                        </span>
                                    @endif
                                </td>

                                <!-- Toplam Tutar -->
                                <td class="py-4 px-6 text-right font-mono font-black text-emerald-400 text-base">
                                    ₺{{ number_format($checkItem->total, 2) }}
                                    @if($checkItem->discount_total > 0)
                                        <span class="block text-[10px] text-amber-400 font-normal">(-₺{{ number_format($checkItem->discount_total, 2) }} İsk.)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-16 text-center text-slate-500">
                                    Seçilen dönemde sipariş kaydı bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-800/80 bg-[#161b2e] no-print">
                {{ $checksHistory->links() }}
            </div>
        </div>

        <!-- 4. SAATLİK SATIŞ YOĞUNLUĞU ANALİZİ -->
        <div class="p-6 rounded-3xl bg-[#111524] border border-slate-800/80 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <i class="fi fi-rr-time-twenty-four text-fuchsia-400"></i>
                        Saatlik Satış Yoğunluk Dağılımı (24 Saat)
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Günün hangi saatlerinde ciro ve adisyon yoğunlaşmasını gösterir</p>
                </div>
            </div>

            @php
                $maxHourlyAmount = collect($hourlyData)->max('amount') ?: 1;
            @endphp

            <div class="grid grid-cols-12 md:grid-cols-24 gap-1.5 items-end h-40 pt-6 px-2 bg-[#0c0e17] rounded-2xl border border-slate-800/80 overflow-x-auto custom-scrollbar">
                @foreach($hourlyData as $item)
                    @php
                        $heightPercent = ($item['amount'] / $maxHourlyAmount) * 100;
                        $hasSales = $item['amount'] > 0;
                    @endphp
                    <div class="flex flex-col items-center gap-1 group relative h-full justify-end min-w-[28px]">
                        
                        <!-- Hover Tooltip -->
                        <div class="absolute -top-12 hidden group-hover:flex flex-col items-center bg-slate-900 border border-slate-700 px-2 py-1 rounded-lg text-[10px] font-mono z-30 shadow-xl whitespace-nowrap pointer-events-none">
                            <span class="font-bold text-fuchsia-300">{{ $item['hour'] }}</span>
                            <span class="text-emerald-400 font-extrabold">₺{{ number_format($item['amount'], 2) }}</span>
                            <span class="text-slate-400">{{ $item['count'] }} Adisyon</span>
                        </div>

                        <!-- Bar -->
                        <div class="w-full rounded-t-md transition-all duration-300 {{ $hasSales ? 'bg-gradient-to-t from-fuchsia-600 to-indigo-500 group-hover:from-fuchsia-500 group-hover:to-cyan-400' : 'bg-slate-800/40' }}"
                             style="height: {{ max(4, $heightPercent) }}%"></div>

                        <!-- Hour Label -->
                        <span class="text-[9px] font-mono text-slate-500 mt-1">{{ substr($item['hour'], 0, 2) }}h</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- 5. GRID: ÜRÜN SATIŞ PERFORMANSI & KATEGORİ DAĞILIMI -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- LEFT: Ürün Bazlı Detaylı Adisyon Raporu (2 Cols) -->
            <div class="lg:col-span-2 bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl flex flex-col">
                <div class="p-5 border-b border-slate-800/80 bg-[#161b2e] flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                            <i class="fi fi-rr-box-alt text-cyan-400"></i>
                            Ürün Bazlı Satış Performansı
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">Satılan adetler, elde edilen toplam gelir ve iptal sayıları</p>
                    </div>

                    <input type="text" id="productReportSearch" onkeyup="filterReportProducts()" placeholder="Ürün ara..."
                           class="px-3 py-1.5 bg-[#0e111d] border border-slate-800 text-xs text-white placeholder-slate-500 rounded-xl outline-none focus:border-fuchsia-500 w-48">
                </div>

                <div class="overflow-x-auto flex-1 max-h-96 custom-scrollbar">
                    <table id="productReportTable" class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-[#161b2e] text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-800 sticky top-0 z-10">
                            <tr>
                                <th class="py-3 px-6">Ürün Adı</th>
                                <th class="py-3 px-6 text-center">Satılan Adet</th>
                                <th class="py-3 px-6 text-center">İptal Edilen</th>
                                <th class="py-3 px-6 text-right">Elde Edilen Ciro</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-semibold">
                            @forelse($productStats as $stat)
                                <tr class="hover:bg-slate-900/40 transition product-report-row">
                                    <td class="py-3.5 px-6 font-bold text-white text-sm">
                                        {{ $stat->product_name }}
                                    </td>
                                    <td class="py-3.5 px-6 text-center text-emerald-400 font-extrabold text-base">
                                        {{ number_format($stat->sold_qty, 0) }}
                                    </td>
                                    <td class="py-3.5 px-6 text-center {{ $stat->cancelled_qty > 0 ? 'text-rose-400 font-bold' : 'text-slate-600' }}">
                                        {{ number_format($stat->cancelled_qty, 0) }}
                                    </td>
                                    <td class="py-3.5 px-6 text-right font-mono font-black text-cyan-400 text-sm">
                                        ₺{{ number_format($stat->total_revenue, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-500">
                                        Seçilen dönemde satış kaydı bulunmuyor.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- RIGHT: Kategori Bazlı Ciro Dağılımı (1 Col) -->
            <div class="bg-[#111524] border border-slate-800/80 rounded-3xl p-6 shadow-2xl flex flex-col space-y-4">
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <i class="fi fi-rr-apps text-indigo-400"></i>
                        Kategori Ciro Dağılımı
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Kategorilerin toplam cirodaki payı</p>
                </div>

                <div class="space-y-4 flex-1 overflow-y-auto max-h-96 custom-scrollbar pr-1">
                    @forelse($categoryStatsMap as $cat)
                        @php
                            $catPercent = $stats['total_revenue'] > 0 ? ($cat['total_revenue'] / $stats['total_revenue']) * 100 : 0;
                        @endphp
                        <div class="p-3 rounded-2xl bg-[#161b2e] border border-slate-800 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-extrabold text-white">{{ $cat['category_name'] }}</span>
                                <span class="font-mono font-black text-emerald-400">₺{{ number_format($cat['total_revenue'], 2) }}</span>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="w-full h-2 rounded-full bg-slate-900 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-fuchsia-500 to-indigo-500 rounded-full" style="width: {{ $catPercent }}%"></div>
                            </div>

                            <div class="flex justify-between text-[10px] text-slate-400 font-mono">
                                <span>{{ number_format($cat['sold_qty'], 0) }} Adet Satıldı</span>
                                <span>%{{ number_format($catPercent, 1) }} Pay</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-slate-500 text-xs">
                            Kategori dağılımı bulunmuyor.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 6. GARSON / PERSONEL PERFORMANS RAPORU -->
        <div class="bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-5 border-b border-slate-800/80 bg-[#161b2e]">
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fi fi-rr-user-time text-amber-400"></i>
                    Garson & Personel Satış Performans Raporu
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Personel bazında işlem sayısı ve üretilen toplam ciro</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-[#161b2e] text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-800">
                        <tr>
                            <th class="py-4 px-6">Personel Adı</th>
                            <th class="py-4 px-6 text-center">Kapatılan Adisyon</th>
                            <th class="py-4 px-6 text-center">Ortalama Masa Tutarı</th>
                            <th class="py-4 px-6 text-right">Üretilen Toplam Ciro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-semibold">
                        @forelse($waiterStats as $w)
                            @php
                                $avgWaiter = $w->check_count > 0 ? ($w->total_sales / $w->check_count) : 0;
                            @endphp
                            <tr class="hover:bg-slate-900/40 transition">
                                <td class="py-4 px-6 font-extrabold text-white text-sm flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-slate-800 text-indigo-300 font-bold flex items-center justify-center border border-slate-700">
                                        {{ mb_substr($w->waiter?->name ?: 'Garson', 0, 1) }}
                                    </div>
                                    <span>{{ $w->waiter?->name ?: 'Tezgah / Sistem Kasiyeri' }}</span>
                                </td>
                                <td class="py-4 px-6 text-center text-white font-mono font-bold">
                                    {{ $w->check_count }}
                                </td>
                                <td class="py-4 px-6 text-center text-slate-300 font-mono">
                                    ₺{{ number_format($avgWaiter, 2) }}
                                </td>
                                <td class="py-4 px-6 text-right font-mono font-black text-emerald-400 text-base">
                                    ₺{{ number_format($w->total_sales, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-500">
                                    Personel satış kaydı bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 7. İPTAL & İADE DETAY LOG RAPORU -->
        <div class="bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-5 border-b border-slate-800/80 bg-[#161b2e] flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                        <i class="fi fi-rr-cross-circle text-rose-400"></i>
                        İptal & İade Detay Kayıtları
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">Seçilen dönemde masalardan veya mutfaktan iptal edilen tüm siparişler</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-[#161b2e] text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-800">
                        <tr>
                            <th class="py-4 px-6">Tarih</th>
                            <th class="py-4 px-6">İptal Edilen Ürün</th>
                            <th class="py-4 px-6">Masa / Adisyon</th>
                            <th class="py-4 px-6 text-center">İptal Miktarı</th>
                            <th class="py-4 px-6 text-right">Kayıp Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 font-semibold">
                        @forelse($cancelledItemsList as $cItem)
                            <tr class="hover:bg-slate-900/40 transition">
                                <td class="py-4 px-6 text-slate-400 font-mono">
                                    {{ $cItem->created_at->format('d.m.Y H:i') }}
                                </td>
                                <td class="py-4 px-6 font-extrabold text-white text-sm">
                                    {{ $cItem->product_name }}
                                </td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-400 font-bold border border-indigo-500/20">
                                        {{ $cItem->check?->diningTable?->name ?: 'Tezgah' }} (#{{ $cItem->check?->check_number }})
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center text-rose-400 font-black text-sm">
                                    {{ number_format($cItem->quantity, 0) }} {{ $cItem->product?->unit ?: 'adet' }}
                                </td>
                                <td class="py-4 px-6 text-right font-mono font-bold text-rose-400">
                                    ₺{{ number_format($cItem->unit_price * $cItem->quantity, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-500">
                                    Seçilen dönemde herhangi bir iptal kaydı bulunmuyor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
            const productName = rows[i].getElementsByTagName('td')[0].innerText.toLowerCase();
            if (productName.includes(filter)) {
                rows[i].style.display = '';
            } else {
                rows[i].style.display = 'none';
            }
        }
    }
</script>
@endsection
