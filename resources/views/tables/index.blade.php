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

            <button onclick="openModal('addHallModal')" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 hover:text-white text-xs font-bold transition-all flex items-center gap-2">
                <i class="fi fi-rr-apps text-indigo-400"></i>
                <span class="hidden sm:inline">+ Yeni Salon</span>
            </button>

            <button onclick="openModal('addTableModal')" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-extrabold transition-all shadow-lg shadow-indigo-600/30 flex items-center gap-2">
                <i class="fi fi-rr-plus text-sm"></i>
                <span>+ Yeni Masa Ekle</span>
            </button>
        </div>
    </header>

    <!-- MAIN BODY CONTENT -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 space-y-6">

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

        <!-- STATS OVERVIEW CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Toplam Masa</div>
                    <div class="text-2xl font-extrabold text-white mt-1">{{ $stats['total_tables'] ?? 0 }}</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700/80 flex items-center justify-center text-slate-300">
                    <i class="fi fi-rr-layout-fluid text-lg"></i>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Boş Masalar</div>
                    <div class="text-2xl font-extrabold text-emerald-400 mt-1">{{ $stats['available_tables'] ?? 0 }}</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i class="fi fi-rr-check-circle text-lg"></i>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Dolu Masalar</div>
                    <div class="text-2xl font-extrabold text-indigo-400 mt-1">{{ $stats['occupied_tables'] ?? 0 }}</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <i class="fi fi-rr-users text-lg"></i>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Hesap Bekleyen</div>
                    <div class="text-2xl font-extrabold text-amber-400 mt-1">{{ $stats['awaiting_tables'] ?? 0 }}</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                    <i class="fi fi-rr-time-fast text-lg"></i>
                </div>
            </div>

            <div class="col-span-2 md:col-span-1 p-4 rounded-2xl bg-[#131625] border border-slate-800/80 flex items-center justify-between shadow-xl">
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Açık Adisyon</div>
                    <div class="text-2xl font-extrabold text-rose-400 mt-1">₺{{ $stats['open_revenue'] ?? '0.00' }}</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                    <i class="fi fi-rr-receipt text-lg"></i>
                </div>
            </div>
        </div>

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
                <button onclick="openModal('addTableModal')" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-lg shadow-indigo-600/30">
                    + İlk Masayı Ekle
                </button>
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

<!-- MODAL 1: YENİ MASA EKLE -->
<div id="addTableModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#131625] border border-slate-800 rounded-2xl w-full max-w-lg p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3.5">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fi fi-rr-plus text-indigo-400"></i>
                <span>Yeni Masa Ekle</span>
            </h3>
            <button onclick="closeModal('addTableModal')" class="text-slate-400 hover:text-white">
                <i class="fi fi-rr-cross text-sm"></i>
            </button>
        </div>

        <form action="{{ route('tables.store') }}" method="POST" class="space-y-4 text-xs">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Salon / Bölüm Seçin</label>
                    <select name="hall_id" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
                        <option value="">Salonsuz Alan</option>
                        @foreach ($halls as $hall)
                            <option value="{{ $hall->id }}">{{ $hall->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Masa Adı</label>
                    <input type="text" name="name" required placeholder="Örn: Masa 1" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Masa Kodu</label>
                    <input type="text" name="code" required placeholder="Örn: M1" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1">Masa Kapasitesi (Kişi)</label>
                    <input type="number" name="capacity" min="1" value="4" required class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div class="col-span-2">
                    <label class="block font-bold text-slate-300 mb-1">Not / Açıklama</label>
                    <textarea name="notes" rows="2" placeholder="Cam kenarı, özel bölüm vb..." class="w-full bg-slate-900 border border-slate-700/80 rounded-xl p-3 text-white focus:border-indigo-500 focus:outline-none transition"></textarea>
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('addTableModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition">
                    Masayı Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 2: YENİ SALON EKLE -->
<div id="addHallModal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-[#131625] border border-slate-800 rounded-2xl w-full max-w-md p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3.5">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fi fi-rr-apps text-indigo-400"></i>
                <span>Yeni Salon / Bölüm Ekle</span>
            </h3>
            <button onclick="closeModal('addHallModal')" class="text-slate-400 hover:text-white">
                <i class="fi fi-rr-cross text-sm"></i>
            </button>
        </div>

        <form action="{{ route('halls.store') }}" method="POST" class="space-y-4 text-xs">
            @csrf

            <div>
                <label class="block font-bold text-slate-300 mb-1">Salon / Bölüm Adı</label>
                <input type="text" name="name" required placeholder="Örn: Teras, Bahçe, VIP Salon" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>

            <div>
                <label class="block font-bold text-slate-300 mb-1">Salon Kodu (Opsiyonel)</label>
                <input type="text" name="code" placeholder="Örn: TRS, BHÇ" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-3.5 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('addHallModal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition">
                    İptal
                </button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition">
                    Salonu Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

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
