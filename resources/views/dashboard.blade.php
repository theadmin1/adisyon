@extends('layouts.app')

@section('title', 'Restoran Kontrol Paneli - Adisyon POS')

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0d17] text-slate-100 selection:bg-indigo-500 selection:text-white">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-[#121424]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3 flex items-center justify-between shadow-2xl">
        <!-- Logo & Subtitle -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 p-0.5 shadow-lg shadow-indigo-500/30">
                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                    <i class="fi fi-rr-shop text-xl text-indigo-400"></i>
                </div>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-extrabold text-xl tracking-wider text-white">ADISYON <span class="text-indigo-400 font-serif italic">Pos</span></h1>
                    <span class="px-2 py-0.5 rounded-md bg-indigo-500/20 border border-indigo-500/30 text-[10px] font-bold text-indigo-300 tracking-widest uppercase">RESTORAN PANELİ</span>
                </div>
            </div>
        </div>

        <!-- Status Badges & User Info -->
        <div class="flex items-center gap-3">
            <!-- Internet Status -->
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-[10px] text-emerald-500 uppercase font-mono tracking-wider">İNTERNET</span>
                <span>Bağlı</span>
            </div>

            <!-- Server Status -->
            <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-[10px] text-emerald-500 uppercase font-mono tracking-wider">SERVER</span>
                <span>Bağlı</span>
            </div>

            <!-- Active Branch Badge -->
            <div class="hidden lg:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-indigo-950/60 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">
                <i class="fi fi-rr-shop text-xs text-indigo-400"></i>
                <span class="text-[10px] text-indigo-400 uppercase font-mono tracking-wider">ŞUBE</span>
                <span>{{ $user->branch->name ?? 'Tüm Şubeler' }}</span>
            </div>

            <!-- Active Staff Profile Badge -->
            @if(session('active_staff_name'))
                <div class="flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-purple-950/80 border border-purple-500/40 text-xs shadow-lg">
                    <span class="text-base">
                        @if(session('active_staff_role') === 'Kasa') 💳
                        @elseif(session('active_staff_role') === 'Mutfak') 👨‍🍳
                        @elseif(session('active_staff_role') === 'Kaptan') 👔
                        @else 🍷
                        @endif
                    </span>
                    <div class="text-left">
                        <div class="font-bold text-white leading-tight">{{ session('active_staff_name') }}</div>
                        <div class="text-[9px] font-bold text-purple-300 uppercase tracking-wider">{{ session('active_staff_role') }}</div>
                    </div>
                </div>

                <a href="{{ route('staff.switch') }}" title="Profil Değiştir" class="p-2 rounded-xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-600/40 text-xs transition-all flex items-center gap-1">
                    <span>🔄</span>
                    <span class="text-[11px] font-semibold hidden sm:inline">Değiştir</span>
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
                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500/20 text-xs font-semibold transition-all flex items-center gap-1.5" title="Çıkış Yap">
                    <i class="fi fi-rr-exit text-xs"></i>
                    <span class="hidden sm:inline">Çıkış</span>
                </button>
            </form>
        </div>
    </header>

    <!-- MAIN CONTAINER (LAYOUT WITH LEFT SIDEBAR + 4-COL GRID) -->
    <main class="flex-1 p-4 lg:p-8 max-w-[1600px] w-full mx-auto">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- SOL YAN BİLGİ PANELİ (Tarih, Canlı Saat & Bildirimler) -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Clock & Date Box -->
                <div class="relative overflow-hidden rounded-3xl p-6 border border-pink-500/30 bg-gradient-to-b from-rose-950/80 via-purple-950/70 to-slate-950/90 shadow-2xl space-y-4">
                    <div class="absolute -right-16 -top-16 w-48 h-48 bg-rose-500/20 rounded-full blur-3xl pointer-events-none"></div>
                    
                    <div class="text-slate-300 text-sm font-semibold tracking-wide" id="liveDateStr">
                        -- Temmuz Perşembe
                    </div>

                    <div class="text-5xl sm:text-6xl font-black tracking-tight text-white font-mono gradient-text" id="liveClockStr">
                        15:36:00
                    </div>

                    <div class="pt-4 border-t border-white/10 space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-400 tracking-wider uppercase">BİLDİRİMLER</span>
                            <span class="px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-400 font-bold text-[10px]">0</span>
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-900/60 border border-slate-800 text-center text-xs text-slate-400 font-medium">
                            <i class="fi fi-rr-bell text-slate-500 text-lg block mb-1"></i>
                            Bildirim bulunmuyor.
                        </div>
                    </div>
                </div>

                <!-- Active Role Information Card -->
                <div class="glass-panel p-5 rounded-3xl space-y-3 border border-indigo-500/20 bg-indigo-950/30">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-indigo-300 uppercase tracking-wider">AKTİF PERSONEL ROLÜ</span>
                        <span class="px-2 py-0.5 rounded-md bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 font-bold text-[11px]">{{ $staffRole }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Sayfada sadece <strong>{{ $staffRole }}</strong> yetkisine açık modül ve kategoriler görüntülenmektedir.
                    </p>
                </div>
            </div>

            <!-- SAĞ TARAF (4-KOLON KATEGORİ IZGARASI - ROLE BAZLI FİLTRELİ) -->
            <div class="lg:col-span-9">
                <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5 sm:gap-6">
                    
                    <!-- 1. Masalar -->
                    @if(in_array('masalar', $allowedCategories))
                        <a href="#masalar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-pink-500/50 hover:bg-slate-900/95 hover:shadow-pink-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-pink-400 via-rose-500 to-fuchsia-600 text-white shadow-lg shadow-pink-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-room-service text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-pink-300 transition-colors">Masalar</span>
                        </a>
                    @endif

                    <!-- 2. Hızlı Satış -->
                    @if(in_array('hizli-satis', $allowedCategories))
                        <a href="#hizli-satis" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-amber-500/50 hover:bg-slate-900/95 hover:shadow-amber-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 via-rose-400 to-pink-500 text-white shadow-lg shadow-amber-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-bolt text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-amber-300 transition-colors">Hızlı Satış</span>
                        </a>
                    @endif

                    <!-- 3. Online Sipariş -->
                    @if(in_array('online-siparis', $allowedCategories))
                        <a href="#online-siparis" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-sky-500/50 hover:bg-slate-900/95 hover:shadow-sky-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-400 via-indigo-500 to-purple-600 text-white shadow-lg shadow-sky-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-shopping-cart text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-sky-300 transition-colors">Online Sipariş</span>
                        </a>
                    @endif

                    <!-- 4. Mutfak -->
                    @if(in_array('mutfak', $allowedCategories))
                        <a href="#mutfak" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-emerald-500/50 hover:bg-slate-900/95 hover:shadow-emerald-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 via-teal-500 to-cyan-600 text-white shadow-lg shadow-emerald-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-restaurant text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-emerald-300 transition-colors">Mutfak</span>
                        </a>
                    @endif

                    <!-- 5. Kasa -->
                    @if(in_array('kasa', $allowedCategories))
                        <a href="#kasa" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-violet-500/50 hover:bg-slate-900/95 hover:shadow-violet-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-400 via-purple-500 to-indigo-600 text-white shadow-lg shadow-violet-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-cash-register text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-violet-300 transition-colors">Kasa</span>
                        </a>
                    @endif

                    <!-- 6. Ürünler -->
                    @if(in_array('urunler', $allowedCategories))
                        <a href="#urunler" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-rose-500/50 hover:bg-slate-900/95 hover:shadow-rose-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-400 via-pink-500 to-purple-600 text-white shadow-lg shadow-rose-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-box-open text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-rose-300 transition-colors">Ürünler</span>
                        </a>
                    @endif

                    <!-- 7. Kategoriler -->
                    @if(in_array('kategoriler', $allowedCategories))
                        <a href="#kategoriler" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-emerald-500/50 hover:bg-slate-900/95 hover:shadow-emerald-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-400 via-emerald-500 to-green-600 text-white shadow-lg shadow-emerald-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-apps text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-emerald-300 transition-colors">Kategoriler</span>
                        </a>
                    @endif

                    <!-- 8. Salonlar -->
                    @if(in_array('salonlar', $allowedCategories))
                        <a href="#salonlar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-cyan-500/50 hover:bg-slate-900/95 hover:shadow-cyan-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 via-sky-500 to-indigo-600 text-white shadow-lg shadow-cyan-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-objects-column text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-cyan-300 transition-colors">Salonlar</span>
                        </a>
                    @endif

                    <!-- 9. Ayarlar -->
                    @if(in_array('ayarlar', $allowedCategories))
                        <a href="#ayarlar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-purple-500/50 hover:bg-slate-900/95 hover:shadow-purple-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-400 via-fuchsia-500 to-rose-500 text-white shadow-lg shadow-purple-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-settings text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-purple-300 transition-colors">Ayarlar</span>
                        </a>
                    @endif

                    <!-- 10. Şubeler -->
                    @if(in_array('subeler', $allowedCategories))
                        <a href="#subeler" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-blue-500/50 hover:bg-slate-900/95 hover:shadow-blue-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-400 via-indigo-500 to-sky-600 text-white shadow-lg shadow-blue-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-shop text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-blue-300 transition-colors">Şubeler</span>
                        </a>
                    @endif

                    <!-- 11. Kullanıcılar -->
                    @if(in_array('kullanicilar', $allowedCategories))
                        <a href="#kullanicilar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-orange-500/50 hover:bg-slate-900/95 hover:shadow-orange-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-400 via-amber-500 to-rose-600 text-white shadow-lg shadow-orange-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-users text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-orange-300 transition-colors">Kullanıcılar</span>
                        </a>
                    @endif

                    <!-- 12. Raporlar -->
                    @if(in_array('raporlar', $allowedCategories))
                        <a href="#raporlar" class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/80 bg-slate-900/70 p-6 shadow-2xl transition-all duration-300 hover:-translate-y-1.5 hover:border-fuchsia-500/50 hover:bg-slate-900/95 hover:shadow-fuchsia-500/20 cursor-pointer">
                            <div class="flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-2xl bg-gradient-to-br from-fuchsia-400 via-purple-500 to-pink-600 text-white shadow-lg shadow-fuchsia-500/30 group-hover:scale-110 transition-transform duration-300">
                                <i class="fi fi-rr-chart-pie-alt text-4xl sm:text-5xl mt-1"></i>
                            </div>
                            <span class="mt-5 text-xl sm:text-2xl font-bold tracking-tight text-white group-hover:text-fuchsia-300 transition-colors">Raporlar</span>
                        </a>
                    @endif

                </div>
            </div>

        </div>

    </main>

    <!-- LIVE CLOCK SCRIPT -->
    <script>
        function updateLiveClock() {
            const now = new Date();
            
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const clockEl = document.getElementById('liveClockStr');
            if (clockEl) {
                clockEl.textContent = `${hours}:${minutes}:${seconds}`;
            }

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
