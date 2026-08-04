<!DOCTYPE html>
<html lang="tr" class="h-full app-booting">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html.app-booting body > :not(#appStyleLoader) { visibility: hidden !important; }
        #appStyleLoader {
            position: fixed; inset: 0; z-index: 2147483647; display: flex;
            align-items: center; justify-content: center; background: #0b0c12;
            color: #e2e8f0; font: 600 14px/1.5 system-ui, sans-serif;
        }
        #appStyleLoader > div { text-align: center; }
        #appStyleLoaderSpinner {
            width: 34px; height: 34px; margin: 0 auto 14px; border-radius: 9999px;
            border: 3px solid #273149; border-top-color: #6366f1;
            animation: app-style-spin .7s linear infinite;
        }
        @keyframes app-style-spin { to { transform: rotate(360deg); } }
        html.app-ready #appStyleLoader { display: none; }
    </style>
    @if (file_exists(public_path('build/manifest.json')))
        @vite('resources/css/app.css')
    @else
        <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    @endif
    <title>@yield('title', 'Adisyon Sistem Portalı')</title>
    <!-- Yerel Offline Varlıklar (İnternet Kesintisinde %100 Çevrimdışı Kasa Desteği) -->
    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}?v={{ filemtime(public_path('assets/css/fontawesome.min.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/uicons-regular-rounded.css') }}?v={{ filemtime(public_path('assets/css/uicons-regular-rounded.css')) }}">
    <script>
        // Sayfa yüklenmeden önce kayıtlı temayı uygula (Flicker Önleme)
        (function() {
            const savedTheme = localStorage.getItem('pos_theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-mode');
            }
        })();

        if (window.tailwind) tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Segoe UI"', 'Inter', 'Outfit', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0f3ff',
                            100: '#e0e7ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            900: '#312e81',
                        }
                    },
                    keyframes: {
                        'toast-slide-in': {
                            '0%': { transform: 'translateX(120%) scale(0.9)', opacity: '0' },
                            '100%': { transform: 'translateX(0) scale(1)', opacity: '1' }
                        }
                    },
                    animation: {
                        'toast-slide-in': 'toast-slide-in 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards'
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #0b0c12;
            color: #f8fafc;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.3s ease;
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        .gradient-text {
            background: linear-gradient(135deg, #a855f7 0%, #6366f1 50%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .gradient-btn {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.35);
            transition: all 0.3s ease;
        }
        .gradient-btn:hover {
            box-shadow: 0 6px 25px rgba(99, 102, 241, 0.5);
            transform: translateY(-1px);
        }

        /* ==========================================================================
           ☀️ BEYAZ MOD (LIGHT MODE) HASSAS VE KUSURSUZ TASARIM SİSTEMİ
           ========================================================================== */
        html.light-mode,
        html.light-mode body {
            background-color: #f8fafc !important;
            color: #0f172a !important;
        }

        /* 1. Sayfa Kapsayıcıları Ve Ana Alanlar */
        html.light-mode div.min-h-screen,
        html.light-mode div.h-screen,
        html.light-mode div.flex-col.min-h-screen,
        html.light-mode #posMainWrapper {
            background-color: #f8fafc !important;
        }

        /* Dashboard Main kapsayıcısı şeffaf olmalı, dikdörtgen blok yaratmamalı */
        html.light-mode main {
            background-color: transparent !important;
        }

        /* 2. Tüm Koyu Hex Arka Plan Yüzeylerini Ve Panelleri Beyaza Çevir */
        html.light-mode [class~="bg-[#07090e]"],
        html.light-mode [class~="bg-[#0a0a0a]"],
        html.light-mode [class~="bg-[#0b0c10]"],
        html.light-mode [class~="bg-[#0b0c12]"],
        html.light-mode [class~="bg-[#0b0e18]"],
        html.light-mode [class~="bg-[#0c0e17]"],
        html.light-mode [class~="bg-[#0d0f18]"],
        html.light-mode [class~="bg-[#0d101a]"],
        html.light-mode [class~="bg-[#0d101a]/50"],
        html.light-mode [class~="bg-[#0e101b]"],
        html.light-mode [class~="bg-[#0e111d]"],
        html.light-mode [class~="bg-[#0e121d]"],
        html.light-mode [class~="bg-[#0f1117]"],
        html.light-mode [class~="bg-[#0f121d]"],
        html.light-mode [class~="bg-[#0f131f]/95"],
        html.light-mode [class~="bg-[#111523]"],
        html.light-mode [class~="bg-[#111524]"],
        html.light-mode [class~="bg-[#121522]"],
        html.light-mode [class~="bg-[#121522]/40"],
        html.light-mode [class~="bg-[#121522]/90"],
        html.light-mode [class~="bg-[#121524]"],
        html.light-mode [class~="bg-[#121525]"],
        html.light-mode [class~="bg-[#121626]"],
        html.light-mode [class~="bg-[#131625]"],
        html.light-mode [class~="bg-[#141620]"],
        html.light-mode [class~="bg-[#141724]"],
        html.light-mode [class~="bg-[#15192b]"],
        html.light-mode [class~="bg-[#161615]"],
        html.light-mode [class~="bg-[#161a2b]"],
        html.light-mode [class~="bg-[#161a2e]"],
        html.light-mode [class~="bg-[#161b2e]"],
        html.light-mode [class~="bg-[#170e13]"],
        html.light-mode [class~="bg-[#181a24]"],
        html.light-mode [class~="bg-[#191d2d]"],
        html.light-mode [class~="bg-[#191d2d]/60"],
        html.light-mode [class~="bg-[#191d2d]/80"],
        html.light-mode .bg-slate-950,
        html.light-mode .bg-slate-900,
        html.light-mode .bg-slate-900\/90,
        html.light-mode .bg-slate-900\/80,
        html.light-mode .bg-slate-900\/70,
        html.light-mode .bg-slate-900\/60,
        html.light-mode .bg-slate-900\/50,
        html.light-mode .bg-slate-900\/40,
        html.light-mode .bg-slate-900\/30,
        html.light-mode .bg-slate-900\/20 {
            background-color: #ffffff !important;
        }

        /* Üst Header, Nav ve Yan Menüler */
        html.light-mode header,
        html.light-mode nav,
        html.light-mode aside,
        html.light-mode #adisyonPanel {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
        }

        /* Dashboard Üst Header Şeffaf Olmalı */
        html.light-mode header.bg-transparent {
            background-color: transparent !important;
            border-color: transparent !important;
        }

        /* Dashboard Kategori Kartları (Masalar, Hızlı Satış, Paket Servis vb.) */
        html.light-mode main .grid > a {
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.06), 0 4px 6px -2px rgba(15, 23, 42, 0.02) !important;
            border-radius: 1.5rem !important;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        html.light-mode main .grid > a:hover {
            transform: translateY(-5px) !important;
            border-color: #6366f1 !important;
            box-shadow: 0 20px 35px -10px rgba(99, 102, 241, 0.22), 0 8px 10px -6px rgba(15, 23, 42, 0.05) !important;
        }

        html.light-mode main .grid > a span {
            color: #0f172a !important;
            font-weight: 700 !important;
        }

        /* İkincil Yumuşak Arka Planlar & Alt Yüzeyler */
        html.light-mode .bg-slate-800,
        html.light-mode .bg-slate-800\/90,
        html.light-mode .bg-slate-800\/80,
        html.light-mode .bg-slate-800\/60,
        html.light-mode .bg-slate-800\/50,
        html.light-mode [class*="bg-[#191d2d]"],
        html.light-mode [class*="bg-[#121522]/40"],
        html.light-mode [class*="bg-[#131625]"] {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
        }

        html.light-mode .bg-slate-800\/40,
        html.light-mode .bg-slate-800\/30 {
            background-color: #f8fafc !important;
        }

        /* Cam Kartlar Ve Paneller */
        html.light-mode .glass-panel {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(16px) !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05) !important;
        }

        html.light-mode .glass-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04) !important;
        }

        /* POS & Hızlı Satış Ürün Kartları */
        html.light-mode .product-item,
        html.light-mode .product-card {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04) !important;
            color: #0f172a !important;
        }
        html.light-mode .product-item:hover,
        html.light-mode .product-card:hover {
            background-color: #ffffff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 12px 28px -6px rgba(99, 102, 241, 0.2) !important;
        }
        html.light-mode .product-card .absolute.bottom-2 {
            background-color: rgba(255, 255, 255, 0.95) !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
            font-weight: 700 !important;
        }

        /* POS & Hızlı Satış Sol Aksiyon Butonları */
        html.light-mode #posMainWrapper button:not([class*="bg-indigo-600"]):not([class*="bg-emerald"]):not([class*="bg-rose"]):not([class*="bg-amber"]):not([class*="bg-violet"]):not([class*="bg-sky"]):not([class*="bg-cyan"]):not([class*="bg-orange"]),
        html.light-mode div[class*="w-20"] button:not([class*="bg-indigo-600"]):not([class*="bg-emerald"]):not([class*="bg-rose"]):not([class*="bg-amber"]):not([class*="bg-violet"]):not([class*="bg-sky"]):not([class*="bg-cyan"]):not([class*="bg-orange"]),
        html.light-mode div[class*="w-24"] button:not([class*="bg-indigo-600"]):not([class*="bg-emerald"]):not([class*="bg-rose"]):not([class*="bg-amber"]):not([class*="bg-violet"]):not([class*="bg-sky"]):not([class*="bg-cyan"]):not([class*="bg-orange"]),
        html.light-mode div[class*="w-20"] a,
        html.light-mode div[class*="w-24"] a {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05) !important;
        }
        html.light-mode div[class*="w-20"] button span,
        html.light-mode div[class*="w-24"] button span,
        html.light-mode div[class*="w-20"] a span,
        html.light-mode div[class*="w-24"] a span {
            color: #1e293b !important;
        }

        /* Alert Toast Bildirim Kutuları (Sağ Üst) */
        html.light-mode #toastContainer > div {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
            box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.15) !important;
        }
        html.light-mode #toastContainer h5,
        html.light-mode #toastContainer p {
            color: #0f172a !important;
        }
        html.light-mode #toastContainer button {
            color: #64748b !important;
        }
        html.light-mode #toastContainer button:hover {
            color: #0f172a !important;
        }

        /* 3. Masalar (Dining Table) Kart Stilleri */
        html.light-mode .hall-filter-btn {
            border-color: #cbd5e1 !important;
        }
        html.light-mode .hall-filter-btn:not(.bg-indigo-600) {
            background-color: #ffffff !important;
            color: #475569 !important;
        }

        /* Boş Masalar */
        html.light-mode [class*="available"] {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            color: #0f172a !important;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03) !important;
        }
        html.light-mode [class*="available"]:hover {
            background-color: #f8fafc !important;
            border-color: #10b981 !important;
        }

        /* Dolu Masalar (Gradient) */
        html.light-mode [class*="from-indigo-950"] {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%) !important;
            border-color: #6366f1 !important;
            color: #1e1b4b !important;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.15) !important;
        }
        html.light-mode [class*="from-indigo-950"] * {
            color: #1e1b4b !important;
        }
        html.light-mode [class*="from-indigo-950"] .text-indigo-400 {
            color: #4338ca !important;
        }

        /* Rezerve Masalar (Gradient) */
        html.light-mode [class*="from-rose-950"] {
            background: linear-gradient(135deg, #ffe4e6 0%, #fecdd3 100%) !important;
            border-color: #f43f5e !important;
            color: #881337 !important;
        }
        html.light-mode [class*="from-rose-950"] * {
            color: #881337 !important;
        }

        /* Ödeme Yöntemi Kartları (Payment Modal) */
        html.light-mode .payment-method-card {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }
        html.light-mode .payment-method-card:hover {
            border-color: #94a3b8 !important;
        }

        /* 4. Çerçeve Ve Ayırıcı Çizgiler */
        html.light-mode .border-slate-800,
        html.light-mode .border-slate-700,
        html.light-mode .border-slate-800\/80,
        html.light-mode .border-slate-800\/60,
        html.light-mode .border-slate-800\/50,
        html.light-mode .border-slate-800\/40,
        html.light-mode .divide-slate-800 > :not([hidden]) ~ :not([hidden]),
        html.light-mode .divide-slate-800\/80 > :not([hidden]) ~ :not([hidden]) {
            border-color: #e2e8f0 !important;
        }

        /* 5. Tipografi Ve Metin Renkleri */
        html.light-mode .text-white,
        html.light-mode .text-slate-100,
        html.light-mode .text-slate-200 {
            color: #0f172a !important;
        }

        html.light-mode .text-slate-300,
        html.light-mode .text-slate-400 {
            color: #334155 !important;
        }

        html.light-mode .text-slate-500,
        html.light-mode .text-slate-600 {
            color: #64748b !important;
        }

        /* Soluk Renkli Metinlerin ve Vurguların Beyaz Modda Yüksek Kontrastı */
        html.light-mode .text-indigo-400,
        html.light-mode .text-indigo-300,
        html.light-mode .text-indigo-200 {
            color: #4338ca !important;
        }
        html.light-mode .text-amber-400,
        html.light-mode .text-amber-300,
        html.light-mode .text-amber-200 {
            color: #b45309 !important;
        }
        html.light-mode .text-emerald-400,
        html.light-mode .text-emerald-400\/90,
        html.light-mode .text-emerald-300,
        html.light-mode .text-emerald-200 {
            color: #047857 !important;
        }
        html.light-mode .text-rose-400,
        html.light-mode .text-rose-400\/80,
        html.light-mode .text-rose-300,
        html.light-mode .text-rose-200 {
            color: #be123c !important;
        }
        html.light-mode .text-sky-400,
        html.light-mode .text-sky-300,
        html.light-mode .text-sky-200,
        html.light-mode .text-sky-100\/90 {
            color: #0369a1 !important;
        }
        html.light-mode .text-cyan-400,
        html.light-mode .text-cyan-400\/80,
        html.light-mode .text-cyan-300 {
            color: #0e7490 !important;
        }
        html.light-mode .text-violet-400,
        html.light-mode .text-violet-300 {
            color: #6d28d9 !important;
        }
        html.light-mode .text-fuchsia-400,
        html.light-mode .text-fuchsia-300 {
            color: #a21caf !important;
        }
        html.light-mode .text-purple-400,
        html.light-mode .text-purple-300 {
            color: #6b21a8 !important;
        }
        html.light-mode .text-orange-400,
        html.light-mode .text-orange-300 {
            color: #c2410c !important;
        }
        html.light-mode .text-blue-400,
        html.light-mode .text-blue-300,
        html.light-mode .text-blue-200 {
            color: #1d4ed8 !important;
        }
        html.light-mode .text-red-400,
        html.light-mode .text-red-300 {
            color: #b91c1c !important;
        }
        html.light-mode .text-teal-400,
        html.light-mode .text-teal-300 {
            color: #0f766e !important;
        }
        html.light-mode .text-yellow-400,
        html.light-mode .text-yellow-300 {
            color: #a16207 !important;
        }
        html.light-mode .text-pink-400,
        html.light-mode .text-pink-300 {
            color: #be185d !important;
        }

        /* İç Sayfa Kartları (Mutfak, Paket Servis, Masalar) */
        html.light-mode .kitchen-card,
        html.light-mode .delivery-card,
        html.light-mode .table-card {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.04) !important;
        }

        /* Modallar ve Karartma Katmanları */
        html.light-mode .fixed.bg-slate-950\/85,
        html.light-mode .fixed.bg-slate-950\/80 {
            background-color: rgba(15, 23, 42, 0.45) !important;
        }

        /* LOGO BEYAZ MOD KONTRASTI (Orijinal logoyu bozma) */
        html.light-mode header img[alt*="ADİSYON"],
        html.light-mode header img[src*="logo"] {
            filter: none !important;
        }

        .brand-logo-dark { display: block; }
        .brand-logo-light { display: none; }
        html.light-mode .brand-logo-dark { display: none !important; }
        html.light-mode .brand-logo-light { display: block !important; }

        /* 7. Form Girdi Alanları */
        html.light-mode input[type="text"],
        html.light-mode input[type="number"],
        html.light-mode input[type="password"],
        html.light-mode input[type="date"],
        html.light-mode input[type="search"],
        html.light-mode input[type="email"],
        html.light-mode input[type="url"],
        html.light-mode input[type="tel"],
        html.light-mode input[type="time"],
        html.light-mode input[type="datetime-local"],
        html.light-mode input[type="file"],
        html.light-mode select,
        html.light-mode textarea {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }

        html.light-mode input::placeholder,
        html.light-mode textarea::placeholder {
            color: #94a3b8 !important;
        }

        /* Tarayıcı otomatik doldurmasının açık temada mavi/sarı yüzey bırakmasını önle. */
        html.light-mode input:-webkit-autofill,
        html.light-mode input:-webkit-autofill:hover,
        html.light-mode input:-webkit-autofill:focus,
        html.light-mode textarea:-webkit-autofill,
        html.light-mode select:-webkit-autofill {
            -webkit-text-fill-color: #0f172a !important;
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            box-shadow: 0 0 0 1000px #ffffff inset !important;
            caret-color: #0f172a !important;
        }

        /* 8. Tablolar */
        html.light-mode table thead tr,
        html.light-mode table th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            border-color: #e2e8f0 !important;
        }

        html.light-mode table td {
            border-color: #f1f5f9 !important;
            color: #0f172a !important;
        }

        html.light-mode table tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* 9. Modallar Ve Pencereler */
        html.light-mode [id*="Modal"] > div,
        html.light-mode .modal-card {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2) !important;
            color: #0f172a !important;
        }

        /* 10. Yumuşak Vurgu & Alert Kutuları */
        html.light-mode .bg-indigo-950\/60,
        html.light-mode .bg-indigo-950\/40,
        html.light-mode .bg-indigo-500\/10,
        html.light-mode .bg-indigo-500\/20 {
            background-color: #e0e7ff !important;
            border-color: #c7d2fe !important;
            color: #3730a3 !important;
        }
        html.light-mode .bg-indigo-950\/60 .text-white,
        html.light-mode .bg-indigo-950\/60 .text-indigo-300,
        html.light-mode .bg-indigo-500\/10 .text-indigo-400 {
            color: #3730a3 !important;
        }

        html.light-mode .bg-emerald-950\/60,
        html.light-mode .bg-emerald-950\/40,
        html.light-mode .bg-emerald-500\/10,
        html.light-mode .bg-emerald-500\/20 {
            background-color: #d1fae5 !important;
            border-color: #a7f3d0 !important;
            color: #065f46 !important;
        }

        html.light-mode .bg-rose-950\/70,
        html.light-mode .bg-rose-950\/40,
        html.light-mode .bg-rose-500\/10,
        html.light-mode .bg-rose-500\/20 {
            background-color: #ffe4e6 !important;
            border-color: #fecdd3 !important;
            color: #9f1239 !important;
        }

        html.light-mode .bg-amber-950\/60,
        html.light-mode .bg-amber-950\/40,
        html.light-mode .bg-amber-950\/30,
        html.light-mode .bg-amber-500\/10,
        html.light-mode .bg-amber-500\/20 {
            background-color: #fef3c7 !important;
            border-color: #fde68a !important;
            color: #92400e !important;
        }

        html.light-mode .bg-sky-950\/60,
        html.light-mode .bg-sky-950\/40,
        html.light-mode .bg-sky-500\/10,
        html.light-mode .bg-sky-500\/20 {
            background-color: #e0f2fe !important;
            border-color: #bae6fd !important;
            color: #075985 !important;
        }

        /* 11. Custom Scrollbars */
        html.light-mode * {
            scrollbar-color: #cbd5e1 #f1f5f9 !important;
        }
        html.light-mode ::-webkit-scrollbar-track,
        html.light-mode .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9 !important;
        }
        html.light-mode ::-webkit-scrollbar-thumb,
        html.light-mode .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1 !important;
            border-color: #e2e8f0 !important;
        }

        /* 12. Hover Efektleri */
        html.light-mode .hover\:bg-slate-800:hover,
        html.light-mode .hover\:bg-slate-800\/80:hover,
        html.light-mode .hover\:bg-slate-800\/60:hover,
        html.light-mode .hover\:bg-slate-800\/30:hover,
        html.light-mode .hover\:bg-slate-900:hover,
        html.light-mode .hover\:bg-slate-900\/70:hover,
        html.light-mode .hover\:bg-slate-900\/60:hover,
        html.light-mode .hover\:bg-slate-900\/40:hover {
            background-color: #e2e8f0 !important;
        }

        /* 13. Kalan sayfa uyumlulukları
           Özel koyu yüzeyler yukarıda tek tek tanımlıdır. Böylece #059669 gibi
           marka/aksiyon renkleri yanlışlıkla beyaza dönüşmez. */
        html.light-mode {
            color-scheme: light;
        }

        html.light-mode .bg-slate-700,
        html.light-mode .bg-slate-600,
        html.light-mode .bg-gray-900,
        html.light-mode .bg-gray-800,
        html.light-mode .bg-gray-800\/40,
        html.light-mode .bg-gray-700 {
            background-color: #f1f5f9 !important;
            border-color: #dbe3ee !important;
        }

        html.light-mode .border-slate-900,
        html.light-mode .border-slate-700\/80,
        html.light-mode .border-slate-700\/60,
        html.light-mode .border-slate-700\/50,
        html.light-mode .border-slate-700\/30,
        html.light-mode .border-gray-800,
        html.light-mode .border-gray-700,
        html.light-mode .border-gray-700\/80,
        html.light-mode .border-gray-600,
        html.light-mode .divide-slate-800\/70 > :not([hidden]) ~ :not([hidden]),
        html.light-mode .divide-slate-800\/60 > :not([hidden]) ~ :not([hidden]),
        html.light-mode .divide-gray-800 > :not([hidden]) ~ :not([hidden]),
        html.light-mode .divide-gray-800\/60 > :not([hidden]) ~ :not([hidden]) {
            border-color: #e2e8f0 !important;
        }

        html.light-mode .border-white\/5,
        html.light-mode .border-white\/10 {
            border-color: #e2e8f0 !important;
        }

        html.light-mode .text-gray-100,
        html.light-mode .text-gray-200,
        html.light-mode .text-gray-300 {
            color: #1e293b !important;
        }

        html.light-mode .text-gray-400,
        html.light-mode .text-gray-500,
        html.light-mode .text-gray-600 {
            color: #475569 !important;
        }

        html.light-mode input[type="email"],
        html.light-mode input[type="tel"],
        html.light-mode input[type="time"],
        html.light-mode input[type="url"] {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }

        html.light-mode input:disabled,
        html.light-mode select:disabled,
        html.light-mode textarea:disabled {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            opacity: 1 !important;
        }

        html.light-mode select option {
            background-color: #ffffff;
            color: #0f172a;
        }

        /* Modal dışındaki siyah bilgi yüzeylerini açık mod kartına çevir.
           Fixed inset overlay'ler karartma katmanı olarak korunur. */
        html.light-mode .bg-black\/30:not(.fixed),
        html.light-mode .bg-black\/40:not(.fixed),
        html.light-mode .bg-black\/60:not(.fixed) {
            background-color: #f1f5f9 !important;
            border-color: #e2e8f0 !important;
            color: #1e293b !important;
        }

        html.light-mode .bg-white\/10,
        html.light-mode .bg-white\/20 {
            background-color: #e2e8f0 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
        }

        html.light-mode .bg-slate-950\/60:not(.fixed),
        html.light-mode .bg-slate-950\/80:not(.fixed) {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
            color: #334155 !important;
        }

        html.light-mode .bg-indigo-600\/20,
        html.light-mode .bg-indigo-600\/30,
        html.light-mode .bg-indigo-900\/30,
        html.light-mode .bg-indigo-900\/60,
        html.light-mode .bg-indigo-950\/80 {
            background-color: #e0e7ff !important;
            border-color: #c7d2fe !important;
            color: #3730a3 !important;
        }

        html.light-mode .bg-emerald-600\/20,
        html.light-mode .bg-emerald-600\/30,
        html.light-mode .bg-emerald-900\/40,
        html.light-mode .bg-emerald-950\/20,
        html.light-mode .bg-emerald-950\/30,
        html.light-mode .bg-emerald-950\/80,
        html.light-mode .bg-emerald-950\/90 {
            background-color: #d1fae5 !important;
            border-color: #a7f3d0 !important;
            color: #065f46 !important;
        }

        html.light-mode .bg-rose-500\/15,
        html.light-mode .bg-rose-500\/30,
        html.light-mode .bg-rose-600\/20,
        html.light-mode .bg-rose-600\/30,
        html.light-mode .bg-rose-600\/40,
        html.light-mode .bg-rose-900\/60,
        html.light-mode .bg-rose-950\/30,
        html.light-mode .bg-rose-950\/80,
        html.light-mode .bg-rose-950\/90 {
            background-color: #ffe4e6 !important;
            border-color: #fecdd3 !important;
            color: #9f1239 !important;
        }

        html.light-mode .bg-amber-600\/20,
        html.light-mode .bg-amber-600\/30,
        html.light-mode .bg-amber-600\/40,
        html.light-mode .bg-amber-900\/40,
        html.light-mode .bg-amber-950\/80 {
            background-color: #fef3c7 !important;
            border-color: #fde68a !important;
            color: #92400e !important;
        }

        html.light-mode .bg-sky-600\/30,
        html.light-mode .bg-sky-900\/40,
        html.light-mode .bg-sky-950\/30 {
            background-color: #e0f2fe !important;
            border-color: #bae6fd !important;
            color: #075985 !important;
        }

        html.light-mode .bg-cyan-500\/10,
        html.light-mode .bg-cyan-600\/20,
        html.light-mode .bg-cyan-600\/30 {
            background-color: #cffafe !important;
            border-color: #a5f3fc !important;
            color: #155e75 !important;
        }

        html.light-mode .bg-purple-500\/10,
        html.light-mode .bg-purple-500\/20,
        html.light-mode .bg-purple-600\/20,
        html.light-mode .bg-purple-600\/30,
        html.light-mode .bg-purple-900\/30 {
            background-color: #f3e8ff !important;
            border-color: #e9d5ff !important;
            color: #6b21a8 !important;
        }

        html.light-mode .bg-violet-500\/10,
        html.light-mode .bg-violet-500\/20,
        html.light-mode .bg-violet-600\/30 {
            background-color: #ede9fe !important;
            border-color: #ddd6fe !important;
            color: #5b21b6 !important;
        }

        html.light-mode .bg-teal-500\/10,
        html.light-mode .bg-teal-500\/15,
        html.light-mode .bg-teal-500\/30 {
            background-color: #ccfbf1 !important;
            border-color: #99f6e4 !important;
            color: #115e59 !important;
        }

        html.light-mode .bg-orange-500\/10,
        html.light-mode .bg-orange-500\/20,
        html.light-mode .bg-orange-600\/30 {
            background-color: #ffedd5 !important;
            border-color: #fed7aa !important;
            color: #9a3412 !important;
        }

        html.light-mode .bg-yellow-500\/10,
        html.light-mode .bg-yellow-500\/20,
        html.light-mode .bg-yellow-500\/30 {
            background-color: #fef9c3 !important;
            border-color: #fef08a !important;
            color: #854d0e !important;
        }

        html.light-mode .bg-blue-500\/20 {
            background-color: #dbeafe !important;
            border-color: #bfdbfe !important;
            color: #1e40af !important;
        }

        html.light-mode .bg-red-500\/10 {
            background-color: #fee2e2 !important;
            border-color: #fecaca !important;
            color: #991b1b !important;
        }

        html.light-mode .bg-fuchsia-500\/10 {
            background-color: #fae8ff !important;
            border-color: #f5d0fe !important;
            color: #86198f !important;
        }

        /* Dolu aksiyon renklerinde ve güçlü gradient butonlarda beyaz yazıyı koru. */
        html.light-mode [class~="bg-indigo-500"],
        html.light-mode [class~="bg-indigo-600"],
        html.light-mode [class~="bg-emerald-500"],
        html.light-mode [class~="bg-emerald-600"],
        html.light-mode [class~="bg-rose-500"],
        html.light-mode [class~="bg-rose-600"],
        html.light-mode [class~="bg-sky-500"],
        html.light-mode [class~="bg-sky-600"],
        html.light-mode [class~="bg-cyan-500"],
        html.light-mode [class~="bg-cyan-600"],
        html.light-mode [class~="bg-teal-500"],
        html.light-mode [class~="bg-teal-600"],
        html.light-mode [class~="bg-violet-500"],
        html.light-mode [class~="bg-violet-600"],
        html.light-mode [class~="bg-purple-500"],
        html.light-mode [class~="bg-purple-600"],
        html.light-mode [class~="bg-fuchsia-500"],
        html.light-mode [class~="bg-fuchsia-600"],
        html.light-mode [class~="bg-orange-500"],
        html.light-mode [class~="bg-orange-600"],
        html.light-mode [class~="bg-amber-500"],
        html.light-mode [class~="bg-amber-600"],
        html.light-mode [class~="bg-pink-500"],
        html.light-mode [class~="bg-yellow-500"],
        html.light-mode [class~="bg-yellow-600"],
        html.light-mode [class~="bg-[#059669]"],
        html.light-mode [class~="bg-[#10b981]"] {
            color: #ffffff !important;
        }

        html.light-mode [class~="bg-indigo-500"] *,
        html.light-mode [class~="bg-indigo-600"] *,
        html.light-mode [class~="bg-emerald-500"] *,
        html.light-mode [class~="bg-emerald-600"] *,
        html.light-mode [class~="bg-rose-500"] *,
        html.light-mode [class~="bg-rose-600"] *,
        html.light-mode [class~="bg-sky-500"] *,
        html.light-mode [class~="bg-sky-600"] *,
        html.light-mode [class~="bg-cyan-500"] *,
        html.light-mode [class~="bg-cyan-600"] *,
        html.light-mode [class~="bg-teal-500"] *,
        html.light-mode [class~="bg-teal-600"] *,
        html.light-mode [class~="bg-violet-500"] *,
        html.light-mode [class~="bg-violet-600"] *,
        html.light-mode [class~="bg-purple-500"] *,
        html.light-mode [class~="bg-purple-600"] *,
        html.light-mode [class~="bg-fuchsia-500"] *,
        html.light-mode [class~="bg-fuchsia-600"] *,
        html.light-mode [class~="bg-orange-500"] *,
        html.light-mode [class~="bg-orange-600"] *,
        html.light-mode [class~="bg-amber-500"] *,
        html.light-mode [class~="bg-amber-600"] *,
        html.light-mode [class~="bg-pink-500"] *,
        html.light-mode [class~="bg-yellow-500"] *,
        html.light-mode [class~="bg-yellow-600"] *,
        html.light-mode [class~="bg-[#059669]"] *,
        html.light-mode [class~="bg-[#10b981]"] * {
            color: #ffffff !important;
        }

        html.light-mode [class*="bg-gradient-"][class~="from-indigo-500"],
        html.light-mode [class*="bg-gradient-"][class~="from-indigo-600"],
        html.light-mode [class*="bg-gradient-"][class~="from-emerald-500"],
        html.light-mode [class*="bg-gradient-"][class~="from-emerald-600"],
        html.light-mode [class*="bg-gradient-"][class~="from-rose-600"],
        html.light-mode [class*="bg-gradient-"][class~="from-cyan-600"],
        html.light-mode [class*="bg-gradient-"][class~="from-fuchsia-500"],
        html.light-mode [class*="bg-gradient-"][class~="from-fuchsia-600"] {
            color: #ffffff !important;
        }

        html.light-mode [class*="bg-gradient-"][class~="from-indigo-500"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-indigo-600"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-emerald-500"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-emerald-600"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-rose-600"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-cyan-600"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-fuchsia-500"] *,
        html.light-mode [class*="bg-gradient-"][class~="from-fuchsia-600"] * {
            color: #ffffff !important;
        }

        /* Hover durumlarında açık temanın okunabilirliğini ve mevcut renk dilini koru. */
        html.light-mode .hover\:bg-slate-700:hover,
        html.light-mode .hover\:bg-gray-700:hover,
        html.light-mode .hover\:bg-gray-800:hover {
            background-color: #e2e8f0 !important;
        }

        html.light-mode .hover\:text-white:hover {
            color: #0f172a !important;
        }

        html.light-mode [class~="hover:bg-indigo-500"]:hover,
        html.light-mode [class~="hover:bg-indigo-600"]:hover,
        html.light-mode [class~="hover:bg-emerald-500"]:hover,
        html.light-mode [class~="hover:bg-emerald-600"]:hover,
        html.light-mode [class~="hover:bg-rose-500"]:hover,
        html.light-mode [class~="hover:bg-rose-600"]:hover,
        html.light-mode [class~="hover:bg-sky-500"]:hover,
        html.light-mode [class~="hover:bg-cyan-500"]:hover,
        html.light-mode [class~="hover:bg-cyan-600"]:hover,
        html.light-mode [class~="hover:bg-teal-500"]:hover,
        html.light-mode [class~="hover:bg-teal-600"]:hover,
        html.light-mode [class~="hover:bg-violet-500"]:hover,
        html.light-mode [class~="hover:bg-purple-500"]:hover,
        html.light-mode [class~="hover:bg-fuchsia-500"]:hover,
        html.light-mode [class~="hover:bg-orange-500"]:hover,
        html.light-mode [class~="hover:bg-amber-400"]:hover,
        html.light-mode [class~="hover:bg-amber-500"]:hover,
        html.light-mode [class~="hover:bg-amber-600"]:hover,
        html.light-mode [class~="hover:bg-yellow-500"]:hover {
            color: #ffffff !important;
        }

        html.light-mode [class~="hover:bg-indigo-600/30"]:hover,
        html.light-mode [class~="hover:bg-indigo-900/60"]:hover {
            background-color: #c7d2fe !important;
            color: #3730a3 !important;
        }

        html.light-mode [class~="hover:bg-emerald-500/10"]:hover,
        html.light-mode [class~="hover:bg-emerald-500/20"]:hover,
        html.light-mode [class~="hover:bg-emerald-600/30"]:hover,
        html.light-mode [class~="hover:bg-emerald-900/40"]:hover {
            background-color: #a7f3d0 !important;
            color: #065f46 !important;
        }

        html.light-mode [class~="hover:bg-rose-500/10"]:hover,
        html.light-mode [class~="hover:bg-rose-500/20"]:hover,
        html.light-mode [class~="hover:bg-rose-500/30"]:hover,
        html.light-mode [class~="hover:bg-rose-600/30"]:hover,
        html.light-mode [class~="hover:bg-rose-600/40"]:hover,
        html.light-mode [class~="hover:bg-rose-900/60"]:hover {
            background-color: #fecdd3 !important;
            color: #9f1239 !important;
        }

        html.light-mode [class~="hover:bg-amber-500/10"]:hover,
        html.light-mode [class~="hover:bg-amber-500/20"]:hover,
        html.light-mode [class~="hover:bg-amber-600/30"]:hover,
        html.light-mode [class~="hover:bg-amber-600/40"]:hover,
        html.light-mode [class~="hover:bg-amber-900/40"]:hover,
        html.light-mode [class~="hover:bg-amber-950/30"]:hover {
            background-color: #fde68a !important;
            color: #92400e !important;
        }

        html.light-mode [class~="hover:bg-sky-500/10"]:hover,
        html.light-mode [class~="hover:bg-sky-500/20"]:hover,
        html.light-mode [class~="hover:bg-sky-600/30"]:hover,
        html.light-mode [class~="hover:bg-sky-900/40"]:hover {
            background-color: #bae6fd !important;
            color: #075985 !important;
        }

        html.light-mode [class~="hover:bg-cyan-600/30"]:hover {
            background-color: #a5f3fc !important;
            color: #155e75 !important;
        }

        html.light-mode [class~="hover:bg-purple-600/30"]:hover {
            background-color: #e9d5ff !important;
            color: #6b21a8 !important;
        }

        html.light-mode [class~="hover:bg-violet-600/30"]:hover {
            background-color: #ddd6fe !important;
            color: #5b21b6 !important;
        }

        html.light-mode [class~="hover:bg-teal-500/30"]:hover {
            background-color: #99f6e4 !important;
            color: #115e59 !important;
        }

        html.light-mode [class~="hover:bg-orange-600/30"]:hover {
            background-color: #fed7aa !important;
            color: #9a3412 !important;
        }

        html.light-mode [class~="hover:bg-yellow-500/30"]:hover {
            background-color: #fef08a !important;
            color: #854d0e !important;
        }

        /* Grup hover kullanılan dashboard ikonlarında vurgu kontrastını koru. */
        html.light-mode .group:hover [class~="group-hover:bg-indigo-600"] {
            background-color: #4f46e5 !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-emerald-500"],
        html.light-mode .group:hover [class~="group-hover:bg-emerald-600"] {
            background-color: #059669 !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-rose-500"] {
            background-color: #f43f5e !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-sky-500"] {
            background-color: #0ea5e9 !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-cyan-500"] {
            background-color: #06b6d4 !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-amber-500"] {
            background-color: #f59e0b !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-violet-500"] {
            background-color: #8b5cf6 !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-purple-500"] {
            background-color: #a855f7 !important;
            color: #ffffff !important;
        }

        html.light-mode .group:hover [class~="group-hover:bg-fuchsia-500"] {
            background-color: #d946ef !important;
            color: #ffffff !important;
        }

        /* Switch ve tuş durumları: temel açık yüzey kuralları state renklerini ezmesin. */
        html.light-mode .peer:checked ~ .peer-checked\:bg-indigo-600 {
            background-color: #4f46e5 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-emerald-500 {
            background-color: #10b981 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-rose-600 {
            background-color: #e11d48 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-amber-500 {
            background-color: #f59e0b !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-yellow-500 {
            background-color: #eab308 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-teal-600 {
            background-color: #0d9488 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-orange-500 {
            background-color: #f97316 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-pink-500 {
            background-color: #ec4899 !important;
        }

        html.light-mode .peer:checked ~ .peer-checked\:bg-purple-500 {
            background-color: #a855f7 !important;
        }

        html.light-mode .active\:bg-indigo-600:active {
            background-color: #4f46e5 !important;
            color: #ffffff !important;
        }
        /* Ortak form/modal dayanıklılığı: uzun formlar ekrandan taşmaz, mobil gridler daralmaz. */
        html.modal-open,
        body.modal-open {
            overflow: hidden !important;
        }

        .app-modal {
            align-items: flex-start;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .app-modal-panel {
            max-height: calc(100vh - 2rem);
            max-height: calc(100dvh - 2rem);
            min-width: 0;
            overflow-x: hidden;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .app-form-grid > *,
        form input,
        form select,
        form textarea {
            min-width: 0;
            max-width: 100%;
        }

        input[type="file"] {
            min-width: 0;
            max-width: 100%;
        }

        @media (min-width: 640px) and (min-height: 700px) {
            .app-modal {
                align-items: center;
            }
        }

        html.light-mode .app-modal-panel,
        html.light-mode [id$="-modal"] > .app-modal-panel {
            background-color: #ffffff !important;
            border-color: #cbd5e1 !important;
            color: #0f172a !important;
        }
    </style>
    @yield('styles')
</head>
<body class="h-full antialiased selection:bg-indigo-500 selection:text-white flex flex-col min-h-screen relative">

    <div id="appStyleLoader" role="status" aria-live="polite">
        <div>
            <div id="appStyleLoaderSpinner"></div>
            <div id="appStyleLoaderText">Arayüz hazırlanıyor...</div>
        </div>
    </div>
    <div id="appStyleProbe" class="flex" style="position:absolute;left:-9999px" aria-hidden="true"></div>
    <script>
        (() => {
            const startedAt = Date.now();
            const finishWhenReady = () => {
                const probe = document.getElementById('appStyleProbe');
                if (probe && getComputedStyle(probe).display === 'flex') {
                    document.documentElement.classList.remove('app-booting');
                    document.documentElement.classList.add('app-ready');
                    probe.remove();
                    return;
                }

                if (Date.now() - startedAt < 12000) {
                    setTimeout(finishWhenReady, 100);
                    return;
                }

                const text = document.getElementById('appStyleLoaderText');
                const spinner = document.getElementById('appStyleLoaderSpinner');
                if (spinner) spinner.style.display = 'none';
                if (text) text.innerHTML = 'Arayüz yüklenemedi.<br><button type="button" onclick="location.reload()" style="margin-top:12px;padding:9px 16px;border:0;border-radius:10px;background:#4f46e5;color:white;font-weight:700;cursor:pointer">Yeniden Dene</button>';
            };
            window.addEventListener('load', finishWhenReady, { once: true });
        })();
    </script>

    <!-- 🌐 GLOBAL ALERT TOAST CONTAINER -->
    <div id="toastContainer" class="fixed top-5 right-5 z-[9999] flex flex-col gap-3 max-w-sm sm:max-w-md pointer-events-none"></div>

    @yield('content')

    <!-- GLOBAL TOAST NOTIFICATION SCRIPT -->
    <script>
        window.openAppModal = function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('modal-open');
            document.body.classList.add('modal-open');

            requestAnimationFrame(() => {
                modal.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus({ preventScroll: true });
            });
        };

        window.closeAppModal = function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            if (!document.querySelector('.app-modal:not(.hidden)')) {
                document.documentElement.classList.remove('modal-open');
                document.body.classList.remove('modal-open');
            }
        };

        document.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            const openModal = [...document.querySelectorAll('.app-modal:not(.hidden)')].pop();
            if (openModal?.id) window.closeAppModal(openModal.id);
        });

        document.addEventListener('click', event => {
            const overlay = event.target.closest('.app-modal[data-close-on-overlay="true"]');
            if (overlay && event.target === overlay && overlay.id) window.closeAppModal(overlay.id);
        });

        window.escapeHtml = function(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[character]);
        };

        window.safeImageUrl = function(value) {
            const candidate = String(value ?? '');
            if (/^data:image\/(?:png|jpeg|webp|gif);base64,/i.test(candidate)) {
                return candidate;
            }

            try {
                const parsed = new URL(candidate, window.location.origin);
                return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
            } catch {
                return '';
            }
        };

        window.showToast = function(message, type = 'success', duration = 4000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toastId = 'toast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);

            const config = {
                success: {
                    bg: 'bg-slate-900/95 border-emerald-500/40 text-slate-100 shadow-emerald-950/30',
                    iconBg: 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30',
                    icon: 'fi-rr-check-circle',
                    progress: 'bg-emerald-500',
                    title: 'Başarılı'
                },
                danger: {
                    bg: 'bg-slate-900/95 border-rose-500/40 text-slate-100 shadow-rose-950/30',
                    iconBg: 'bg-rose-500/20 text-rose-400 border border-rose-500/30',
                    icon: 'fi-rr-cross-circle',
                    progress: 'bg-rose-500',
                    title: 'Hata'
                },
                error: {
                    bg: 'bg-slate-900/95 border-rose-500/40 text-slate-100 shadow-rose-950/30',
                    iconBg: 'bg-rose-500/20 text-rose-400 border border-rose-500/30',
                    icon: 'fi-rr-cross-circle',
                    progress: 'bg-rose-500',
                    title: 'Hata'
                },
                warning: {
                    bg: 'bg-slate-900/95 border-amber-500/40 text-slate-100 shadow-amber-950/30',
                    iconBg: 'bg-amber-500/20 text-amber-400 border border-amber-500/30',
                    icon: 'fi-rr-exclamation',
                    progress: 'bg-amber-500',
                    title: 'Uyarı'
                },
                info: {
                    bg: 'bg-slate-900/95 border-sky-500/40 text-slate-100 shadow-sky-950/30',
                    iconBg: 'bg-sky-500/20 text-sky-400 border border-sky-500/30',
                    icon: 'fi-rr-info',
                    progress: 'bg-sky-500',
                    title: 'Bilgi'
                }
            };

            const style = config[type] || config.info;

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `pointer-events-auto flex flex-col overflow-hidden rounded-2xl border ${style.bg} shadow-2xl backdrop-blur-md transition-all duration-300 transform translate-x-0 animate-toast-slide-in`;

            toast.innerHTML = `
                <div class="flex items-start gap-3 p-4">
                    <div class="w-9 h-9 rounded-xl ${style.iconBg} flex items-center justify-center shrink-0 mt-0.5">
                        <i class="fi ${style.icon} text-base"></i>
                    </div>
                    <div class="flex-1 min-w-0 pr-2">
                        <h5 class="text-xs font-black tracking-wider uppercase text-slate-200">${style.title}</h5>
                        <p class="text-xs font-medium text-slate-300 mt-1 leading-relaxed break-words">${window.escapeHtml(message)}</p>
                    </div>
                    <button onclick="dismissToast('${toastId}')" class="text-slate-500 hover:text-white p-1 rounded-lg transition shrink-0 cursor-pointer">
                        <i class="fi fi-rr-cross text-xs"></i>
                    </button>
                </div>
                <div class="w-full bg-slate-800/80 h-1">
                    <div id="${toastId}-progress" class="${style.progress} h-1 transition-all ease-linear" style="width: 100%; transition-duration: ${duration}ms;"></div>
                </div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                const bar = document.getElementById(`${toastId}-progress`);
                if (bar) bar.style.width = '0%';
            }, 50);

            setTimeout(() => {
                dismissToast(toastId);
            }, duration);
        };

        window.dismissToast = function(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('opacity-0', 'translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }
        };

        window.showAlert = window.showToast;

        // BEYAZ MOD (LIGHT THEME) / KARANLIK MOD SİSTEMİ
        window.toggleTheme = function() {
            const isLight = document.documentElement.classList.toggle('light-mode');
            document.body.classList.toggle('light-mode', isLight);
            localStorage.setItem('pos_theme', isLight ? 'light' : 'dark');
            updateThemeUI(isLight);
            if (typeof showToast === 'function') {
                showToast(isLight ? '☀️ Beyaz (Aydınlık) Mod Aktif Edildi' : '🌙 Karanlık Mod Aktif Edildi', 'info');
            }
        };

        function updateThemeUI(isLight) {
            const icons = document.querySelectorAll('.theme-toggle-icon');
            const texts = document.querySelectorAll('.theme-toggle-text');
            const switchBgs = document.querySelectorAll('.theme-switch-bg');
            const switchDots = document.querySelectorAll('.theme-switch-dot');

            icons.forEach(el => {
                el.className = isLight ? 'fi fi-rr-sun text-amber-500 text-xs theme-toggle-icon' : 'fi fi-rr-moon text-indigo-400 text-xs theme-toggle-icon';
            });
            texts.forEach(el => {
                el.innerText = isLight ? 'Beyaz Mod' : 'Karanlık Mod';
            });

            switchBgs.forEach(el => {
                if (isLight) {
                    el.classList.remove('bg-indigo-600');
                    el.classList.add('bg-amber-500');
                } else {
                    el.classList.remove('bg-amber-500');
                    el.classList.add('bg-indigo-600');
                }
            });

            switchDots.forEach(el => {
                if (isLight) {
                    el.classList.remove('translate-x-4');
                    el.classList.add('translate-x-0');
                } else {
                    el.classList.remove('translate-x-0');
                    el.classList.add('translate-x-4');
                }
            });

            // Beyaz mod için özel tasarlanan logoyu kullan
            const logoImgs = document.querySelectorAll('img[alt*="ADİSYON"], img[src*="logo.png"], img[src*="logo-light.png"]');
            const lightLogoUrl = "{{ asset('assets/images/logo-light.png') }}";
            const darkLogoUrl = "{{ asset('assets/images/logo.png') }}";
            logoImgs.forEach(img => {
                img.src = isLight ? lightLogoUrl : darkLogoUrl;
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const isLight = localStorage.getItem('pos_theme') === 'light';
            if (isLight) {
                document.documentElement.classList.add('light-mode');
                document.body.classList.add('light-mode');
            }
            updateThemeUI(isLight);
        });
    </script>

    <!-- LARAVEL SESSION FLASH MESSAGES -->
    @if(session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('success')), 'success'));</script>
    @endif
    @if(session('status'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('status')), 'success'));</script>
    @endif
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'danger'));</script>
    @endif
    @if(session('warning'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('warning')), 'warning'));</script>
    @endif
    @if(session('info'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('info')), 'info'));</script>
    @endif
    @if($errors->any())
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json($errors->first()), 'danger', 6000));</script>
    @endif

    @yield('scripts')
</body>
</html>
