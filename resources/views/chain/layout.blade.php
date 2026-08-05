<!DOCTYPE html>
<html lang="tr" class="theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app-fonts.css') }}?v={{ filemtime(public_path('assets/css/app-fonts.css')) }}">
    <title>@yield('title', 'Zincir Yönetimi') | Adisyon</title>
    <script>try{const saved=localStorage.getItem('chain-theme');const t=saved||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.className='theme-'+t}catch(e){}</script>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/chain-institutional.css') }}?v=20260801-2">
    <style>
        :root {
            --font-ui: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            --font-display: 'Outfit', 'Plus Jakarta Sans', system-ui, sans-serif;
        }
        body { font-family: var(--font-ui); }
        button,
        input,
        select,
        textarea { font-family: inherit; }
        h1,
        h2,
        h3,
        h4,
        h5,
        h6,
        .font-display,
        .text-display { font-family: var(--font-display); }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
@php
    $navItems = [
        ['chain.dashboard','chain.dashboard','Grafikli Yönetici Özeti','M3 13h8v8H3v-8Zm10-10h8v12h-8V3ZM3 3h8v8H3V3Zm10 14h8v4h-8v-4Z'],
        ['chain.branches.index','chain.branches.*','Şubeler','M4 21V10l8-6 8 6v11M8 21v-7h8v7M2 21h20'],
        ['chain.reports.index','chain.reports.*','Grafik Rapor Merkezi','M4 19V9m5 10V5m5 14v-7m5 7V3M2 21h20'],
        ['chain.menu.index','chain.menu.*','Merkezi Menü','M4 5h16M4 12h16M4 19h16M7 3v4m5 3v4m5 3v4'],
        ['chain.diji-menu.index','chain.diji-menu.*','Diji Menü','M4 5h16v14H4V5Zm4 4h8M8 13h5m-5 3h8'],
        ['chain.stocks.index','chain.stocks.*|chain.stock-transfers.*','Stok ve Transferler','M21 8 12 3 3 8l9 5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5'],
        ['chain.workflows.index','chain.workflows.*','Üretim İş Akışı','M4 6h16M4 12h10M4 18h7m7-8 3 3 5-6'],
        ['chain.purchasing.index','chain.purchasing.*','Satın Alma Yönetimi','M6 8V6a6 6 0 0 1 12 0v2m3 0H3l1 13h16L21 8ZM9 12v2m6-2v2'],
    ];
    $roleLabels = ['owner' => 'Zincir Yöneticisi', 'general_manager' => 'Genel Müdür', 'regional_manager' => 'Bölge Yöneticisi', 'analyst' => 'Raporlama Kullanıcısı'];
    $roleLabel = $roleLabels[auth()->user()->chain_role] ?? 'Yetkili Kullanıcı';
    $chainOrganization = auth()->user()->organization;
    $chainDarkLogoUrl = $chainOrganization?->logo_url ?? asset('assets/images/logo.png');
    $chainLightLogoUrl = $chainOrganization?->light_logo_url ?? asset('assets/images/logo-light.png');
