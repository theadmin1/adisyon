<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AltF4 Central Admin Panel')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="bg-[#0f1117] text-gray-100 min-h-screen flex flex-col">

    <!-- ÜST NAVBAR -->
    <header class="bg-[#181a24] border-b border-gray-800 h-16 flex items-center justify-between px-6 sticky top-0 z-30">
        <div class="flex items-center space-x-3">
            <span class="text-2xl">⚡</span>
            <div>
                <h1 class="text-lg font-bold tracking-wide text-white">AltF4 Adisyon <span class="text-indigo-400 text-sm font-semibold">Central Admin</span></h1>
                <p class="text-xs text-gray-400">Bulut Şube, Lisans & Cihaz Yönetim Paneli</p>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <div class="bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs px-3 py-1.5 rounded-full flex items-center space-x-2 font-mono">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>API STATUS: ONLINE (v1.0)</span>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-gray-300 text-xs px-3 py-1.5 rounded border border-gray-700 transition">
                    <i class="fa-solid fa-sign-out-alt mr-1"></i> Çıkış Yap
                </button>
            </form>
        </div>
    </header>

    <div class="flex flex-1">
        <!-- SOL SİDEBAR -->
        <aside class="w-64 bg-[#141620] border-r border-gray-800 p-4 space-y-6 flex flex-col">
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-3">Yönetim Menüsü</div>
            <nav class="space-y-1 flex-1">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-[#fa-chart-pie] w-5 text-center"></i>
                    <span>📊 Genel Bakış</span>
                </a>
                <a href="{{ route('admin.licenses.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.licenses.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-key w-5 text-center"></i>
                    <span>🔑 Lisans Yönetimi</span>
                </a>
                <a href="{{ route('admin.branches.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.branches.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-store w-5 text-center"></i>
                    <span>🏬 Şubeler & Restoranlar</span>
                </a>
                <a href="{{ route('admin.staff.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.staff.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-users w-5 text-center"></i>
                    <span>👥 Personel & Profiller</span>
                </a>
                <a href="{{ route('admin.roles.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.roles.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-user-shield w-5 text-center"></i>
                    <span>🔐 Rol & Yetki Tanımları</span>
                </a>
                <a href="{{ route('admin.devices.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.devices.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-desktop w-5 text-center"></i>
                    <span>💻 Kayıtlı Cihazlar</span>
                </a>
                <a href="{{ route('admin.logs.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('admin.logs.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
                    <i class="fa-solid fa-list-check w-5 text-center"></i>
                    <span>📋 Canlı Loglar & Sinyaller</span>
                </a>
            </nav>

            <div class="bg-[#181a24] p-3 rounded-lg border border-gray-800 text-xs text-gray-400 space-y-1">
                <div class="font-bold text-gray-300">🔗 Windows Service API Endpoint</div>
                <div class="font-mono text-indigo-400 break-all">/api/v1/license/verify</div>
            </div>
        </aside>

        <!-- İÇERİK BÖLGESİ -->
        <main class="flex-1 p-8 space-y-6 overflow-y-auto">
            @if(session('success'))
                <div class="bg-emerald-950/80 border border-emerald-500/50 text-emerald-300 px-4 py-3 rounded-lg text-sm flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-2">
                        <span>✅</span>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-rose-950/80 border border-rose-500/50 text-rose-300 px-4 py-3 rounded-lg text-sm flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-2">
                        <span>❌</span>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</body>
</html>
