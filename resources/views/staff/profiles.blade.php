@extends('layouts.app')

@section('title', 'Kim Çalışıyor? - Personel Profil Seçimi')

@section('content')
    <div class="relative min-h-screen flex flex-col items-center justify-center p-4 sm:p-8 overflow-hidden bg-slate-950">
        <!-- Netflix-style Background Glow Blobs -->
        <div
            class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-purple-900/30 rounded-full blur-3xl pointer-events-none">
        </div>
        <div
            class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-indigo-900/30 rounded-full blur-3xl pointer-events-none">
        </div>

        <div class="relative z-10 w-full max-w-6xl text-center">
            <!-- Header -->
            <div class="mb-10 animate-fade-in">
                <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight text-white mb-3 drop-shadow-md">
                    Kim Çalışıyor?
                </h1>
                <p class="text-lg text-slate-400 font-medium">
                    Restoran Kasa ve POS sistemini kullanmak için profilinizi seçip PIN kodunuzu giriniz.
                </p>
            </div>

            @if (session('info'))
                <div
                    class="mb-8 max-w-md mx-auto p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-sm flex items-center justify-center gap-2">
                    <span><i class="fi fi-rr-info"></i> {{ session('info') }}</span>
                </div>
            @endif

            <!-- Profile Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-6 sm:gap-8 justify-center px-4">
                @forelse($profiles as $profile)
                    @php
                        $colorMap = [
                            'indigo' => 'from-indigo-600 to-purple-600 group-hover:ring-indigo-400',
                            'emerald' => 'from-emerald-600 to-teal-600 group-hover:ring-emerald-400',
                            'amber' => 'from-amber-500 to-orange-600 group-hover:ring-amber-400',
                            'rose' => 'from-rose-600 to-pink-600 group-hover:ring-rose-400',
                            'cyan' => 'from-cyan-600 to-blue-600 group-hover:ring-cyan-400',
                        ];
                        $bgGradient = $colorMap[$profile->avatar_color] ?? $colorMap['indigo'];
                        $pinLength = strlen(trim($profile->pin_code ?? '')) ?: 4;
                    @endphp

                    <div onclick="openPinModal({{ $profile->id }}, '{{ addslashes($profile->name) }}', '{{ $profile->role }}', {{ $pinLength }})"
                        class="group cursor-pointer flex flex-col items-center transition-all duration-300 transform hover:-translate-y-2">

                        <!-- Avatar Box -->
                        <div
                            class="w-28 h-28 sm:w-36 sm:h-36 rounded-3xl bg-gradient-to-br {{ $bgGradient }} p-1 shadow-2xl transition-all duration-300 group-hover:ring-4 group-hover:shadow-indigo-500/30">
                            <div
                                class="w-full h-full bg-slate-900/80 backdrop-blur-md rounded-[22px] flex flex-col items-center justify-center p-3 border border-white/10">
                                <i class="fi fi-rr-user text-3xl sm:text-4xl text-white mb-1"></i>
                                <span
                                    class="text-[10px] sm:text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/10 text-white/90">
                                    {{ $profile->role }}
                                </span>
                            </div>
                        </div>

                        <!-- Staff Name -->
                        <span class="mt-3 text-lg font-bold text-slate-200 group-hover:text-white transition-colors">
                            {{ $profile->name }}
                        </span>
                        <span class="text-xs text-slate-500 font-medium">🔒 {{ $pinLength }}-Haneli PIN</span>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-slate-400">
                        <p class="text-lg">Bu şubeye henüz tanımlı personel bulunamadı.</p>
                    </div>
                @endforelse
            </div>

            <!-- System Logout -->
            <div class="mt-16">
                <form action="{{ route('logout') }}" method="POST" class="inline-block">
                    @csrf
                    <button type="submit"
                        class="px-6 py-2.5 rounded-xl border border-slate-800 bg-slate-900/60 text-slate-400 hover:text-white hover:bg-slate-800 text-sm font-semibold transition-all flex items-center gap-2">
                        <span><i class="fi fi-rr-sign-out-alt"></i> Restoran Hesabından Çıkış Yap</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Touch Numeric Keypad (Numpad Modal) -->
    <div id="pinModal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-lg">
        <div
            class="relative w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl text-center animate-scale-up">

            <!-- Close Button -->
            <button onclick="closePinModal()"
                class="absolute top-4 right-4 text-slate-400 hover:text-white p-2 rounded-full hover:bg-slate-800 transition-all">
                ✕
            </button>

            <!-- Selected Profile Header -->
            <div class="mb-6">
                <div id="modalAvatarRole"
                    class="inline-block px-3 py-1 rounded-full bg-indigo-500/10 text-indigo-400 text-xs font-bold uppercase tracking-wider mb-2">
                    Garson
                </div>
                <h2 id="modalProfileName" class="text-2xl font-extrabold text-white">
                    Personel Adı
                </h2>
                <p id="modalSubTitle" class="text-xs text-slate-400 mt-1">Lütfen PIN Kodunuzu Giriniz</p>
            </div>

            <!-- PIN Display Dots (Dynamically rendered based on profile's exact PIN length) -->
            <div id="dotsContainer" class="flex justify-center gap-2.5 sm:gap-3 mb-6 min-h-[16px]"></div>

            <!-- Error Message Bar -->
            <div id="pinErrorMsg"
                class="hidden mb-4 text-xs font-semibold text-red-400 bg-red-500/10 border border-red-500/20 py-2 px-3 rounded-xl">
            </div>

            <!-- Numpad Grid -->
            <div class="grid grid-cols-3 gap-3 mb-3">
                <button onclick="pressDigit('1')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">1</button>
                <button onclick="pressDigit('2')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">2</button>
                <button onclick="pressDigit('3')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">3</button>

                <button onclick="pressDigit('4')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">4</button>
                <button onclick="pressDigit('5')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">5</button>
                <button onclick="pressDigit('6')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">6</button>

                <button onclick="pressDigit('7')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">7</button>
                <button onclick="pressDigit('8')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">8</button>
                <button onclick="pressDigit('9')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">9</button>

                <button onclick="clearPin()"
                    class="py-3.5 text-sm font-bold text-slate-400 hover:text-white bg-slate-800/50 hover:bg-slate-700 rounded-2xl border border-slate-700/30 transition-all">C</button>
                <button onclick="pressDigit('0')"
                    class="py-3.5 text-2xl font-bold text-white bg-slate-800/80 hover:bg-slate-700 active:bg-indigo-600 rounded-2xl border border-slate-700/50 shadow transition-all">0</button>
                <button onclick="backspacePin()"
                    class="py-3.5 text-lg font-bold text-slate-300 hover:text-white bg-slate-800/50 hover:bg-slate-700 rounded-2xl border border-slate-700/30 transition-all">⌫</button>
            </div>

            <!-- Submit Button -->
            <button onclick="submitPin()"
                class="w-full py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                <span>Giriş Yap</span>
                <i class="fi fi-rr-arrow-right"></i>
            </button>
        </div>
    </div>

    <script>
        let selectedProfileId = null;
        let enteredPin = "";
        let currentTargetPinLength = 4;

        function openPinModal(id, name, role, pinLength = 4) {
            selectedProfileId = id;
            enteredPin = "";
            currentTargetPinLength = parseInt(pinLength) || 4;

            document.getElementById('modalProfileName').innerText = name;
            document.getElementById('modalAvatarRole').innerText = role;
            document.getElementById('modalSubTitle').innerText = `Lütfen ${currentTargetPinLength} Haneli PIN Kodunuzu Giriniz`;
            document.getElementById('pinErrorMsg').classList.add('hidden');

            renderDots(currentTargetPinLength);
            document.getElementById('pinModal').classList.remove('hidden');
            updateDots();
        }

        function renderDots(count) {
            const container = document.getElementById('dotsContainer');
            container.innerHTML = '';
            for (let i = 0; i < count; i++) {
                const dot = document.createElement('div');
                dot.id = 'dot' + i;
                dot.className = "w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full border-2 border-slate-700 bg-slate-800 transition-all";
                container.appendChild(dot);
            }
        }

        function closePinModal() {
            document.getElementById('pinModal').classList.add('hidden');
            enteredPin = "";
            selectedProfileId = null;
        }

        function pressDigit(digit) {
            if (enteredPin.length < currentTargetPinLength) {
                enteredPin += digit;
                updateDots();

                if (enteredPin.length === currentTargetPinLength) {
                    submitPin();
                }
            }
        }

        function backspacePin() {
            if (enteredPin.length > 0) {
                enteredPin = enteredPin.slice(0, -1);
                updateDots();
            }
        }

        function clearPin() {
            enteredPin = "";
            updateDots();
        }

        function updateDots() {
            for (let i = 0; i < currentTargetPinLength; i++) {
                const dot = document.getElementById('dot' + i);
                if (dot) {
                    if (i < enteredPin.length) {
                        dot.className = "w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full border-2 border-indigo-400 bg-indigo-500 scale-110 shadow-lg shadow-indigo-500/50 transition-all";
                    } else {
                        dot.className = "w-3.5 h-3.5 sm:w-4 sm:h-4 rounded-full border-2 border-slate-700 bg-slate-800 transition-all";
                    }
                }
            }
        }

        async function submitPin() {
            if (!selectedProfileId || enteredPin.length < 4) return;

            const errorMsg = document.getElementById('pinErrorMsg');
            errorMsg.classList.add('hidden');

            try {
                const response = await fetch("{{ route('staff.select') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        profile_id: selectedProfileId,
                        pin: enteredPin
                    })
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMsg.innerText = data.message || "Girdiğiniz PIN Kodu hatalı!";
                    errorMsg.classList.remove('hidden');
                    enteredPin = "";
                    updateDots();
                }
            } catch (err) {
                errorMsg.innerText = "PIN doğrulanırken bir hata oluştu.";
                errorMsg.classList.remove('hidden');
                enteredPin = "";
                updateDots();
            }
        }

        // Physical Keyboard listener
        document.addEventListener('keydown', function (e) {
            const modal = document.getElementById('pinModal');
            if (modal.classList.contains('hidden')) return;

            if (e.key >= '0' && e.key <= '9') {
                pressDigit(e.key);
            } else if (e.key === 'Backspace') {
                backspacePin();
            } else if (e.key === 'Enter') {
                submitPin();
            } else if (e.key === 'Escape') {
                closePinModal();
            }
        });
    </script>
@endsection