@extends('layouts.app')

@section('title', 'Masa Planı & Salon Yönetimi - Adisyon POS')

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans antialiased">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-[#121522]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3.5 flex items-center justify-between shadow-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center text-slate-300 hover:text-white transition-all shadow-md" title="Ana Panele Dön">
                <i class="fi fi-rr-arrow-left text-base"></i>
            </a>
            <div>
                <h1 class="font-extrabold text-lg tracking-wide text-white flex items-center gap-2">
                    <i class="fi fi-rr-room-service text-indigo-400"></i>
                    <span>Masa Planı & Salon Yönetimi</span>
                </h1>
                <p class="text-xs text-slate-400">Restoran salonlarınızı, masa doluluklarını ve açık adisyonları yönetin.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if(session('status'))
                <div class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
                    <i class="fi fi-rr-check-circle text-sm"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-1 w-full p-4 sm:p-6 lg:p-6 space-y-4">

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-950/70 border border-rose-500/50 text-rose-200 text-xs font-semibold shadow-xl space-y-1">
                <div class="flex items-center gap-2 text-rose-400 font-bold text-sm">
                    <i class="fi fi-rr-cross-circle"></i>
                    <span>İşlem Sırasında Hata Oluştu:</span>
                </div>
                <ul class="list-disc list-inside space-y-0.5 text-rose-300">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- SALON TABS & STATUS FILTERS -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 bg-[#131625] p-3.5 rounded-2xl border border-slate-800/80 shadow-xl">
            <!-- Salon Horizontal Scroll Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 md:pb-0 no-scrollbar">
                <button type="button" 
                        onclick="filterHall('all')" 
                        class="hall-filter-btn px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all bg-indigo-600 text-white shadow-lg shadow-indigo-600/30"
                        data-hall="all">
                    Tüm Salonlar ({{ $stats['total_tables'] ?? 0 }})
                </button>

                @foreach($groupedTables as $hallName => $hallTables)
                    <button type="button" 
                            onclick="filterHall('hall-{{ Str::slug($hallName) }}')" 
                            class="hall-filter-btn px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all bg-slate-900 text-slate-400 hover:text-white border border-slate-800"
                            data-hall="hall-{{ Str::slug($hallName) }}">
                        {{ $hallName }} ({{ $hallTables->count() }})
                    </button>
                @endforeach
            </div>

            <!-- Status Filter & Search -->
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('tables.index') }}" class="flex items-center gap-2">
                    <select name="status" onchange="this.form.submit()" class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-300 focus:border-indigo-500 focus:outline-none transition">
                        <option value="">Tüm Durumlar</option>
                        <option value="available" @selected(request('status') === 'available')>Boş Masalar</option>
                        <option value="occupied" @selected(request('status') === 'occupied')>Dolu Masalar</option>
                        <option value="awaiting_payment" @selected(request('status') === 'awaiting_payment')>Hesap Bekleyenler</option>
                    </select>
                </form>

                <div class="relative min-w-[200px]">
                    <i class="fi fi-rr-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="tableSearchInput" onkeyup="filterTablesBySearch()" placeholder="Masa ara..." class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none transition">
                </div>
            </div>
        </div>

        <!-- TABLES GRID -->
        @if($tables->isEmpty())
            <div class="p-12 text-center bg-[#131625] border border-slate-800/80 rounded-2xl space-y-4 shadow-xl">
                <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mx-auto text-2xl">
                    <i class="fi fi-rr-room-service"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Henüz Masa Kaydı Bulunmuyor</h3>
                    <p class="text-xs text-slate-400 mt-1">Salonlarınıza masa ekleyerek adisyon takibine başlayabilirsiniz.</p>
                </div>
                <a href="{{ route('settings.index', ['tab' => 'tables']) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold transition shadow-lg shadow-teal-600/30">
                    <i class="fi fi-rr-settings text-sm"></i>
                    <span>Masa & Salon Ayarlarına Git</span>
                </a>
            </div>
        @else
            <div id="tablesGrid" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                @foreach ($tables as $table)
                    @php
                        $statusKey = is_object($table->status) ? $table->status->value : ($table->status ?? 'available');
                        $activeCheck = $table->checks->first();
                        $hallSlug = Str::slug($table->hall?->name ?: 'salonsuz-alan');

                        $cardStyle = match($statusKey) {
                            'occupied' => 'bg-gradient-to-br from-indigo-950 via-[#15192e] to-slate-900 border-indigo-500/60 text-white hover:border-indigo-400 shadow-indigo-900/30',
                            'available' => 'bg-[#131625] border-slate-800/80 text-slate-200 hover:border-emerald-500/50 hover:bg-[#161a2b]',
                            'reserved' => 'bg-gradient-to-br from-rose-950/80 to-slate-900 border-rose-500/50 text-white hover:border-rose-400',
                            'awaiting_payment' => 'bg-gradient-to-br from-amber-950/90 via-[#261f14] to-slate-900 border-amber-500/60 text-white hover:border-amber-400 animate-pulse',
                            default => 'bg-slate-900 border-slate-800 text-slate-400',
                        };

                        $badgeStyle = match($statusKey) {
                            'occupied' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                            'available' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                            'reserved' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                            'awaiting_payment' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                            default => 'bg-slate-800 text-slate-400',
                        };
                    @endphp

                    <div class="table-card group relative flex flex-col justify-between p-4 rounded-3xl border shadow-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl {{ $cardStyle }}"
                         data-hall="hall-{{ $hallSlug }}"
                         data-name="{{ Str::lower($table->name) }}"
                         data-code="{{ Str::lower($table->code) }}">
                        
                        <!-- Header: Status & Capacity -->
                        <div class="flex items-center justify-between gap-1">
                            <span class="px-2.5 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-wider border {{ $badgeStyle }}">
                                @if ($statusKey === 'occupied') Dolu
                                @elseif ($statusKey === 'available') Boş
                                @elseif ($statusKey === 'reserved') Rezerve
                                @elseif ($statusKey === 'awaiting_payment') Hesap Bekliyor
                                @else Pasif @endif
                            </span>
                            <span class="text-[10px] font-semibold text-slate-400 flex items-center gap-1">
                                <i class="fi fi-rr-users text-[10px]"></i> {{ $table->capacity }}
                            </span>
                        </div>

                        <!-- Center: Table Name & Hall Name -->
                        <a href="{{ route('tables.show', $table) }}" class="my-4 text-center block">
                            <h3 class="text-xl sm:text-2xl font-black tracking-tight group-hover:scale-105 transition-transform text-white">
                                {{ $table->name }}
                            </h3>
                            <span class="text-[10px] font-bold text-slate-500 block mt-0.5 uppercase tracking-wider">
                                {{ $table->hall?->name ?: 'Salonsuz' }}
                            </span>
                        </a>

                        <!-- Footer: Active Check Summary -->
                        <a href="{{ route('tables.show', $table) }}" class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                            @if ($activeCheck)
                                <div>
                                    <div class="text-[10px] font-semibold text-slate-400">{{ $activeCheck->items_count ?? 0 }} Kalem</div>
                                    <div class="text-sm font-extrabold text-white">₺{{ number_format($activeCheck->total, 2) }}</div>
                                </div>
                                <div class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-xs group-hover:bg-indigo-500 group-hover:text-white transition">
                                    <i class="fi fi-rr-angle-right"></i>
                                </div>
                            @else
                                <div class="text-[11px] font-bold text-slate-500">Adisyon Aç</div>
                                <div class="w-7 h-7 rounded-lg bg-slate-800 text-slate-400 flex items-center justify-center text-xs group-hover:bg-emerald-600 group-hover:text-white transition">
                                    <i class="fi fi-rr-plus"></i>
                                </div>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

    </main>
</div>

<script>
    function filterHall(hallSlug) {
        const buttons = document.querySelectorAll('.hall-filter-btn');
        buttons.forEach(btn => {
            if (btn.getAttribute('data-hall') === hallSlug) {
                btn.className = "hall-filter-btn px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all bg-indigo-600 text-white shadow-lg shadow-indigo-600/30";
            } else {
                btn.className = "hall-filter-btn px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all bg-slate-900 text-slate-400 hover:text-white border border-slate-800";
            }
        });

        const cards = document.querySelectorAll('.table-card');
        cards.forEach(card => {
            if (hallSlug === 'all' || card.getAttribute('data-hall') === hallSlug) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function filterTablesBySearch() {
        const query = document.getElementById('tableSearchInput').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.table-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name') || '';
            const code = card.getAttribute('data-code') || '';

            if (name.includes(query) || code.includes(query)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }
</script>
@endsection
