<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central Admin Girişi - AltF4 Licensor</title>
    <!-- Yerel Offline Varlıklar -->
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/uicons-regular-rounded.css') }}">
</head>
<body class="bg-[#0b0c10] text-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md space-y-6">

        <!-- LOGO & HEADER -->
        <div class="text-center space-y-2">
            <div class="flex items-center justify-center mb-3">
                <img src="{{ asset('assets/images/logo.png') }}" alt="ADİSYON POS" class="h-16 w-auto object-contain drop-shadow-2xl">
            </div>
            <h1 class="text-xl font-bold text-white tracking-wide">Central Admin Portalı</h1>
            <p class="text-xs text-gray-400">Restoran Lisanslama, Şube & Cihaz Yönetim Paneli</p>
        </div>

        @if(session('info'))
            <div class="bg-blue-950/80 border border-blue-500/40 text-blue-300 px-4 py-3 rounded-lg text-xs">
                {{ session('info') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-rose-950/80 border border-rose-500/40 text-rose-300 px-4 py-3 rounded-lg text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <!-- GİRİŞ FORMU KARTI -->
        <div class="bg-[#141620] border border-gray-800 p-8 rounded-2xl shadow-2xl space-y-6">
            <div class="bg-indigo-950/50 border border-indigo-500/30 text-indigo-300 text-xs px-3 py-2 rounded-lg flex items-center justify-between font-mono">
                <span>ROLE: SUPER ADMIN</span>
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            </div>

            <form action="{{ route('admin.login') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1.5">Admin E-Posta Adresi</label>
                    <input type="email" name="email" value="{{ old('email', 'admin@adisyon.com') }}" required autofocus placeholder="admin@adisyon.com"
                        class="w-full bg-[#0b0c10] border border-gray-700 text-white rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1.5">Admin Şifresi</label>
                    <input type="password" name="password" value="password" required placeholder="••••••••"
                        class="w-full bg-[#0b0c10] border border-gray-700 text-white rounded-xl px-4 py-3 text-sm focus:border-indigo-500 focus:outline-none transition">
                </div>

                <div class="flex items-center justify-between text-xs text-gray-400">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded bg-[#0b0c10] border-gray-700 text-indigo-600 focus:ring-0">
                        <span>Beni Hatırla</span>
                    </label>
                    <a href="{{ route('login') }}" class="text-indigo-400 hover:underline">Restoran Kasa Girişine Git &rarr;</a>
                </div>

                <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/30 transition">
                    🔑 Admin Paneline Giriş Yap
                </button>
            </form>

            <div class="pt-4 border-t border-gray-800 text-center text-xs text-gray-500">
                Varsayılan Admin: <code class="text-indigo-300 font-mono">admin@adisyon.com</code> / <code class="text-indigo-300 font-mono">password</code>
            </div>
        </div>

    </div>

</body>
</html>
