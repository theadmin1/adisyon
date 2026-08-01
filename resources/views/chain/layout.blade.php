<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Zincir Yönetimi') | Adisyon</title>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <div class="flex min-h-screen">
        <aside class="hidden w-64 shrink-0 border-r border-slate-800 bg-slate-900/70 p-5 lg:block">
            <img src="{{ asset('assets/images/logo.png') }}" alt="Adisyon" class="h-10 mb-8">
            <div class="mb-7 rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4">
                <p class="text-xs uppercase tracking-wider text-cyan-400">Organizasyon</p>
                <p class="mt-1 font-bold">{{ auth()->user()->organization->name }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ strtoupper(auth()->user()->chain_role) }}</p>
            </div>
            <nav class="space-y-2 text-sm">
                <a href="{{ route('chain.dashboard') }}" class="block rounded-xl px-4 py-3 font-bold {{ request()->routeIs('chain.dashboard') ? 'bg-cyan-500 text-slate-950' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">Genel Bakış</a>
                <a href="{{ route('chain.branches.index') }}" class="block rounded-xl px-4 py-3 font-bold {{ request()->routeIs('chain.branches.*') ? 'bg-cyan-500 text-slate-950' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">Şubeler</a>
                <a href="{{ route('chain.reports.index') }}" class="block rounded-xl px-4 py-3 font-bold {{ request()->routeIs('chain.reports.*') ? 'bg-cyan-500 text-slate-950' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">Raporlar</a>
                <span class="block rounded-xl px-4 py-3 text-slate-500">Merkezi Menü <small class="float-right">Yakında</small></span>
                <span class="block rounded-xl px-4 py-3 text-slate-500">Stoklar <small class="float-right">Yakında</small></span>
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="flex h-20 items-center justify-between border-b border-slate-800 px-5 lg:px-8">
                <div>
                    <p class="text-xs uppercase tracking-widest text-cyan-400">Zincir Kontrol Merkezi</p>
                    <p class="font-bold">{{ auth()->user()->name }}</p>
                </div>
                <form method="POST" action="{{ route('chain.logout') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-700 px-4 py-2 text-sm hover:border-rose-400 hover:text-rose-300">Çıkış Yap</button>
                </form>
            </header>
            <div class="p-5 lg:p-8">@yield('content')</div>
        </main>
    </div>
</body>
</html>
