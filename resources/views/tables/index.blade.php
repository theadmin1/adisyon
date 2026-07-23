@extends('layouts.app')

@section('title', 'Masa Planı & Salon Yönetimi - Adisyon POS')

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans antialiased">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-[#121522]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3 flex items-center justify-between shadow-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="w-10 h-10 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center hover:bg-indigo-600/40 transition">
                <i class="fi fi-rr-arrow-left text-lg text-indigo-400"></i>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-extrabold text-lg tracking-wider text-white">MASA PLANLAMA</h1>
                    <span class="px-2 py-0.5 rounded-md bg-indigo-500/10 border border-indigo-500/20 text-[10px] font-semibold text-indigo-300 uppercase">POS</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if(session('active_staff_name'))
                <div class="flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-indigo-950/60 border border-indigo-500/30 text-xs">
                    <span class="font-bold text-white">{{ session('active_staff_name') }}</span>
                    <span class="text-[10px] font-bold text-indigo-300 uppercase">({{ session('active_staff_role') }})</span>
                </div>
            @endif
            <a href="{{ route('dashboard') }}" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 text-xs font-semibold transition">
                Ana Sayfa
            </a>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 p-5 lg:p-8 max-w-[1600px] w-full mx-auto space-y-6">

        @if(session('status'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-semibold flex items-center gap-2">
                <i class="fi fi-rr-check-circle text-lg"></i>
                {{ session('status') }}
            </div>
        @endif

        @if(isset($errors) && $errors->any())
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm font-semibold space-y-1">
                @foreach($errors->all() as $error)
                    <div><i class="fi fi-rr-cross-circle mr-1"></i> {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <!-- TOP BAR: Title & Filters -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-4">
            <div>
                <h2 class="text-2xl font-bold text-white tracking-wide">Masa & Salon Düzeni</h2>
                <p class="text-xs text-slate-400 mt-0.5">Adisyon açmak veya masayı görüntülemek için masaya tıklayınız.</p>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-3">
                <select name="status" class="h-10 rounded-xl border border-slate-800 bg-[#141724] px-4 text-xs font-semibold text-slate-300 outline-none focus:border-indigo-500">
                    <option value="">Tüm Durumlar</option>
                    <option value="available" @selected(request('status') === 'available')>Boş</option>
                    <option value="occupied" @selected(request('status') === 'occupied')>Dolu</option>
                    <option value="awaiting_payment" @selected(request('status') === 'awaiting_payment')>Hesap Bekleyen</option>
                    <option value="reserved" @selected(request('status') === 'reserved')>Rezerve</option>
                </select>

                <button class="h-10 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-bold text-xs text-white transition shadow-lg shadow-indigo-600/20">
                    Filtrele
                </button>
            </form>
        </div>

        <!-- HALL TABS & TABLES GRID -->
        <div class="space-y-6">
            @if($groupedTables->count() > 0)
                <!-- Hall Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto p-1.5 rounded-2xl bg-[#141724]/90 border border-slate-800/80">
                    @foreach ($groupedTables as $hallName => $hallTables)
                        <button type="button" 
                                onclick="switchHallTab('hall-{{ Str::slug($hallName) }}')" 
                                class="hall-tab whitespace-nowrap rounded-xl px-5 py-2.5 text-xs font-extrabold transition-all duration-200 {{ $loop->first ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/50' }}"
                                data-target="hall-{{ Str::slug($hallName) }}">
                            {{ $hallName }}
                            <span class="ml-2 px-2 py-0.5 rounded-full {{ $loop->first ? 'bg-indigo-900/60 text-indigo-200' : 'bg-slate-800 text-slate-400' }} text-[10px] font-bold">
                                {{ $hallTables->count() }}
                            </span>
                        </button>
                    @endforeach
                </div>

                <!-- Tab Contents -->
                <div class="relative min-h-[50vh]">
                    @foreach ($groupedTables as $hallName => $hallTables)
                        <div id="hall-{{ Str::slug($hallName) }}" class="hall-content {{ $loop->first ? 'block' : 'hidden' }}">
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-4">
                                @foreach ($hallTables as $table)
                                    @php
                                        $statusKey = is_object($table->status) ? $table->status->value : ($table->status ?? 'available');
                                        $activeCheck = $table->checks->first();

                                        $cardStyle = match($statusKey) {
                                            'occupied' => 'bg-gradient-to-br from-indigo-900/80 to-indigo-950 border-indigo-500/50 text-white hover:border-indigo-400 shadow-indigo-900/20',
                                            'available' => 'bg-[#141724]/90 border-slate-800 text-slate-200 hover:border-indigo-500/50 hover:bg-[#191d2d]',
                                            'reserved' => 'bg-gradient-to-br from-rose-950/80 to-slate-900 border-rose-500/40 text-white hover:border-rose-400',
                                            'awaiting_payment' => 'bg-gradient-to-br from-amber-950/80 to-slate-900 border-amber-500/40 text-white hover:border-amber-400',
                                            default => 'bg-slate-900 border-slate-800 text-slate-400',
                                        };

                                        $badgeBg = match($statusKey) {
                                            'occupied' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                                            'available' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                            'reserved' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                            'awaiting_payment' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                            default => 'bg-slate-800 text-slate-400',
                                        };
                                    @endphp

                                    <a href="{{ route('tables.show', $table) }}"
                                       class="group relative flex aspect-square flex-col items-center justify-between p-4 rounded-3xl border shadow-xl transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl cursor-pointer {{ $cardStyle }}">
                                        
                                        <!-- Top Status Badge -->
                                        <div class="w-full flex items-center justify-between">
                                            <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider border {{ $badgeBg }}">
                                                @if ($statusKey === 'occupied') Dolu
                                                @elseif ($statusKey === 'available') Boş
                                                @elseif ($statusKey === 'reserved') Rezerve
                                                @elseif ($statusKey === 'awaiting_payment') Hesap Bekliyor
                                                @else Pasif @endif
                                            </span>
                                            <span class="text-[10px] font-semibold text-slate-400">
                                                <i class="fi fi-rr-users text-xs"></i> {{ $table->capacity }}
                                            </span>
                                        </div>

                                        <!-- Center Table Name -->
                                        <div class="text-center my-auto">
                                            <span class="text-xl sm:text-2xl font-black tracking-tight block group-hover:scale-105 transition-transform">
                                                {{ $table->name }}
                                            </span>
                                        </div>

                                        <!-- Bottom Price or Occupant info -->
                                        <div class="w-full text-center pt-2 border-t border-white/5">
                                            @if ($activeCheck)
                                                <div class="text-[10px] font-medium text-indigo-300">
                                                    {{ $activeCheck->items_count ?? 0 }} Kalem Ürün
                                                </div>
                                                <div class="text-sm font-black text-white">
                                                    ₺{{ number_format($activeCheck->total, 2) }}
                                                </div>
                                            @else
                                                <div class="text-[11px] font-bold text-slate-400">
                                                    Adisyon Yok
                                                </div>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-16 text-center text-slate-500 bg-[#141724]/50 border border-slate-800 rounded-3xl">
                    <i class="fi fi-rr-room-service text-4xl block mb-2 opacity-40"></i>
                    <p class="text-base font-bold">Kayıtlı masa bulunamadı.</p>
                </div>
            @endif
        </div>

        <!-- MASA & SALON YÖNETİMİ ACCORDION -->
        <details class="rounded-3xl bg-[#141724] border border-slate-800/80 shadow-xl overflow-hidden group">
            <summary class="flex cursor-pointer list-none items-center justify-between p-6 hover:bg-slate-900/50 transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                        <i class="fi fi-rr-settings-sliders text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Masa Yönetimi</h3>
                        <p class="text-xs text-slate-400">Yeni masa ekle veya mevcut masaları düzenle</p>
                    </div>
                </div>
                <i class="fi fi-rr-angle-small-down text-xl text-slate-400 group-open:rotate-180 transition-transform"></i>
            </summary>

            <div class="grid gap-6 border-t border-slate-800/80 p-6 xl:grid-cols-[360px_1fr]">
                <!-- Masa Ekle Formu -->
                <form method="POST" action="{{ route('tables.store') }}" class="space-y-4 bg-slate-900/60 p-5 rounded-2xl border border-slate-800">
                    @csrf
                    <p class="text-xs font-black uppercase tracking-wider text-indigo-400">Yeni Masa Tanımla</p>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Salon / Alan</label>
                        <select name="hall_id" class="w-full rounded-xl border border-slate-800 bg-[#141724] px-4 py-2.5 text-xs font-semibold text-slate-200 outline-none focus:border-indigo-500">
                            <option value="">Salonsuz Alan</option>
                            @foreach ($halls as $hall)
                                <option value="{{ $hall->id }}">{{ $hall->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid gap-3 grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Masa Adı</label>
                            <input name="name" class="w-full rounded-xl border border-slate-800 bg-[#141724] px-4 py-2.5 text-xs font-semibold text-slate-200 outline-none focus:border-indigo-500" placeholder="Örn: Masa 1" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Masa Kodu</label>
                            <input name="code" class="w-full rounded-xl border border-slate-800 bg-[#141724] px-4 py-2.5 text-xs font-semibold text-slate-200 outline-none focus:border-indigo-500" placeholder="Örn: M1" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Kapasite</label>
                        <input name="capacity" type="number" min="1" value="4" class="w-full rounded-xl border border-slate-800 bg-[#141724] px-4 py-2.5 text-xs font-semibold text-slate-200 outline-none focus:border-indigo-500" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Not (Opsiyonel)</label>
                        <textarea name="notes" class="w-full h-16 rounded-xl border border-slate-800 bg-[#141724] p-3 text-xs font-medium text-slate-200 outline-none focus:border-indigo-500" placeholder="Masa notu..."></textarea>
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-bold text-xs text-white transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                        <i class="fi fi-rr-disk text-sm"></i> Masa Kaydet
                    </button>
                </form>

                <!-- Masa Listesi Tablosu -->
                <div class="overflow-x-auto bg-slate-900/40 rounded-2xl border border-slate-800 p-4">
                    <table class="w-full text-xs text-left text-slate-300">
                        <thead class="bg-slate-800/80 text-[10px] font-black uppercase text-slate-400">
                            <tr>
                                <th class="px-4 py-3 rounded-l-xl">Masa</th>
                                <th class="px-4 py-3">Salon</th>
                                <th class="px-4 py-3 text-center">Kapasite</th>
                                <th class="px-4 py-3">Durum</th>
                                <th class="px-4 py-3 text-right rounded-r-xl">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            @foreach ($tables as $managedTable)
                                <tr class="hover:bg-slate-800/30 transition">
                                    <td class="px-4 py-3 font-bold text-white">
                                        {{ $managedTable->name }}
                                        <span class="block text-[10px] font-medium text-slate-500">{{ $managedTable->code }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-slate-400">{{ $managedTable->hall?->name ?: 'Salonsuz' }}</td>
                                    <td class="px-4 py-3 text-center font-bold text-indigo-300">{{ $managedTable->capacity }} Person</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-300">
                                            {{ is_object($managedTable->status) ? $managedTable->status->label() : $managedTable->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('tables.destroy', $managedTable) }}" onsubmit="return confirm('Masayı silmek istediğinize emin misiniz?')" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-[11px] font-bold transition">
                                                Sil
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    </main>
</div>

@endsection

@section('scripts')
<script>
    function switchHallTab(targetId) {
        document.querySelectorAll('.hall-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.hall-tab').forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-lg', 'shadow-indigo-600/30');
            btn.classList.add('text-slate-400', 'hover:text-white');
        });

        const selectedContent = document.getElementById(targetId);
        if (selectedContent) selectedContent.classList.remove('hidden');

        const activeBtn = document.querySelector(`[data-target="${targetId}"]`);
        if (activeBtn) {
            activeBtn.classList.remove('text-slate-400', 'hover:text-white');
            activeBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-lg', 'shadow-indigo-600/30');
        }
    }
</script>
@endsection
