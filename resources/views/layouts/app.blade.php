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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'Plus Jakarta Sans', 'sans-serif'],
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
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
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
