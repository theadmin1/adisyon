@extends('layouts.app')

@section('title', 'Restoran Kontrol Paneli - Adisyon POS')

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 selection:bg-indigo-500 selection:text-white font-sans antialiased">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-[#121522]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3 flex items-center justify-between shadow-2xl">
        <!-- Logo & Subtitle -->
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center shadow-inner">
                <i class="fi fi-rr-shop text-xl text-indigo-400"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-extrabold text-lg tracking-wider text-white">ADISYON <span class="text-indigo-400 font-serif italic">Pos</span></h1>
                    <span class="px-2 py-0.5 rounded-md bg-indigo-500/10 border border-indigo-500/20 text-[10px] font-semibold text-indigo-300 tracking-wider uppercase">PANEL</span>
                </div>
            </div>
        </div>

        <!-- Center: Integrated Minimalist Clock & Date (NO SECONDS) -->
        <div class="hidden md:flex items-center gap-3 px-4 py-1.5 rounded-2xl bg-slate-900/80 border border-slate-800 text-xs text-slate-300 font-medium">
            <div class="flex items-center gap-1.5">
                <i class="fi fi-rr-calendar text-indigo-400 text-sm"></i>
                <span id="liveDateStr">-- Temmuz Perşembe</span>
            </div>
            <span class="text-slate-600">|</span>
            <div class="flex items-center gap-1.5 font-mono text-sm font-bold text-white tracking-wide">
                <i class="fi fi-rr-clock text-indigo-400 text-xs"></i>
                <span id="liveClockStr">18:54</span>
            </div>
        </div>

        <!-- Right: Status Badges & Active Staff -->
        <div class="flex items-center gap-3">
            <!-- System Badges -->
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-950/40 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Bağlı</span>
            </div>

            <!-- Active Staff Profile Badge -->
            @if(session('active_staff_name'))
                <div class="flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-indigo-950/60 border border-indigo-500/30 text-xs">
                    <span class="text-base">
                        @if(session('active_staff_role') === 'Kasa') 💳
                        @elseif(session('active_staff_role') === 'Mutfak') 👨‍🍳
                        @elseif(session('active_staff_role') === 'Kaptan') 👔
                        @else 🍷
                        @endif
                    </span>
                    <div class="text-left">
                        <div class="font-bold text-white leading-tight">{{ session('active_staff_name') }}</div>
                        <div class="text-[9px] font-bold text-indigo-300 uppercase tracking-wider">{{ session('active_staff_role') }}</div>
                    </div>
                </div>

                <a href="{{ route('staff.switch') }}" title="Profil Değiştir" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-medium transition-all">
                    Değiştir
                </a>
            @else
                <div class="flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs">
                    <i class="fi fi-rr-user text-indigo-400"></i>
                    <span class="font-bold text-white">{{ $user->name }}</span>
                </div>
            @endif

            <!-- Logout Button -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500/20 text-xs font-semibold transition-all flex items-center gap-1.5" title="Çıkış Yap">
                    <i class="fi fi-rr-exit text-xs"></i>
                    <span class="hidden sm:inline">Çıkış</span>
                </button>
            </form>
        </div>
    </header>

    <!-- MAIN DASHBOARD CONTENT -->
    <main class="flex-1 p-5 lg:p-10 max-w-6xl w-full mx-auto space-y-6">

        <!-- MINIMALIST CATEGORY GRID (4 COLUMNS) -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-5 sm:gap-6 pt-2">
            
            <!-- 1. Masalar -->
            @if(in_array('masalar', $allowedCategories))
                <a href="{{ route('tables.index') }}" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-indigo-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:border-indigo-500 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-room-service text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Masalar</span>
                </a>
            @endif

            <!-- 2. Hızlı Satış -->
            @if(in_array('hizli-satis', $allowedCategories))
                <a href="{{ route('quicksale.index') }}" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-amber-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-amber-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 group-hover:bg-amber-500 group-hover:text-white group-hover:border-amber-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-bolt text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Hızlı Satış</span>
                </a>
            @endif

            <!-- 3. Paket Servis -->
            @if(in_array('paket-servis', $allowedCategories))
                <a href="#paket-servis" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-sky-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-sky-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-400 group-hover:bg-sky-500 group-hover:text-white group-hover:border-sky-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-box-alt text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Paket Servis</span>
                </a>
            @endif

            <!-- 4. Mutfak -->
            @if(in_array('mutfak', $allowedCategories))
                <a href="#mutfak" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-emerald-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white group-hover:border-emerald-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-restaurant text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Mutfak</span>
                </a>
            @endif

            <!-- 5. Ürünler -->
            @if(in_array('urunler', $allowedCategories))
                <a href="{{ route('products.index') }}" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-rose-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-rose-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 group-hover:bg-rose-500 group-hover:text-white group-hover:border-rose-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-box-open text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Ürünler</span>
                </a>
            @endif

            <!-- 6. Stoklar -->
            @if(in_array('stoklar', $allowedCategories))
                <a href="#stoklar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-cyan-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-cyan-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 group-hover:bg-cyan-500 group-hover:text-white group-hover:border-cyan-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-boxes text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Stoklar</span>
                </a>
            @endif

            <!-- 7. Raporlar -->
            @if(in_array('raporlar', $allowedCategories))
                <a href="#raporlar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-fuchsia-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-fuchsia-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-fuchsia-500/10 border border-fuchsia-500/20 text-fuchsia-400 group-hover:bg-fuchsia-500 group-hover:text-white group-hover:border-fuchsia-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-chart-pie-alt text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Raporlar</span>
                </a>
            @endif

            <!-- 8. Ayarlar -->
            @if(in_array('ayarlar', $allowedCategories))
                <a href="{{ route('settings.index') }}" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-[#141724]/80 p-5 sm:p-6 shadow-xl transition-all duration-300 hover:-translate-y-1 hover:border-purple-500/50 hover:bg-[#191d2d] hover:shadow-2xl hover:shadow-purple-500/10 cursor-pointer">
                    <div class="flex h-16 w-16 sm:h-20 sm:w-20 items-center justify-center rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 group-hover:bg-purple-500 group-hover:text-white group-hover:border-purple-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                        <i class="fi fi-rr-settings text-3xl sm:text-4xl"></i>
                    </div>
                    <span class="mt-4 text-base sm:text-lg font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Ayarlar</span>
                </a>
            @endif

        </div>

    </main>

    <!-- LIVE CLOCK SCRIPT (HH:MM FORMAT - NO SECONDS) -->
    <script>
        function updateLiveClock() {
            const now = new Date();
            
            // Format time: HH:MM (SANİYESİZ SAAT)
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            
            const timeStr = `${hours}:${minutes}`;

            const clockEl = document.getElementById('liveClockStr');
            if (clockEl) {
                clockEl.textContent = timeStr;
            }

            const mobileClockEl = document.getElementById('mobileLiveClockStr');
            if (mobileClockEl) {
                mobileClockEl.textContent = timeStr;
            }

            // Format Turkish date string (e.g. 23 Temmuz Perşembe)
            const options = { day: 'numeric', month: 'long', weekday: 'long' };
            const dateStr = now.toLocaleDateString('tr-TR', options);
            
            const dateEl = document.getElementById('liveDateStr');
            if (dateEl) {
                dateEl.textContent = dateStr;
            }
        }

        setInterval(updateLiveClock, 1000);
        updateLiveClock();
    </script>

</div>
@endsection
