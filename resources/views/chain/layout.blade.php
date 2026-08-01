<!DOCTYPE html>
<html lang="tr" class="theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Zincir Yönetimi') | Adisyon</title>
    <script>try{const saved=localStorage.getItem('chain-theme');const t=saved||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.className='theme-'+t}catch(e){}</script>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <style>
        :root{color-scheme:dark}body{font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;letter-spacing:-.01em}.theme-light{color-scheme:light}.theme-light body{background:#f5f6f8!important;color:#18181b!important}.theme-light #chainSidebar,.theme-light .chain-header{background:rgba(255,255,255,.96)!important}.theme-light .chain-header{box-shadow:0 1px 0 rgba(24,24,27,.06)}.theme-light .bg-slate-950,.theme-light .bg-slate-950\/50,.theme-light .bg-slate-950\/60,.theme-light .bg-slate-950\/80{background-color:#f8fafc!important}.theme-light .bg-slate-900,.theme-light .bg-slate-900\/70{background-color:#fff!important}.theme-light .bg-slate-800{background-color:#f1f3f6!important}.theme-light .border-slate-800,.theme-light .border-slate-700{border-color:#e6e8ec!important}.theme-light .text-slate-100,.theme-light .text-white{color:#18181b!important}.theme-light .bg-cyan-500.text-white{color:#fff!important}.theme-light .text-slate-400{color:#52525b!important}.theme-light .text-slate-500,.theme-light .text-slate-600{color:#71717a!important}.theme-light .text-cyan-300,.theme-light .text-cyan-400,.theme-light .text-cyan-500{color:#4f46e5!important}.theme-light .bg-cyan-500{background-color:#4f46e5!important}.theme-light .border-cyan-500\/30,.theme-light .border-cyan-500\/40{border-color:#c7d2fe!important}.theme-light input,.theme-light select,.theme-light textarea{color:#18181b!important;background:#fff!important}.theme-light section.bg-slate-900,.theme-light div.bg-slate-900{box-shadow:0 1px 2px rgba(15,23,42,.025),0 6px 18px rgba(15,23,42,.035)}.theme-light article.bg-slate-950{background:#fafbfc!important}.theme-light .dark-logo{display:none}.theme-dark .light-logo{display:none}.theme-light .nav-active{background:#eef2ff!important;color:#4338ca!important}.theme-dark .nav-active{background:#f4f4f5!important;color:#18181b!important}.theme-light .nav-active-dot{background:#4f46e5!important}.theme-dark .nav-active-dot{background:#18181b!important}.theme-light .font-black{font-weight:700!important}.nav-link{letter-spacing:-.01em}.panel-shadow{box-shadow:0 12px 30px rgba(0,0,0,.08)}.rounded-2xl{border-radius:1rem!important}.rounded-xl{border-radius:.75rem!important}::selection{background:#c7d2fe;color:#312e81}
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
    <aside id="chainSidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-slate-800 bg-slate-900 px-3 py-4 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
        <div class="flex h-11 items-center justify-between px-2">
            <a href="{{ route('chain.dashboard') }}"><img src="{{ asset('assets/images/logo.png') }}" alt="Adisyon" class="dark-logo h-7"><img src="{{ asset('assets/images/logo-light.png') }}" alt="Adisyon" class="light-logo h-7"></a>
            <button onclick="toggleSidebar(false)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800 lg:hidden" aria-label="Menüyü kapat"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="my-5 border-y border-slate-800 px-2 py-4">
            <div class="flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 text-xs font-bold">{{ strtoupper(substr(auth()->user()->organization->name,0,1)) }}</span><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth()->user()->organization->name }}</p><p class="mt-0.5 text-[10px] uppercase tracking-wider text-slate-500">{{ str_replace('_',' ',auth()->user()->chain_role) }}</p></div></div>
        </div>
        <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.16em] text-slate-600">Menü</p>
        <nav class="flex-1 space-y-1.5 overflow-y-auto text-sm">
            @foreach($navItems as [$route,$pattern,$label,$path])
                @php($active=collect(explode('|',$pattern))->contains(fn($p)=>request()->routeIs($p)))
                <a data-search-item data-label="{{ $label }}" href="{{ route($route) }}" class="nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium transition-colors {{ $active ? 'nav-active' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}"><svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="{{ $path }}"/></svg><span>{{ $label }}</span>@if($active)<span class="nav-active-dot ml-auto h-1 w-1 rounded-full"></span>@endif</a>
            @endforeach
        </nav>
        <div class="mt-4 border-t border-slate-800 px-2 pt-4 text-[11px] text-slate-600"><div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Sistem çevrimiçi</div></div>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="chain-header sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-800 bg-slate-950/80 px-4 backdrop-blur-xl lg:px-7">
            <div class="flex items-center gap-3"><button onclick="toggleSidebar(true)" class="rounded-lg border border-slate-800 bg-slate-900 p-2 text-slate-400 lg:hidden" aria-label="Menüyü aç"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button><div><p class="text-[10px] font-medium uppercase tracking-[.16em] text-slate-500">Zincir Kontrol Merkezi</p><p class="text-sm font-semibold">@yield('title', 'Genel Bakış')</p></div></div>
            <div class="flex items-center gap-2">
                <button onclick="openSearch()" class="hidden items-center gap-2 rounded-lg border border-slate-800 bg-slate-900 px-2.5 py-2 text-xs text-slate-500 transition hover:text-slate-100 sm:flex"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="1.8"/><path stroke-width="1.8" d="m20 20-4-4"/></svg><span class="hidden xl:inline">Ara</span><kbd class="hidden text-[10px] text-slate-600 lg:inline">⌘K</kbd></button>
                <button onclick="toggleTheme()" class="rounded-lg border border-slate-800 bg-slate-900 p-2 text-slate-400 transition hover:text-slate-100" aria-label="Temayı değiştir"><svg id="sunIcon" class="hidden h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" stroke-width="1.8"/><path stroke-width="1.8" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg id="moonIcon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg></button>
                <div class="relative"><button onclick="toggleUserMenu()" class="flex items-center gap-2 rounded-lg p-1 transition hover:bg-slate-800"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-800 text-xs font-semibold">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span class="hidden max-w-32 truncate text-xs font-medium md:block">{{ auth()->user()->name }}</span><svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="m7 10 5 5 5-5"/></svg></button>
                    <div id="userMenu" class="panel-shadow absolute right-0 mt-2 hidden w-64 rounded-2xl border border-slate-800 bg-slate-900 p-2"><div class="border-b border-slate-800 p-3"><p class="font-bold">{{ auth()->user()->name }}</p><p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p></div><div class="p-2 text-xs text-slate-500">Rol: <strong class="text-cyan-400">{{ str_replace('_',' ',auth()->user()->chain_role) }}</strong></div><form method="POST" action="{{ route('chain.logout') }}">@csrf<button class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-bold text-rose-400 hover:bg-rose-500/10"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg>Çıkış Yap</button></form></div>
                </div>
            </div>
        </header>
        <div class="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-7">@yield('content')</div>
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
