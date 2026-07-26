<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Adisyon Sistem Portalı')</title>
    <!-- Yerel Offline Varlıklar (İnternet Kesintisinde %100 Çevrimdışı Kasa Desteği) -->
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/uicons-regular-rounded.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        // Sayfa yüklenmeden önce kayıtlı temayı uygula (Flicker Önleme)
        (function() {
            const savedTheme = localStorage.getItem('pos_theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.add('light-mode');
            }
        })();

        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'Outfit', 'Plus Jakarta Sans', 'sans-serif'],
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
            transform: translateY(-1px        /* ==========================================================================
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
        html.light-mode main,
        html.light-mode #posMainWrapper {
            background-color: #f8fafc !important;
        }

        /* 2. Tüm Koyu Hex Arka Plan Yüzeylerini Ve Panelleri Beyaza Çevir */
        html.light-mode [class*="bg-[#0"],
        html.light-mode [class*="bg-[#1"],
        html.light-mode [class*="bg-\[\#0"],
        html.light-mode [class*="bg-\[\#1"],
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

        /* POS Ürün Kartları */
        html.light-mode .product-item {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04) !important;
            color: #0f172a !important;
        }
        html.light-mode .product-item:hover {
            background-color: #f8fafc !important;
            border-color: #6366f1 !important;
        }

        /* POS Yan Aksiyon Butonları */
        html.light-mode #posMainWrapper button:not([class*="bg-indigo-600"]):not([class*="bg-emerald"]):not([class*="bg-rose"]):not([class*="bg-amber"]):not([class*="bg-violet"]):not([class*="bg-sky"]):not([class*="bg-cyan"]):not([class*="bg-orange"]) {
            background-color: #ffffff !important;
            border-color: #e2e8f0 !important;
            color: #1e293b !important;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05) !important;
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

        /* 6. RENKLİ AKSİYON BUTONLARINI VE BADGE'LERİ KORU (Beyaz Yazıları Bozma) */
        html.light-mode button[class*="bg-indigo-600"],
        html.light-mode button[class*="bg-indigo-500"],
        html.light-mode button[class*="bg-emerald"],
        html.light-mode button[class*="bg-rose"],
        html.light-mode button[class*="bg-amber-500"],
        html.light-mode button[class*="bg-amber-600"],
        html.light-mode button[class*="bg-sky"],
        html.light-mode button[class*="bg-teal"],
        html.light-mode button[class*="bg-purple"],
        html.light-mode button[class*="bg-violet"],
        html.light-mode button[class*="bg-orange"],
        html.light-mode a[class*="bg-indigo-600"],
        html.light-mode a[class*="bg-indigo-500"],
        html.light-mode a[class*="bg-emerald"],
        html.light-mode a[class*="bg-rose"],
        html.light-mode a[class*="bg-amber-500"],
        html.light-mode a[class*="bg-amber-600"],
        html.light-mode a[class*="bg-sky"],
        html.light-mode a[class*="bg-teal"],
        html.light-mode a[class*="bg-purple"],
        html.light-mode a[class*="bg-violet"],
        html.light-mode a[class*="bg-orange"] {
            color: #ffffff !important;
        }
        html.light-mode button[class*="bg-indigo-600"] *,
        html.light-mode button[class*="bg-indigo-500"] *,
        html.light-mode button[class*="bg-emerald"] *,
        html.light-mode button[class*="bg-rose"] *,
        html.light-mode button[class*="bg-amber-500"] *,
        html.light-mode button[class*="bg-amber-600"] *,
        html.light-mode button[class*="bg-sky"] *,
        html.light-mode button[class*="bg-teal"] *,
        html.light-mode button[class*="bg-purple"] *,
        html.light-mode button[class*="bg-violet"] *,
        html.light-mode button[class*="bg-orange"] *,
        html.light-mode a[class*="bg-indigo-600"] *,
        html.light-mode a[class*="bg-indigo-500"] *,
        html.light-mode a[class*="bg-emerald"] *,
        html.light-mode a[class*="bg-rose"] *,
        html.light-mode a[class*="bg-amber-500"] *,
        html.light-mode a[class*="bg-amber-600"] *,
        html.light-mode a[class*="bg-sky"] *,
        html.light-mode a[class*="bg-teal"] *,
        html.light-mode a[class*="bg-purple"] *,
        html.light-mode a[class*="bg-violet"] *,
        html.light-mode a[class*="bg-orange"] * {
            color: #ffffff !important;
        }

        /* LOGO BEYAZ MOD KONTRASTI */
        html.light-mode header img[alt*="ADİSYON"],
        html.light-mode header img[src*="logo"] {
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.15)) brightness(0.2) contrast(1.8) !important;
        }

        /* 7. Form Girdi Alanları */
        html.light-mode input[type="text"],
        html.light-mode input[type="number"],
        html.light-mode input[type="password"],
        html.light-mode input[type="date"],
        html.light-mode input[type="search"],
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
        html.light-mode .hover\:bg-slate-900:hover,
        html.light-mode .hover\:bg-slate-800\/80:hover {
            background-color: #e2e8f0 !important;
        }
    </style>
    @yield('styles')
</head>
<body class="h-full antialiased selection:bg-indigo-500 selection:text-white flex flex-col min-h-screen relative">

    <!-- 🌐 GLOBAL ALERT TOAST CONTAINER -->
    <div id="toastContainer" class="fixed top-5 right-5 z-[9999] flex flex-col gap-3 max-w-sm sm:max-w-md pointer-events-none"></div>

    @yield('content')

    <!-- GLOBAL TOAST NOTIFICATION SCRIPT -->
    <script>
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
                        <p class="text-xs font-medium text-slate-300 mt-1 leading-relaxed break-words">${message}</p>
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
            icons.forEach(el => {
                el.className = isLight ? 'fi fi-rr-sun text-amber-500 text-sm theme-toggle-icon' : 'fi fi-rr-moon text-indigo-400 text-sm theme-toggle-icon';
            });
            texts.forEach(el => {
                el.innerText = isLight ? 'Beyaz Mod' : 'Karanlık Mod';
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
    @if(session('error'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('error')), 'danger'));</script>
    @endif
    @if(session('warning'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('warning')), 'warning'));</script>
    @endif
    @if(session('info'))
        <script>document.addEventListener('DOMContentLoaded', () => showToast(@json(session('info')), 'info'));</script>
    @endif

    @yield('scripts')
</body>
</html>
