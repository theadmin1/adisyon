@extends('layouts.app')

@section('title', 'Restoran Kontrol Paneli - Adisyon Sistem Portalı')

@section('content')
<div class="min-h-screen flex flex-col bg-slate-950">
    <!-- Top Navigation Bar -->
    <header class="glass-panel sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center">
                <i class="fi fi-rr-shop text-xl text-indigo-400"></i>
            </div>
            <div>
                <h1 class="font-bold text-lg text-white leading-none">Adisyon Portalı</h1>
                <span class="text-xs text-slate-400">Restoran & Kasa Kontrol Paneli</span>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <!-- Active Staff Profile Badge (Netflix-style) -->
            @if(session('active_staff_name'))
                <div class="flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-indigo-950/80 border border-indigo-500/40 text-xs">
                    <span class="text-base">
                        @if(session('active_staff_role') === 'Kasa') 💳
                        @elseif(session('active_staff_role') === 'Mutfak') 👨‍🍳
                        @elseif(session('active_staff_role') === 'Kaptan') 👔
                        @else 🍷
                        @endif
                    </span>
                    <div class="text-left">
                        <div class="font-bold text-white">{{ session('active_staff_name') }}</div>
                        <div class="text-[10px] font-semibold text-indigo-300 uppercase tracking-wider">{{ session('active_staff_role') }}</div>
                    </div>
                </div>

                <a href="{{ route('staff.switch') }}" class="px-3.5 py-2 rounded-xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 hover:bg-indigo-600/30 text-xs font-bold transition-all flex items-center gap-1.5">
                    <span>🔄 Profil Değiştir</span>
                </a>
            @endif

            <!-- Logout Button -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 hover:border-red-500/30 text-xs font-semibold transition-all flex items-center gap-2">
                    <i class="fi fi-rr-exit text-sm"></i>
                    <span>Çıkış Yap</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 lg:p-8 max-w-7xl w-full mx-auto space-y-8">

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-3xl p-6 lg:p-8 border border-indigo-500/20 bg-gradient-to-r from-indigo-950/80 via-slate-900/90 to-purple-950/80 shadow-2xl">
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Sistem Aktif & Çevrimiçi</span>
                </div>
                
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white">
                    Merhaba, <span class="gradient-text">{{ $user->name }}</span>! 👋
                </h2>
                
                <p class="text-slate-300 text-xs sm:text-sm max-w-2xl">
                    Adisyon Restoran Otomasyon Sistemine hoş geldiniz. İşlem yapmak istediğiniz kategoriyi veya yönetim modülünü seçiniz.
                </p>
            </div>
        </div>

        <!-- RESTORAN VE KASA KATEGORİ IZGARASI -->
        <section class="flex flex-col p-2">
            <div class="grid flex-1 justify-items-center gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4">
                
                <!-- 1. Masalar -->
                <a href="#masalar" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-pink-100 via-rose-200 to-fuchsia-400 text-[#321347] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-room-service text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Masalar</p>
                </a>

                <!-- 2. Hızlı Satış -->
                <a href="#hizli-satis" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-amber-300 via-rose-300 to-pink-400 text-[#3a1b4f] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-bolt text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Hızlı Satış</p>
                </a>

                <!-- 3. Online Sipariş -->
                <a href="#online-siparis" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-sky-200 via-fuchsia-200 to-pink-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-shopping-cart text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Online Sipariş</p>
                </a>

                <!-- 4. Ayarlar -->
                <a href="#ayarlar" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-amber-300 via-rose-300 to-pink-400 text-[#3a1b4f] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-settings text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Ayarlar</p>
                </a>

                <!-- 5. Mutfak -->
                <a href="#mutfak" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-emerald-200 via-pink-200 to-rose-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-restaurant text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Mutfak</p>
                </a>

                <!-- 6. Kasa -->
                <a href="#kasa" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-violet-200 via-fuchsia-200 to-pink-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-cash-register text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Kasa</p>
                </a>

                <!-- 7. Ürünler -->
                <a href="#urunler" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-pink-100 via-rose-200 to-fuchsia-400 text-[#321347] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-box-open text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Ürünler</p>
                </a>

                <!-- 8. Kategoriler -->
                <a href="#kategoriler" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-emerald-200 via-pink-200 to-rose-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-apps text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Kategoriler</p>
                </a>

                <!-- 9. Şubeler -->
                <a href="#subeler" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-sky-200 via-fuchsia-200 to-pink-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-shop text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Şubeler</p>
                </a>

                <!-- 10. Salonlar -->
                <a href="#salonlar" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-violet-200 via-fuchsia-200 to-pink-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-objects-column text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Salonlar</p>
                </a>

                <!-- 11. Kullanıcılar -->
                <a href="#kullanicilar" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-amber-300 via-rose-300 to-pink-400 text-[#3a1b4f] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-users text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Kullanıcılar</p>
                </a>

                <!-- 12. Raporlar -->
                <a href="#raporlar" class="group flex aspect-square w-full max-w-[270px] flex-col items-center justify-center rounded-[34px] border border-white/7 bg-[linear-gradient(180deg,_rgba(24,18,56,0.76),_rgba(24,18,56,0.54))] p-6 shadow-[0_22px_52px_rgba(10,8,30,0.2)] transition duration-200 hover:-translate-y-1 hover:border-white/14 hover:bg-[linear-gradient(180deg,_rgba(30,22,68,0.88),_rgba(26,20,60,0.7))]">
                    <span class="flex h-20 w-20 items-center justify-center rounded-[26px] bg-gradient-to-br from-sky-200 via-fuchsia-200 to-pink-400 text-[#311445] shadow-[0_20px_36px_rgba(10,8,30,0.22)] sm:h-24 sm:w-24">
                        <i class="fi fi-rr-chart-pie-alt text-4xl sm:text-5xl mt-2"></i>
                    </span>
                    <p class="mt-6 text-2xl sm:text-3xl font-semibold tracking-tight text-white">Raporlar</p>
                </a>

            </div>

            <div class="mt-8 flex items-center justify-between border-t border-white/10 pt-5">
                <p class="text-sm text-white/40 font-mono">Adisyon Sistem v1.0</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/10 bg-white/8 text-white transition hover:bg-white/14" title="Çıkış">
                        <i class="fi fi-rr-exit text-white text-sm mt-0.5"></i>
                    </button>
                </form>
            </div>
        </section>

        <!-- Tables Overview Section -->
        <div id="masalar" class="glass-panel p-6 rounded-3xl space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-white">Masa Canlı Durumu</h3>
                    <p class="text-xs text-slate-400">Restorandaki masaların anlık adisyon ve süre durumları</p>
                </div>
                <button class="px-4 py-2 rounded-xl gradient-btn text-white text-xs font-semibold flex items-center gap-2">
                    <i class="fi fi-rr-plus text-xs"></i>
                    <span>Yeni Masa Ekle</span>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($tables as $table)
                    <div class="glass-card p-5 rounded-2xl border border-slate-800/80 hover:border-slate-700 transition-all">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-bold text-white text-base">{{ $table['name'] }}</h4>
                            @if ($table['status'] === 'busy')
                                <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-semibold">Dolu</span>
                            @elseif ($table['status'] === 'reserved')
                                <span class="px-2.5 py-1 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold">Rezerve</span>
                            @else
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold">Boş</span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-400 pt-2 border-t border-slate-800/60">
                            <span>Tutar: <strong class="text-white">{{ $table['total'] }}</strong></span>
                            <span>Süre: <span class="text-slate-300">{{ $table['time'] }}</span></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </main>
</div>
@endsection
