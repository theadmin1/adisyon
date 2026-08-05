<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('assets/css/app-fonts.css') }}?v={{ filemtime(public_path('assets/css/app-fonts.css')) }}">
    <title>Zincir Yönetimi | Adisyon</title>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
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
<body class="min-h-screen bg-slate-950 text-white flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <img src="{{ asset('assets/images/logo.png') }}" alt="Adisyon" class="h-14 mx-auto mb-5">
            <div class="inline-flex rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-bold tracking-widest text-cyan-300">CHAIN CONTROL</div>
            <h1 class="mt-4 text-3xl font-black">Zincir Yönetim Paneli</h1>
            <p class="mt-2 text-sm text-slate-400">Tüm şubelerinizi tek merkezden yönetin.</p>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-7 shadow-2xl">
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-300">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('chain.login.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-400">E-posta</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-cyan-400">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-400">Şifre</label>
                    <input type="password" name="password" required autocomplete="current-password"
                           class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 outline-none transition focus:border-cyan-400">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-600 bg-slate-900 text-cyan-500">
                    Oturumu açık tut
                </label>
                <button class="w-full rounded-xl bg-cyan-500 px-4 py-3 font-black text-slate-950 transition hover:bg-cyan-400">Panele Giriş Yap</button>
            </form>
        </div>

        <div class="mt-5 flex justify-center gap-5 text-xs text-slate-500">
            <a href="{{ route('login') }}" class="hover:text-cyan-300">Restoran POS</a>
            <a href="{{ route('admin.login') }}" class="hover:text-cyan-300">Sistem Yönetimi</a>
        </div>
    </div>
</body>
</html>
