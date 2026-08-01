<!DOCTYPE html>
<html lang="tr" class="theme-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Zincir Yönetimi') | Adisyon</title>
    <script>try{const t=localStorage.getItem('chain-theme')||'dark';document.documentElement.className='theme-'+t}catch(e){}</script>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <style>
        :root{color-scheme:dark}.theme-light{color-scheme:light}.theme-light body{background:#f1f5f9!important;color:#0f172a!important}.theme-light .bg-slate-950{background-color:#f8fafc!important}.theme-light .bg-slate-950\/50{background-color:rgba(241,245,249,.72)!important}.theme-light .bg-slate-900,.theme-light .bg-slate-900\/70{background-color:#fff!important}.theme-light .bg-slate-800{background-color:#e2e8f0!important}.theme-light .border-slate-800,.theme-light .border-slate-700{border-color:#dbe3ee!important}.theme-light .text-slate-100,.theme-light .text-white{color:#0f172a!important}.theme-light .text-slate-400{color:#475569!important}.theme-light .text-slate-500,.theme-light .text-slate-600{color:#64748b!important}.theme-light input,.theme-light select,.theme-light textarea{color:#0f172a!important}.theme-light .dark-logo{display:none}.theme-dark .light-logo{display:none}.nav-link svg{transition:transform .2s}.nav-link:hover svg{transform:scale(1.08)}.panel-shadow{box-shadow:0 20px 50px rgba(2,8,23,.12)}
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
@php
    $navItems = [
        ['chain.dashboard','chain.dashboard','Genel Bakış','M3 13h8v8H3v-8Zm10-10h8v12h-8V3ZM3 3h8v8H3V3Zm10 14h8v4h-8v-4Z'],
        ['chain.branches.index','chain.branches.*','Şubeler','M4 21V10l8-6 8 6v11M8 21v-7h8v7M2 21h20'],
        ['chain.reports.index','chain.reports.*','Raporlar','M4 19V9m5 10V5m5 14v-7m5 7V3M2 21h20'],
        ['chain.menu.index','chain.menu.*','Merkezi Menü','M4 5h16M4 12h16M4 19h16M7 3v4m5 3v4m5 3v4'],
        ['chain.stocks.index','chain.stocks.*|chain.stock-transfers.*','Stoklar & Transfer','M21 8 12 3 3 8l9 5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5'],
        ['chain.purchasing.index','chain.purchasing.*','Merkezi Satın Alma','M6 8V6a6 6 0 0 1 12 0v2m3 0H3l1 13h16L21 8ZM9 12v2m6-2v2'],
    ];
@endphp
<div class="flex min-h-screen">
    <div id="sidebarBackdrop" onclick="toggleSidebar(false)" class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm lg:hidden"></div>
    <aside id="chainSidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-slate-800 bg-slate-900 p-5 transition-transform duration-300 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="flex items-center justify-between px-2">
            <a href="{{ route('chain.dashboard') }}"><img src="{{ asset('assets/images/logo.png') }}" alt="Adisyon" class="dark-logo h-9"><img src="{{ asset('assets/images/logo-light.png') }}" alt="Adisyon" class="light-logo h-9"></a>
            <button onclick="toggleSidebar(false)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" aria-label="Menüyü kapat"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="my-7 rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4">
            <div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-500 font-black text-slate-950">{{ strtoupper(substr(auth()->user()->organization->name,0,1)) }}</span><div class="min-w-0"><p class="truncate font-bold">{{ auth()->user()->organization->name }}</p><p class="mt-0.5 text-[10px] uppercase tracking-widest text-cyan-400">{{ str_replace('_',' ',auth()->user()->chain_role) }}</p></div></div>
        </div>
        <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.2em] text-slate-600">Yönetim</p>
        <nav class="flex-1 space-y-1.5 overflow-y-auto text-sm">
            @foreach($navItems as [$route,$pattern,$label,$path])
                @php($active=collect(explode('|',$pattern))->contains(fn($p)=>request()->routeIs($p)))
                <a data-search-item data-label="{{ $label }}" href="{{ route($route) }}" class="nav-link flex items-center gap-3 rounded-xl px-3.5 py-3 font-bold transition {{ $active ? 'bg-cyan-500 text-slate-950 shadow-lg shadow-cyan-500/20' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}"><svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $path }}"/></svg><span>{{ $label }}</span>@if($active)<span class="ml-auto h-1.5 w-1.5 rounded-full bg-slate-950"></span>@endif</a>
            @endforeach
        </nav>
        <div class="mt-5 border-t border-slate-800 pt-5 text-xs text-slate-600"><div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Sistem çevrimiçi</div><p class="mt-2">Adisyon Zincir v1.0</p></div>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-slate-800 bg-slate-950/80 px-4 backdrop-blur-xl lg:px-8">
            <div class="flex items-center gap-3"><button onclick="toggleSidebar(true)" class="rounded-xl border border-slate-800 bg-slate-900 p-2.5 text-slate-400 lg:hidden" aria-label="Menüyü aç"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button><div><p class="text-[10px] font-bold uppercase tracking-[.22em] text-cyan-400">Zincir Kontrol Merkezi</p><p class="mt-1 text-sm font-semibold text-slate-400">@yield('title', 'Genel Bakış')</p></div></div>
            <div class="flex items-center gap-2">
                <button onclick="openSearch()" class="hidden items-center gap-3 rounded-xl border border-slate-800 bg-slate-900 px-3 py-2.5 text-sm text-slate-500 transition hover:border-cyan-500/40 hover:text-cyan-400 sm:flex"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2"/><path stroke-width="2" d="m20 20-4-4"/></svg><span class="hidden xl:inline">Panelde ara</span><kbd class="hidden rounded bg-slate-800 px-1.5 py-0.5 text-[10px] lg:inline">Ctrl K</kbd></button>
                <button onclick="toggleTheme()" class="rounded-xl border border-slate-800 bg-slate-900 p-2.5 text-slate-400 transition hover:text-cyan-400" aria-label="Temayı değiştir"><svg id="sunIcon" class="hidden h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" stroke-width="2"/><path stroke-width="2" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg id="moonIcon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg></button>
                <div class="relative"><button onclick="toggleUserMenu()" class="flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-900 p-1.5 pr-3 transition hover:border-cyan-500/40"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-400 to-indigo-500 text-sm font-black text-white">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span class="hidden max-w-32 truncate text-sm font-bold md:block">{{ auth()->user()->name }}</span><svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m7 10 5 5 5-5"/></svg></button>
                    <div id="userMenu" class="panel-shadow absolute right-0 mt-2 hidden w-64 rounded-2xl border border-slate-800 bg-slate-900 p-2"><div class="border-b border-slate-800 p-3"><p class="font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div><div class="p-2 text-xs text-slate-500">Rol: <strong class="text-cyan-400">{{ str_replace('_',' ',auth()->user()->chain_role) }}</strong></div><form method="POST" action="{{ route('chain.logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold text-rose-400 hover:bg-rose-500/10"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg>Çıkış Yap</button></form></div>
                </div>
            </div>
        </header>
        <div class="p-4 sm:p-6 lg:p-8">@yield('content')</div>
    </main>
</div>

<div id="searchModal" class="fixed inset-0 z-[70] hidden items-start justify-center bg-slate-950/75 px-4 pt-[12vh] backdrop-blur-sm" onclick="if(event.target===this)closeSearch()"><div class="panel-shadow w-full max-w-xl overflow-hidden rounded-2xl border border-slate-700 bg-slate-900"><div class="flex items-center gap-3 border-b border-slate-800 px-4"><svg class="h-5 w-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="2"/><path stroke-width="2" d="m20 20-4-4"/></svg><input id="searchInput" oninput="filterSearch(this.value)" class="w-full bg-transparent py-4 outline-none" placeholder="Sayfa veya özellik ara..."><button onclick="closeSearch()" class="rounded-lg bg-slate-800 px-2 py-1 text-xs text-slate-500">ESC</button></div><div id="searchResults" class="max-h-80 space-y-1 overflow-y-auto p-2"></div><p class="border-t border-slate-800 p-3 text-center text-[10px] text-slate-600">Sonuç seçmek için tıklayın</p></div></div>

<script>
const searchItems=[...document.querySelectorAll('[data-search-item]')].map(a=>({label:a.dataset.label,url:a.href,icon:a.querySelector('svg').outerHTML}));
function toggleSidebar(open){document.getElementById('chainSidebar').classList.toggle('-translate-x-full',!open);document.getElementById('sidebarBackdrop').classList.toggle('hidden',!open)}
function syncThemeIcon(){const light=document.documentElement.classList.contains('theme-light');document.getElementById('sunIcon').classList.toggle('hidden',!light);document.getElementById('moonIcon').classList.toggle('hidden',light)}
function toggleTheme(){const light=!document.documentElement.classList.contains('theme-light');document.documentElement.className=light?'theme-light':'theme-dark';localStorage.setItem('chain-theme',light?'light':'dark');syncThemeIcon()}
function toggleUserMenu(){document.getElementById('userMenu').classList.toggle('hidden')}
function renderSearch(q=''){const result=document.getElementById('searchResults');const matches=searchItems.filter(i=>i.label.toLocaleLowerCase('tr').includes(q.toLocaleLowerCase('tr')));result.innerHTML=matches.length?matches.map(i=>`<a href="${i.url}" class="flex items-center gap-3 rounded-xl p-3 text-sm font-bold text-slate-400 hover:bg-slate-800 hover:text-cyan-400">${i.icon}<span>${i.label}</span><span class="ml-auto text-slate-600">→</span></a>`).join(''):'<p class="p-8 text-center text-sm text-slate-500">Sonuç bulunamadı.</p>'}
function openSearch(){const m=document.getElementById('searchModal');m.classList.remove('hidden');m.classList.add('flex');renderSearch();setTimeout(()=>document.getElementById('searchInput').focus(),50)}
function closeSearch(){const m=document.getElementById('searchModal');m.classList.add('hidden');m.classList.remove('flex')}
function filterSearch(q){renderSearch(q)}
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openSearch()}if(e.key==='Escape'){closeSearch();document.getElementById('userMenu').classList.add('hidden')}});document.addEventListener('click',e=>{if(!e.target.closest('#userMenu')&&!e.target.closest('[onclick="toggleUserMenu()"]'))document.getElementById('userMenu').classList.add('hidden')});syncThemeIcon();
</script>
</body>
</html>
