@extends('layouts.app')

@section('title', 'Restoran Kontrol Paneli - Adisyon POS')

@section('content')
    @php
        $brandOrganization = auth()->user()?->branch?->organizations()->where('organizations.is_active', true)->first();
        $brandLogoUrl = $brandOrganization?->logo_url ?? asset('assets/images/logo.png');
    @endphp
    <div
        class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 selection:bg-indigo-500 selection:text-white font-sans antialiased">

        <!-- TOP HEADER NAVBAR -->
        <header class="bg-transparent px-4 lg:px-8 py-3 flex items-center justify-between">
            <!-- Logo & Subtitle -->
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 hover:opacity-90 transition">
                    <img src="{{ $brandLogoUrl }}" alt="{{ $brandOrganization?->name ?? 'ADİSYON POS' }}"
                        class="h-7 sm:h-8 w-auto object-contain drop-shadow-lg">
                </a>
            </div>

            <!-- Center: Integrated Minimalist Clock & Date (NO SECONDS) -->
            <div
                class="hidden md:flex items-center gap-3 px-4 py-1.5 rounded-2xl bg-slate-900/80 border border-slate-800 text-xs text-slate-300 font-medium">
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
                <!-- 1. Sunucu Badge -->
                <div class="hidden sm:flex items-center gap-2.5 text-xs">
                    <i class="fi fi-rr-database text-lg text-white shrink-0"></i>
                    <div class="text-left leading-tight">
                        <div class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider">Sunucu</div>
                        <div class="text-xs font-bold text-emerald-400 flex items-center gap-1.5 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Bağlı</span>
                        </div>
                    </div>
                </div>

                <!-- 2. İnternet Badge -->
                <div class="hidden sm:flex items-center gap-2.5 text-xs">
                    <i class="fi fi-rr-wifi text-lg text-white shrink-0"></i>
                    <div class="text-left leading-tight">
                        <div class="text-[9px] font-semibold text-slate-400 uppercase tracking-wider">İnternet</div>
                        <div class="text-xs font-bold text-emerald-400 flex items-center gap-1.5 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>Bağlı</span>
                        </div>
                    </div>
                </div>

                <!-- Active Staff / User Profile Dropdown Badge -->
                <div class="relative inline-block text-left" id="userDropdownWrapper">
                    <button type="button" onclick="toggleUserDropdown()"
                        class="flex items-center gap-2.5 px-3.5 py-1.5 rounded-xl bg-indigo-950/60 border border-indigo-500/30 text-xs hover:bg-indigo-900/60 transition cursor-pointer shadow-sm">
                        <div
                            class="w-7 h-7 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center shrink-0">
                            <i class="fi fi-rr-user text-xs"></i>
                        </div>
                        <div class="text-left">
                            @if(session('active_staff_name'))
                                <div class="font-bold text-white leading-tight">{{ session('active_staff_name') }}</div>
                                <div class="text-[9px] font-bold text-indigo-300 uppercase tracking-wider">
                                    {{ session('active_staff_role') }}</div>
                            @else
                                <div class="font-bold text-white leading-tight">{{ $user->name }}</div>
                                <div class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Kullanıcı</div>
                            @endif
                        </div>
                        <i class="fi fi-rr-angle-small-down text-slate-400 text-sm ml-1 transition-transform duration-200"
                            id="userDropdownArrow"></i>
                    </button>

                    <!-- Dropdown Menu (Genişletilmiş w-72) -->
                    <div id="userDropdownMenu"
                        class="hidden absolute right-0 mt-2 w-72 rounded-2xl bg-[#141724] border border-slate-800 shadow-2xl z-50 overflow-hidden py-1 divide-y divide-slate-800/80">
                        <!-- User Header Info -->
                        <div class="px-4 py-3 bg-slate-900/60 flex items-center gap-3">
                            <div
                                class="w-9 h-9 rounded-xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center shrink-0">
                                <i class="fi fi-rr-user text-sm"></i>
                            </div>
                            <div class="overflow-hidden">
                                @if(session('active_staff_name'))
                                    <p class="text-xs font-bold text-white truncate">{{ session('active_staff_name') }}</p>
                                    <p class="text-[10px] text-indigo-400 font-semibold uppercase mt-0.5">
                                        {{ session('active_staff_role') }}</p>
                                @else
                                    <p class="text-xs font-bold text-white truncate">{{ $user->name }}</p>
                                    <p class="text-[10px] text-slate-400 font-medium truncate mt-0.5">{{ $user->email }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Dropdown Options -->
                        <div class="py-1">
                            <!-- Karanlık / Aydınlık Mod Toggle Switch -->
                            <button type="button" onclick="toggleTheme()"
                                class="w-full text-left px-4 py-2.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800/80 flex items-center justify-between transition cursor-pointer group">
                                <div class="flex items-center gap-2.5">
                                    <i class="fi fi-rr-moon text-indigo-400 text-xs theme-toggle-icon"></i>
                                    <span class="theme-toggle-text font-medium">Karanlık Mod</span>
                                </div>
                                <div class="relative w-9 h-5 rounded-full bg-indigo-600 transition-colors duration-200 theme-switch-bg flex items-center px-0.5 shrink-0">
                                    <div class="w-4 h-4 rounded-full bg-white shadow-md transform translate-x-4 transition-transform duration-200 theme-switch-dot"></div>
                                </div>
                            </button>

                            @if(session('active_staff_name'))
                                <form action="{{ route('staff.switch') }}" method="POST">
                                    @csrf
                                    <button type="submit" title="Profil Değiştir"
                                        class="w-full text-left px-4 py-2.5 text-xs text-slate-200 hover:text-white hover:bg-slate-800/80 flex items-center justify-between transition cursor-pointer">
                                        <div class="flex items-center gap-2.5">
                                            <i class="fi fi-rr-refresh text-indigo-400 text-xs"></i>
                                            <span>Profil Değiştir</span>
                                        </div>
                                        <i class="fi fi-rr-angle-right text-slate-500 text-xs"></i>
                                    </button>
                                </form>
                            @endif

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" title="Çıkış Yap"
                                    class="w-full text-left px-4 py-2.5 text-xs text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 flex items-center justify-between transition cursor-pointer font-semibold">
                                    <div class="flex items-center gap-2.5">
                                        <i class="fi fi-rr-exit text-xs"></i>
                                        <span>Çıkış Yap</span>
                                    </div>
                                    <i class="fi fi-rr-angle-right text-rose-400/50 text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- MAIN DASHBOARD CONTENT -->
        <main class="flex-1 px-4 sm:px-6 py-4 sm:py-6 max-w-5xl w-full mx-auto flex flex-col justify-center">

            <!-- ELEGANT GLASSMORPHIC SQUARE CATEGORY GRID (4 COLUMNS) -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">

                <!-- 1. Masalar -->
                @if(in_array('masalar', $allowedCategories))
                    <a href="{{ route('tables.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-indigo-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white group-hover:border-indigo-500 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-room-service text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Masalar</span>
                    </a>
                @endif

                <!-- 2. Hızlı Satış -->
                @if(in_array('garson', $allowedCategories))
                    <a href="{{ route('waiter.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-blue-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-blue-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 group-hover:bg-blue-600 group-hover:text-white group-hover:border-blue-500 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-user-time text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Garson</span>
                    </a>
                @endif

                @if(in_array('hizli-satis', $allowedCategories))
                    <a href="{{ route('quicksale.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-amber-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-amber-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 group-hover:bg-amber-500 group-hover:text-white group-hover:border-amber-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-bolt text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Hızlı
                            Satış</span>
                    </a>
                @endif

                <!-- 3. Paket Servis -->
                @if(in_array('paket-servis', $allowedCategories))
                    <a href="{{ route('delivery.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-sky-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-sky-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-400 group-hover:bg-sky-500 group-hover:text-white group-hover:border-sky-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-box-alt text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Paket
                            Servis</span>
                    </a>
                @endif

                <!-- 4. Mutfak -->
                @if(in_array('mutfak', $allowedCategories))
                    <a href="{{ route('kitchen.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-emerald-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 group-hover:bg-emerald-500 group-hover:text-white group-hover:border-emerald-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-restaurant text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Mutfak</span>
                    </a>
                @endif

                <!-- 5. Ürünler -->
                @if(in_array('urunler', $allowedCategories))
                    <a href="{{ route('products.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-rose-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-rose-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 group-hover:bg-rose-500 group-hover:text-white group-hover:border-rose-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-box-open text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Ürünler</span>
                    </a>
                @endif

                <!-- 6. Stoklar -->
                @if(in_array('stoklar', $allowedCategories))
                    <a href="{{ route('stocks.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-cyan-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-cyan-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 group-hover:bg-cyan-500 group-hover:text-white group-hover:border-cyan-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-boxes text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Stoklar</span>
                    </a>
                @endif

                <!-- 7. Tedarikçi & Satın Alma -->
                @if(in_array('satinalma', $allowedCategories))
                    <a href="{{ route('purchasing.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-orange-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-orange-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 group-hover:bg-orange-600 group-hover:text-white group-hover:border-orange-500 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-truck-loading text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Tedarik</span>
                    </a>
                @endif

                <!-- 8. Kasa Vardiyası -->
                @if(in_array('kasa', $allowedCategories))
                    <a href="{{ route('cash-shifts.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-teal-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-teal-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-teal-500/10 border border-teal-500/20 text-teal-400 group-hover:bg-teal-600 group-hover:text-white group-hover:border-teal-500 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-cash-register text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Kasa
                            Vardiyası</span>
                    </a>
                @endif

                <!-- 9. Raporlar -->
                @if(in_array('raporlar', $allowedCategories))
                    <a href="{{ route('reports.index') }}"
                        class="group relative flex aspect-square w-full flex-col items-center justify-center rounded-3xl border border-slate-800/60 bg-slate-900/40 backdrop-blur-xl p-4 sm:p-5 shadow-lg transition-all duration-300 hover:-translate-y-1 hover:border-fuchsia-500/40 hover:bg-slate-900/70 hover:shadow-2xl hover:shadow-fuchsia-500/10 cursor-pointer">
                        <div
                            class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-2xl bg-fuchsia-500/10 border border-fuchsia-500/20 text-fuchsia-400 group-hover:bg-fuchsia-500 group-hover:text-white group-hover:border-fuchsia-400 group-hover:scale-105 transition-all duration-300 shadow-inner">
                            <i class="fi fi-rr-chart-pie-alt text-2xl sm:text-3.5xl"></i>
                        </div>
                        <span
                            class="mt-3.5 text-sm sm:text-base font-bold tracking-tight text-slate-200 group-hover:text-white transition-colors text-center">Raporlar</span>
                    </a>
                @endif



            </div>

        </main>

        <!-- BOTTOM FOOTER (MÜŞTERİ HİZMETLERİ & VERSİYON BİLGİSİ & AYARLAR - ŞEFFAF ARKA PLAN) -->
        <footer class="mt-auto px-4 sm:px-8 py-4 bg-transparent grid grid-cols-3 items-center text-xs w-full">
            <!-- SOL ALT KÖŞE: Müşteri Hizmetleri İkonlu Buton -->
            <div class="flex justify-start">
                <button type="button" onclick="openCustomerServiceModal()"
                    class="flex items-center gap-2.5 px-3.5 py-2 rounded-2xl bg-indigo-500/10 hover:bg-indigo-500/20 border border-indigo-500/20 text-indigo-400 hover:text-indigo-300 transition-all duration-200 group cursor-pointer shadow-sm">
                    <div
                        class="w-7 h-7 rounded-xl bg-indigo-500/20 text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white flex items-center justify-center transition-all">
                        <i class="fi fi-rr-headset text-sm"></i>
                    </div>
                    <span class="text-xs font-bold tracking-wide">Müşteri Hizmetleri</span>
                </button>
            </div>

            <!-- ORTA KISIM: Adisyon Pos v1.0.0 (Yatayda Ortalanmış) -->
            <div class="flex items-center justify-center gap-2">
                <span class="font-['Outfit'] font-black tracking-widest text-xs uppercase text-slate-300">
                    Adisyon Pos <span
                        class="font-mono text-indigo-400 font-bold text-[11px] tracking-normal lowercase ml-1">v1.0.0</span>
                </span>
            </div>

            <!-- SAĞ ALT KÖŞE: Ayarlar Butonu (Şeffaf Arka Plan Kaplamalı) -->
            <div class="flex justify-end">
                <a href="{{ route('settings.index') }}"
                    class="flex items-center gap-2.5 px-3.5 py-2 rounded-2xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 text-purple-400 hover:text-purple-300 transition-all duration-200 group cursor-pointer shadow-sm">
                    <div
                        class="w-7 h-7 rounded-xl bg-purple-500/20 text-purple-400 group-hover:bg-purple-600 group-hover:text-white flex items-center justify-center transition-all">
                        <i class="fi fi-rr-settings text-sm"></i>
                    </div>
                    <span class="text-xs font-bold tracking-wide">Ayarlar</span>
                </a>
            </div>
        </footer>

        <!-- 🎧 MÜŞTERİ HİZMETLERİ MODALI -->
        <div id="customerServiceModal"
            class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md transition-all">
            <div
                class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col space-y-0 transform transition-all">

                <!-- MODAL HEADER -->
                <div class="p-5 border-b border-slate-800/80 flex items-center justify-between bg-indigo-500/10">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-10 h-10 rounded-2xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center">
                            <i class="fi fi-rr-headset text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">Müşteri Hizmetleri & Destek</h3>
                            <p class="text-xs text-slate-400">7/24 İletişim ve Yardım Kanalları</p>
                        </div>
                    </div>
                    <button onclick="closeCustomerServiceModal()"
                        class="w-8 h-8 rounded-xl bg-slate-800/80 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                        <i class="fi fi-rr-cross text-xs"></i>
                    </button>
                </div>

                <!-- MODAL BODY -->
                <div class="p-6 space-y-3.5 text-xs">

                    <!-- 💬 WHATSAPP DESTEK -->
                    <a href="https://wa.me/905441234567" target="_blank"
                        class="flex items-center justify-between p-4 rounded-2xl bg-emerald-950/30 border border-emerald-500/30 hover:border-emerald-500/60 transition group cursor-pointer">
                        <div class="flex items-center gap-3.5">
                            <div
                                class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <i class="fi fi-rr-comments text-lg"></i>
                            </div>
                            <div>
                                <div class="font-extrabold text-white text-sm flex items-center gap-2">
                                    WhatsApp Destek Hattı
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold">7/24
                                        Aktif</span>
                                </div>
                                <div class="text-emerald-400/90 font-mono mt-0.5">+90 (544) 123 45 67</div>
                            </div>
                        </div>
                        <i class="fi fi-rr-angle-right text-emerald-400 group-hover:translate-x-1 transition-transform"></i>
                    </a>

                    <!-- 📞 TELEFON -->
                    <a href="tel:+908508884404"
                        class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition group">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0">
                                <i class="fi fi-rr-phone-call text-base"></i>
                            </div>
                            <div>
                                <div class="font-bold text-slate-200">Çağrı Merkezi / Telefon</div>
                                <div class="text-slate-400 font-mono text-xs">+90 (850) 888 44 04</div>
                            </div>
                        </div>
                        <span class="text-xs text-indigo-400 font-semibold group-hover:underline">Hemen Ara</span>
                    </a>

                    <!-- ✉️ E-POSTA -->
                    <a href="mailto:destek@altf4software.com"
                        class="flex items-center justify-between p-3.5 rounded-2xl bg-slate-900/80 border border-slate-800 hover:border-indigo-500/40 transition group">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-9 h-9 rounded-xl bg-sky-500/10 text-sky-400 flex items-center justify-center shrink-0">
                                <i class="fi fi-rr-envelope text-base"></i>
                            </div>
                            <div>
                                <div class="font-bold text-slate-200">E-Posta Destek</div>
                                <div class="text-slate-400 font-mono text-xs">destek@altf4software.com</div>
                            </div>
                        </div>
                        <span class="text-xs text-sky-400 font-semibold group-hover:underline">E-Posta Gönder</span>
                    </a>

                    <!-- 🎧 CANLI DESTEK HAKKINDA BİLGİ -->
                    <div class="p-3.5 rounded-2xl bg-slate-900/40 border border-slate-800/60 flex items-center gap-3">
                        <div
                            class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0">
                            <i class="fi fi-rr-headset text-base"></i>
                        </div>
                        <div>
                            <div class="font-bold text-slate-200">Canlı Destek & Uzaktan Bağlantı</div>
                            <div class="text-slate-400 text-xs">Haftanın 7 Günü: 09:00 - 00:00</div>
                        </div>
                    </div>

                </div>

                <!-- MODAL FOOTER -->
                <div class="p-4 bg-slate-900/90 border-t border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="font-['Outfit'] font-black tracking-widest text-xs uppercase text-slate-300">
                            Adisyon Pos <span
                                class="font-mono text-indigo-400 font-bold text-[11px] tracking-normal lowercase ml-1">v1.0.0</span>
                        </span>
                    </div>
                    <button type="button" onclick="closeCustomerServiceModal()"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                        Kapat
                    </button>
                </div>

            </div>
        </div>

        <!-- LIVE CLOCK & MODAL SCRIPT -->
        <script>
            function toggleUserDropdown() {
                const menu = document.getElementById('userDropdownMenu');
                const arrow = document.getElementById('userDropdownArrow');
                if (menu) {
                    menu.classList.toggle('hidden');
                }
                if (arrow) {
                    arrow.classList.toggle('rotate-180');
                }
            }

            document.addEventListener('click', function (event) {
                const wrapper = document.getElementById('userDropdownWrapper');
                const menu = document.getElementById('userDropdownMenu');
                const arrow = document.getElementById('userDropdownArrow');
                if (wrapper && !wrapper.contains(event.target) && menu && !menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                    if (arrow) arrow.classList.remove('rotate-180');
                }
            });

            function openCustomerServiceModal() {
                document.getElementById('customerServiceModal').classList.remove('hidden');
            }

            function closeCustomerServiceModal() {
                document.getElementById('customerServiceModal').classList.add('hidden');
            }

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