@endphp
<div class="chain-shell flex min-h-screen">
    <div id="sidebarBackdrop" onclick="toggleSidebar(false)" class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm lg:hidden"></div>
    <aside id="chainSidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-800 px-3 py-4 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="flex min-h-14 items-center justify-between px-2">
            <a href="{{ route('chain.dashboard') }}" class="block"><img src="{{ $chainDarkLogoUrl }}" alt="{{ $chainOrganization?->name ?? 'Adisyon POS' }} koyu mod logosu" class="chain-logo-dark max-h-10 max-w-44 object-contain"><img src="{{ $chainLightLogoUrl }}" alt="{{ $chainOrganization?->name ?? 'Adisyon POS' }} açık mod logosu" class="chain-logo-light max-h-10 max-w-44 object-contain"><span class="sidebar-brand-label mt-2 block text-[9px] font-semibold uppercase tracking-[.2em]">Kurumsal Yönetim Sistemi</span></a>
            <button onclick="toggleSidebar(false)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" aria-label="Menüyü kapat"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="sidebar-organization my-5 rounded-lg px-3 py-3.5">
            <p class="sidebar-organization-label text-[9px] font-semibold uppercase tracking-[.16em]">Kurum / Organizasyon</p>
            <div class="mt-2 flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-md bg-white/10 text-xs font-semibold">{{ strtoupper(substr(auth()->user()->organization->name,0,1)) }}</span><div class="min-w-0"><p class="truncate text-sm font-semibold text-white">{{ auth()->user()->organization->name }}</p><p class="sidebar-organization-role mt-0.5 text-[10px] uppercase tracking-wider">{{ $roleLabel }}</p></div></div>
        </div>
        <p class="sidebar-section-label mb-2 px-3 text-[9px] font-semibold uppercase tracking-[.18em]">Yönetim Modülleri</p>
        <nav class="flex-1 space-y-1.5 overflow-y-auto text-sm">
            @foreach($navItems as [$route,$pattern,$label,$path])
                @php($active=collect(explode('|',$pattern))->contains(fn($p)=>request()->routeIs($p)))
                <a data-search-item data-label="{{ $label }}" href="{{ route($route) }}" class="nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors {{ $active ? 'nav-active' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}"><svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $path }}"/></svg><span>{{ $label }}</span>@if($active)<span class="nav-active-dot ml-auto h-1 w-1 rounded-full"></span>@endif</a>
            @endforeach
        </nav>
        <div class="sidebar-footer mt-4 border-t border-white/10 px-2 pt-4 text-[10px]"><div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Güvenli bağlantı etkin</div><p class="mt-2">Adisyon POS · Yönetim Portalı</p></div>
    </aside>

    <main class="chain-main min-w-0 flex-1">
        <header class="chain-header sticky top-0 z-30 flex h-[72px] items-center justify-between border-b border-slate-800 px-4 backdrop-blur-xl lg:px-8">
            <div class="flex items-center gap-3"><button onclick="toggleSidebar(true)" class="rounded-md border border-slate-800 bg-slate-900 p-2 text-slate-400 lg:hidden" aria-label="Menüyü aç"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button><div><p class="text-[9px] font-semibold uppercase tracking-[.18em] text-slate-500">Zincir Kontrol Merkezi</p><p class="mt-0.5 text-sm font-semibold">@yield('title', 'Genel Bakış')</p></div></div>
            <div class="flex items-center gap-2">
                <button onclick="openSearch()" class="hidden items-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-2.5 py-2 text-xs text-slate-500 transition hover:text-slate-100 sm:flex" aria-label="Yönetim modüllerinde ara"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="1.8"/><path stroke-width="1.8" d="m20 20-4-4"/></svg><span class="hidden xl:inline">Modüllerde Ara</span><kbd class="hidden text-[10px] text-slate-600 lg:inline">Ctrl K</kbd></button>
                <button onclick="toggleTheme()" class="rounded-lg border border-slate-800 bg-slate-900 p-2 text-slate-400 transition hover:text-slate-100" aria-label="Temayı değiştir"><svg id="sunIcon" class="hidden h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" stroke-width="1.8"/><path stroke-width="1.8" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg id="moonIcon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg></button>
                <div class="relative"><button id="userMenuButton" onclick="toggleUserMenu()" class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-slate-800" aria-label="Kullanıcı menüsü" aria-expanded="false" aria-controls="userMenu"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-xs font-semibold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span class="hidden max-w-32 truncate text-xs font-medium md:block">{{ auth()->user()->name }}</span><svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m7 10 5 5 5-5"/></svg></button>
                    <div id="userMenu" class="panel-shadow absolute right-0 mt-2 hidden w-64 rounded-2xl border border-slate-800 bg-slate-900 p-2" role="menu" aria-labelledby="userMenuButton"><div class="border-b border-slate-800 p-3"><p class="font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div><div class="p-2 text-xs text-slate-500">Görev: <strong class="text-cyan-400">{{ $roleLabel }}</strong></div><form method="POST" action="{{ route('chain.logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold text-rose-400 hover:bg-rose-500/10" role="menuitem"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg>Güvenli Çıkış</button></form></div>
                </div>
            </div>
        </header>
        <div class="chain-content mx-auto max-w-[1540px] p-4 sm:p-6 lg:p-8">@yield('content')</div>
    </main>
</div>

<div id="searchModal" class="fixed inset-0 z-[70] hidden items-start justify-center bg-slate-950/75 px-4 pt-[12vh] backdrop-blur-sm" onclick="if(event.target===this)closeSearch()" role="dialog" aria-modal="true" aria-labelledby="searchTitle"><div class="panel-shadow w-full max-w-xl overflow-hidden rounded-2xl border border-slate-700 bg-slate-900"><h2 id="searchTitle" class="sr-only">Yönetim modüllerinde ara</h2><div class="flex items-center gap-3 border-b border-slate-800 px-4"><svg class="h-5 w-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2"/><path stroke-width="2" d="m20 20-4-4"/></svg><input id="searchInput" oninput="filterSearch(this.value)" class="w-full bg-transparent py-4 outline-none" placeholder="Modül adı yazın..." autocomplete="off"><button onclick="closeSearch()" class="rounded-lg bg-slate-800 px-2 py-1 text-xs text-slate-500" aria-label="Arama penceresini kapat">ESC</button></div><div id="searchResults" class="max-h-80 space-y-1 overflow-y-auto p-2"></div><p class="border-t border-slate-800 p-3 text-center text-[10px] text-slate-600">İlgili modüle gitmek için sonucu seçin</p></div></div>

<script>
const searchItems=[...document.querySelectorAll('[data-search-item]')].map(a=>({label:a.dataset.label,url:a.href,icon:a.querySelector('svg').outerHTML}));
function toggleSidebar(open){document.getElementById('chainSidebar').classList.toggle('-translate-x-full',!open);document.getElementById('sidebarBackdrop').classList.toggle('hidden',!open)}
function syncThemeIcon(){const light=document.documentElement.classList.contains('theme-light');document.getElementById('sunIcon').classList.toggle('hidden',!light);document.getElementById('moonIcon').classList.toggle('hidden',light)}
function toggleTheme(){const light=!document.documentElement.classList.contains('theme-light');document.documentElement.className=light?'theme-light':'theme-dark';localStorage.setItem('chain-theme',light?'light':'dark');syncThemeIcon()}
function toggleUserMenu(){const menu=document.getElementById('userMenu');menu.classList.toggle('hidden');document.getElementById('userMenuButton').setAttribute('aria-expanded',String(!menu.classList.contains('hidden')))}
function closeUserMenu(){document.getElementById('userMenu').classList.add('hidden');document.getElementById('userMenuButton').setAttribute('aria-expanded','false')}
function renderSearch(q=''){const result=document.getElementById('searchResults');const matches=searchItems.filter(i=>i.label.toLocaleLowerCase('tr').includes(q.toLocaleLowerCase('tr')));result.innerHTML=matches.length?matches.map(i=>`<a href="${i.url}" class="flex items-center gap-3 rounded-xl p-3 text-sm font-bold text-slate-400 hover:bg-slate-800 hover:text-cyan-400">${i.icon}<span>${i.label}</span><span class="ml-auto text-slate-600">→</span></a>`).join(''):'<p class="p-8 text-center text-sm text-slate-500">Sonuç bulunamadı.</p>'}
function openSearch(){const m=document.getElementById('searchModal');m.classList.remove('hidden');m.classList.add('flex');renderSearch();setTimeout(()=>document.getElementById('searchInput').focus(),50)}
function closeSearch(){const m=document.getElementById('searchModal');m.classList.add('hidden');m.classList.remove('flex')}
function filterSearch(q){renderSearch(q)}
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openSearch()}if(e.key==='Escape'){closeSearch();closeUserMenu()}});document.addEventListener('click',e=>{if(!e.target.closest('#userMenu')&&!e.target.closest('#userMenuButton'))closeUserMenu()});syncThemeIcon();
</script>
</body>
</html>
