@extends('layouts.app')

@section('title', 'Kontrol Paneli - Adisyon Sistem Portalı')

@section('content')
<div class="min-h-screen flex flex-col bg-slate-950">
    <!-- Top Navigation Bar -->
    <header class="glass-panel sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3.5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center">
                <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
            </div>
            <div>
                <h1 class="font-bold text-lg text-white leading-none">Adisyon Portalı</h1>
                <span class="text-xs text-slate-400">Restoran Yönetim Paneli</span>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <!-- User Info Badge -->
            <div class="hidden sm:flex items-center gap-3 px-3.5 py-1.5 rounded-xl bg-slate-800/60 border border-slate-700/50">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-bold text-white text-sm">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <div class="text-left text-xs">
                    <div class="font-semibold text-white">{{ $user->name }}</div>
                    <div class="text-slate-400">{{ $user->email }}</div>
                </div>
            </div>

            <!-- Logout Button -->
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 hover:border-red-500/30 text-xs font-semibold transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Çıkış Yap</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 lg:p-8 max-w-7xl w-full mx-auto space-y-8">

        <!-- Welcome Banner -->
        <div class="relative overflow-hidden rounded-3xl p-8 lg:p-10 border border-indigo-500/20 bg-gradient-to-r from-indigo-950/80 via-slate-900/90 to-purple-950/80 shadow-2xl">
            <div class="absolute -right-20 -top-20 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-500/30 text-indigo-300 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Sistem Aktif & Çevrimiçi</span>
                </div>
                
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white">
                    Merhaba, <span class="gradient-text">{{ $user->name }}</span>! 👋
                </h2>
                
                <p class="text-slate-300 text-sm max-w-2xl">
                    Adisyon Restoran Otomasyon Sistemine hoş geldiniz. Bugünün özet istatistiklerini, masa durumlarını ve aktif siparişleri aşağıdan takip edebilirsiniz.
                </p>
            </div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Stat 1 -->
            <div class="glass-panel p-6 rounded-2xl space-y-3">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-xs font-semibold uppercase tracking-wider">Bugünkü Satışlar</span>
                    <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold text-white">{{ $stats['total_sales'] }}</div>
                <div class="text-xs text-emerald-400 font-medium">↑ Düne göre %14 artış</div>
            </div>

            <!-- Stat 2 -->
            <div class="glass-panel p-6 rounded-2xl space-y-3">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-xs font-semibold uppercase tracking-wider">Açık Masalar</span>
                    <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold text-white">{{ $stats['open_tables'] }} Masa</div>
                <div class="text-xs text-amber-400 font-medium">%60 Doluluk Oranı</div>
            </div>

            <!-- Stat 3 -->
            <div class="glass-panel p-6 rounded-2xl space-y-3">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-xs font-semibold uppercase tracking-wider">Tamamlanan Siparişler</span>
                    <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold text-white">{{ $stats['completed_orders'] }} Adet</div>
                <div class="text-xs text-indigo-400 font-medium">Ort. Hazırlama: 12 dk</div>
            </div>

            <!-- Stat 4 -->
            <div class="glass-panel p-6 rounded-2xl space-y-3">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-xs font-semibold uppercase tracking-wider">Aktif Garsonlar</span>
                    <div class="p-2 rounded-xl bg-purple-500/10 text-purple-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                </div>
                <div class="text-2xl font-bold text-white">{{ $stats['active_waiters'] }} Personel</div>
                <div class="text-xs text-purple-400 font-medium">Vardiya Devam Ediyor</div>
            </div>
        </div>

        <!-- Tables Overview -->
        <div class="glass-panel p-6 rounded-3xl space-y-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-white">Masa Canlı Durumu</h3>
                    <p class="text-xs text-slate-400">Restorandaki masaların anlık adisyon ve süre durumları</p>
                </div>
                <button class="px-4 py-2 rounded-xl gradient-btn text-white text-xs font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
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
