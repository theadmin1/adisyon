@extends('layouts.app')

@section('title', '📦 Stok Yönetimi & İade Onay Portalı')

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
        background: rgba(6, 182, 212, 0.3);
        border-radius: 8px;
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
                    <span class="p-1 rounded-lg bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                        <i class="fi fi-rr-boxes"></i>
                    </span>
                    Stok Yönetimi & Takip Portalı
                </h1>
                <p class="text-[11px] text-slate-400 hidden sm:block">Ürün Stok Kodu, Otomatik Satış Düşümü & İptal İade Onay Sistemi</p>
            </div>
        </div>

        <!-- Right Search & Tools -->
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('stocks.index') }}" class="relative w-64 hidden sm:block">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <i class="fi fi-rr-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="{{ $search }}"
                       placeholder="Ürün adı veya Stok Kodu (SKU)..."
                       class="w-full bg-[#121626] border border-slate-800 text-white placeholder-slate-500 text-xs font-semibold py-2.5 pl-9 pr-4 rounded-xl outline-none focus:border-cyan-500 transition">
            </form>
        </div>
    </header>

    @if(session('status'))
        <div class="mx-6 mt-4 p-4 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 text-cyan-300 text-xs font-bold flex items-center gap-2">
            <i class="fi fi-rr-check-circle text-base"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <div class="p-4 sm:p-8 space-y-6 flex-1">

        <!-- 1. STATS OVERVIEW CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <!-- Toplam Ürün -->
            <div class="p-4 rounded-2xl bg-[#111524] border border-slate-800/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Toplam Ürün</span>
                    <span class="text-2xl font-black text-white mt-1 block">{{ $stats['total_products'] }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center justify-center">
                    <i class="fi fi-rr-box-open text-lg"></i>
                </div>
            </div>

            <!-- Mevcut Stok Toplamı -->
            <div class="p-4 rounded-2xl bg-[#111524] border border-slate-800/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Mevcut Toplam Stok</span>
                    <span class="text-2xl font-black text-cyan-400 mt-1 block">{{ number_format($stats['total_stock'], 0) }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 flex items-center justify-center">
                    <i class="fi fi-rr-boxes text-lg"></i>
                </div>
            </div>

            <!-- Satılan / Düşen Stok -->
            <div class="p-4 rounded-2xl bg-[#111524] border border-slate-800/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Satıştan Düşen</span>
                    <span class="text-2xl font-black text-emerald-400 mt-1 block">{{ number_format($stats['total_deductions'], 0) }}</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center justify-center">
                    <i class="fi fi-rr-shopping-cart text-lg"></i>
                </div>
            </div>

            <!-- Kritik Stok Uyarısı -->
            <div class="p-4 rounded-2xl bg-[#111524] border border-slate-800/80 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Kritik Stok Uyarısı</span>
                    <span class="text-2xl font-black {{ $stats['critical_stock_count'] > 0 ? 'text-amber-400' : 'text-slate-200' }} mt-1 block">
                        {{ $stats['critical_stock_count'] }} Ürün
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center justify-center">
                    <i class="fi fi-rr-exclamation text-lg"></i>
                </div>
            </div>

            <!-- Onay Bekleyen İptal İadeleri -->
            <div class="p-4 rounded-2xl bg-[#111524] border border-slate-800/80 flex items-center justify-between col-span-2 md:col-span-1">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">İptal İade Onay Bekleyen</span>
                    <span class="text-2xl font-black {{ $stats['pending_returns_count'] > 0 ? 'text-rose-400 animate-pulse' : 'text-slate-200' }} mt-1 block">
                        {{ $stats['pending_returns_count'] }} İptal
                    </span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center">
                    <i class="fi fi-rr-time-fast text-lg"></i>
                </div>
            </div>
        </div>

        <!-- 2. NAVIGATION TABS -->
        <div class="flex items-center gap-3 border-b border-slate-800/80 pb-3 overflow-x-auto">
            <a href="{{ route('stocks.index', ['tab' => 'list']) }}"
               class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $tab === 'list' ? 'bg-cyan-600 border-cyan-500 text-white shadow-lg shadow-cyan-600/30' : 'bg-[#111524] border-slate-800 text-slate-400 hover:text-white' }}">
                <i class="fi fi-rr-boxes"></i>
                <span>STOK LİSTESİ</span>
            </a>

            <a href="{{ route('stocks.index', ['tab' => 'pending_returns']) }}"
               class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border relative {{ $tab === 'pending_returns' ? 'bg-rose-600 border-rose-500 text-white shadow-lg shadow-rose-600/30' : 'bg-[#111524] border-slate-800 text-slate-400 hover:text-white' }}">
                <i class="fi fi-rr-refresh"></i>
                <span>İPTAL İADE ONAYLARI</span>
                @if($stats['pending_returns_count'] > 0)
                    <span class="px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px] font-mono font-bold">
                        {{ $stats['pending_returns_count'] }}
                    </span>
                @endif
            </a>

            <a href="{{ route('stocks.index', ['tab' => 'movements']) }}"
               class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all flex items-center gap-2 border {{ $tab === 'movements' ? 'bg-indigo-600 border-indigo-500 text-white shadow-lg shadow-indigo-600/30' : 'bg-[#111524] border-slate-800 text-slate-400 hover:text-white' }}">
                <i class="fi fi-rr-time-past"></i>
                <span>STOK HAREKET GÜNLÜĞÜ</span>
            </a>
        </div>

        <!-- 3. TAB CONTENT -->
        @if($tab === 'list')
            <!-- STOK LİSTESİ TABLOSU -->
            <div class="bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-[#161b2e] text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-800">
                            <tr>
                                <th class="py-4 px-6">Stok Kodu (SKU)</th>
                                <th class="py-4 px-6">Ürün Adı</th>
                                <th class="py-4 px-6">Kategori</th>
                                <th class="py-4 px-6 text-center">Mevcut Stok</th>
                                <th class="py-4 px-6 text-center">Satıştan Düşen</th>
                                <th class="py-4 px-6 text-center">Kritik Seviye</th>
                                <th class="py-4 px-6 text-center">Stok Durumu</th>
                                <th class="py-4 px-6 text-right">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-semibold">
                            @forelse($products as $product)
                                @php
                                    $movementsGroup = $stockStatsMap->get($product->id, collect([]));
                                    $totalDeducted = $movementsGroup->where('type', 'sale_deduction')->sum('total_qty');
                                    $isCritical = $product->track_stock && $product->stock_quantity <= $product->min_stock_level;
                                @endphp
                                <tr class="hover:bg-slate-900/40 transition">
                                    <td class="py-4 px-6 font-mono text-cyan-400 font-bold">
                                        {{ $product->sku ?: 'SKU-' . str_pad($product->id, 5, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="py-4 px-6 font-extrabold text-white text-sm">
                                        {{ $product->name }}
                                    </td>
                                    <td class="py-4 px-6 text-slate-400">
                                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-[11px]">
                                            {{ $product->category?->name ?: 'Genel' }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <span class="text-base font-black {{ $isCritical ? 'text-rose-400' : 'text-white' }}">
                                            {{ number_format($product->stock_quantity, 0) }} {{ $product->unit }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center text-emerald-400 font-bold">
                                        -{{ number_format($totalDeducted, 0) }} {{ $product->unit }}
                                    </td>
                                    <td class="py-4 px-6 text-center text-slate-400">
                                        {{ number_format($product->min_stock_level, 0) }} {{ $product->unit }}
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        @if(!$product->track_stock)
                                            <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-500 text-[10px] font-bold">Takipsiz</span>
                                        @elseif($isCritical)
                                            <span class="px-2.5 py-1 rounded-lg bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[10px] font-bold uppercase animate-pulse">KRİTİK STOK</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold uppercase">Yeterli</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <button onclick="openStockModal({{ $product->id }}, '{{ addslashes($product->name) }}', '{{ $product->sku }}', {{ $product->stock_quantity }}, {{ $product->min_stock_level }}, '{{ $product->unit }}', {{ $product->track_stock ? 'true' : 'false' }})"
                                                class="px-3.5 py-1.5 rounded-xl bg-cyan-600/20 hover:bg-cyan-600 border border-cyan-500/30 text-cyan-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 ml-auto cursor-pointer">
                                            <i class="fi fi-rr-edit"></i>
                                            <span>Düzenle</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-slate-500">
                                        Aradığınız kriterde ürün bulunamadı.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-800/80 bg-[#161b2e]">
                    {{ $products->links() }}
                </div>
            </div>

        @elseif($tab === 'pending_returns')
            <!-- İPTAL İADE ONAYLARI TABLOSU -->
            <div class="bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl">
                <div class="p-5 border-b border-slate-800/80 bg-[#161b2e] flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-extrabold text-white">İptal Edilen Siparişlerin Stoka İade Onayları</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Masalarda veya Hızlı Satışta iptal edilen ürünleri inceleyip stoğa iade edebilir veya zayi olarak kaydedebilirsiniz.</p>
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
                                <th class="py-4 px-6">İptal Açıklaması</th>
                                <th class="py-4 px-6 text-right">İade Aksiyonu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-semibold">
                            @forelse($pendingReturns as $return)
                                <tr class="hover:bg-slate-900/40 transition">
                                    <td class="py-4 px-6 text-slate-400 font-mono">
                                        {{ $return->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="py-4 px-6 font-extrabold text-white text-sm">
                                        {{ $return->product?->name }}
                                        <span class="block text-[10px] text-slate-500 font-mono">{{ $return->product?->sku }}</span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-400 font-bold border border-indigo-500/20">
                                            {{ $return->check?->diningTable?->name ?: 'Tezgah' }} (#{{ $return->check?->check_number }})
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center text-rose-400 font-black text-base">
                                        {{ number_format($return->quantity, 0) }} {{ $return->product?->unit ?: 'adet' }}
                                    </td>
                                    <td class="py-4 px-6 text-slate-400">
                                        {{ $return->notes }}
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- STOKA İADE ET (ONAYLA) -->
                                            <form method="POST" action="{{ route('stocks.approve', $return) }}" class="inline-block">
                                                @csrf
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow-lg shadow-emerald-600/30 flex items-center gap-1 cursor-pointer">
                                                    <i class="fi fi-rr-check"></i>
                                                    <span>Stoka İade Et (+{{ number_format($return->quantity, 0) }})</span>
                                                </button>
                                            </form>

                                            <!-- FİRE / ZAYİ (STOKA AKTARMA) -->
                                            <form method="POST" action="{{ route('stocks.reject', $return) }}" class="inline-block">
                                                @csrf
                                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-bold transition flex items-center gap-1 cursor-pointer">
                                                    <i class="fi fi-rr-cross"></i>
                                                    <span>Fire / Zayi (Aktarma)</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-16 text-center text-slate-500">
                                        <i class="fi fi-rr-check-circle text-4xl block mb-2 opacity-30"></i>
                                        Onay bekleyen herhangi bir iptal sipariş iadesi bulunmuyor.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($tab === 'movements')
            <!-- STOK HAREKET GÜNLÜĞÜ TABLOSU -->
            <div class="bg-[#111524] border border-slate-800/80 rounded-3xl overflow-hidden shadow-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-[#161b2e] text-slate-400 uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-800">
                            <tr>
                                <th class="py-4 px-6">Tarih</th>
                                <th class="py-4 px-6">Ürün</th>
                                <th class="py-4 px-6">İşlem Türü</th>
                                <th class="py-4 px-6 text-center">Miktar</th>
                                <th class="py-4 px-6">İşlemi Yapan / Açıklama</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-semibold">
                            @forelse($movements as $m)
                                @php
                                    $typeBadge = match($m->type) {
                                        'sale_deduction' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'cancellation_pending' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                        'return_approved' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                                        'manual_addition' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                        default => 'bg-slate-800 text-slate-400',
                                    };
                                    $typeName = match($m->type) {
                                        'sale_deduction' => 'Satış Düşümü',
                                        'cancellation_pending' => 'İptal İade Talebi',
                                        'return_approved' => 'Stoka İade Onayı',
                                        'manual_addition' => 'Manuel Stok Girişi',
                                        'manual_subtraction' => 'Manuel Düzeltme',
                                        default => 'İşlem',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-900/40 transition">
                                    <td class="py-4 px-6 text-slate-400 font-mono">
                                        {{ $m->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="py-4 px-6 font-extrabold text-white text-sm">
                                        {{ $m->product?->name }}
                                        <span class="block text-[10px] text-slate-500 font-mono">{{ $m->product?->sku }}</span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 rounded-lg border text-[10px] font-bold {{ $typeBadge }}">
                                            {{ $typeName }}
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center font-black text-sm {{ $m->type === 'sale_deduction' ? 'text-emerald-400' : 'text-cyan-400' }}">
                                        {{ number_format($m->quantity, 0) }} {{ $m->product?->unit ?: 'adet' }}
                                    </td>
                                    <td class="py-4 px-6 text-slate-300">
                                        {{ $m->notes }}
                                        @if($m->approvedByUser)
                                            <span class="block text-[10px] text-slate-500 mt-0.5">İşlem Yapan: {{ $m->approvedByUser->name }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-16 text-center text-slate-500">
                                        Henüz stok hareketi kaydedilmedi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-800/80 bg-[#161b2e]">
                    {{ $movements->links() }}
                </div>
            </div>
        @endif

    </div>
</div>

<!-- STOK DÜZENLEME MODALI -->
<div id="stockModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
    <div class="relative w-full max-w-md bg-[#111524] border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl">
        <button onclick="closeStockModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white p-2 rounded-full hover:bg-slate-800 transition">
            ✕
        </button>

        <div class="mb-6">
            <h3 class="text-xl font-extrabold text-white" id="modalProductName">Ürün Stok Düzenle</h3>
            <p class="text-xs text-slate-400 mt-1">Stok Kodu (SKU), Mevcut Miktar ve Kritik Uyarı Seviyesi</p>
        </div>

        <form id="stockForm" method="POST" action="">
            @csrf
            <div class="space-y-4 text-xs font-semibold text-slate-300">
                <!-- Stok Kodu (SKU) -->
                <div>
                    <label class="block mb-1.5 text-slate-400">Stok Kodu (SKU)</label>
                    <input type="text" id="modalSku" name="sku" placeholder="Örn: STK-1002"
                           class="w-full bg-[#161b2e] border border-slate-800 text-white font-mono font-bold py-3 px-4 rounded-xl outline-none focus:border-cyan-500 transition">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Mevcut Stok Miktarı -->
                    <div>
                        <label class="block mb-1.5 text-slate-400">Mevcut Stok Miktarı</label>
                        <input type="number" step="0.01" id="modalStockQuantity" name="stock_quantity" required
                               class="w-full bg-[#161b2e] border border-slate-800 text-white font-bold py-3 px-4 rounded-xl outline-none focus:border-cyan-500 transition">
                    </div>

                    <!-- Kritik Stok Seviyesi -->
                    <div>
                        <label class="block mb-1.5 text-slate-400">Kritik Uyarı Seviyesi</label>
                        <input type="number" step="0.01" id="modalMinStockLevel" name="min_stock_level" required
                               class="w-full bg-[#161b2e] border border-slate-800 text-white font-bold py-3 px-4 rounded-xl outline-none focus:border-cyan-500 transition">
                    </div>
                </div>

                <!-- Birim -->
                <div>
                    <label class="block mb-1.5 text-slate-400">Birim</label>
                    <select id="modalUnit" name="unit" class="w-full bg-[#161b2e] border border-slate-800 text-white font-bold py-3 px-4 rounded-xl outline-none focus:border-cyan-500 transition">
                        <option value="adet">Adet</option>
                        <option value="porsiyon">Porsiyon</option>
                        <option value="kg">Kilogram (kg)</option>
                        <option value="lt">Litre (lt)</option>
                        <option value="paket">Paket</option>
                    </select>
                </div>

                <!-- Stok Takibi Aktif mi -->
                <div class="flex items-center gap-2 pt-2">
                    <input type="checkbox" id="modalTrackStock" name="track_stock" value="1" class="w-4 h-4 rounded border-slate-800 text-cyan-600 focus:ring-cyan-500">
                    <label for="modalTrackStock" class="text-xs text-slate-300 cursor-pointer">Bu ürün için stok takibini aktif et</label>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2.5 rounded-xl text-slate-400 hover:bg-slate-800 text-xs font-bold transition">İptal</button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-black shadow-lg shadow-cyan-600/30 transition">
                    Stoku Kaydet
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openStockModal(productId, productName, sku, stockQuantity, minStockLevel, unit, trackStock) {
        document.getElementById('modalProductName').innerText = productName + ' Stok Düzenle';
        document.getElementById('modalSku').value = sku || '';
        document.getElementById('modalStockQuantity').value = stockQuantity;
        document.getElementById('modalMinStockLevel').value = minStockLevel;
        document.getElementById('modalUnit').value = unit || 'adet';
        document.getElementById('modalTrackStock').checked = trackStock;

        const form = document.getElementById('stockForm');
        form.action = `/stocks/${productId}`;

        const modal = document.getElementById('stockModal');
        modal.classList.remove('hidden');
    }

    function closeStockModal() {
        document.getElementById('stockModal').classList.add('hidden');
    }
</script>
@endsection
