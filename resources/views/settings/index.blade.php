@extends('layouts.app')

@section('title', 'Sistem & Restoran Ayarları - Adisyon POS')

@section('content')
<div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans antialiased">

    <!-- TOP HEADER NAVBAR -->
    <header class="bg-[#121522]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3.5 flex items-center justify-between shadow-2xl">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center text-slate-300 hover:text-white transition-all shadow-md" title="Ana Panele Dön">
                <i class="fi fi-rr-arrow-left text-base"></i>
            </a>
            <div>
                <h1 class="font-extrabold text-lg tracking-wide text-white flex items-center gap-2">
                    <i class="fi fi-rr-settings text-purple-400"></i>
                    <span>Restoran & POS Ayarları</span>
                </h1>
                <p class="text-xs text-slate-400">Sistem yapılandırma ve işletme ayarlarınızı buradan yönetin.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            @if(session('success'))
                <div class="hidden sm:flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-950/60 border border-emerald-500/30 text-emerald-400 text-xs font-semibold animate-fade-in">
                    <i class="fi fi-rr-check-circle text-sm"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <a href="{{ route('dashboard') }}" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition-all flex items-center gap-2">
                <i class="fi fi-rr-cross-small text-sm"></i>
                <span>Kapat</span>
            </a>
        </div>
    </header>

    <!-- SETTINGS MAIN BODY (LEFT SIDEBAR MENU + RIGHT PANEL) -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col md:flex-row gap-6">

        <!-- LEFT SIDEBAR NAVIGATION MENU -->
        <aside class="w-full md:w-72 shrink-0 space-y-2">
            <div class="p-3 bg-[#131625] border border-slate-800/80 rounded-2xl shadow-xl space-y-1 sticky top-20">
                <div class="px-3 py-2 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                    Ayar Kategorileri
                </div>

                <!-- Tab 1: Genel Restoran -->
                <button type="button" onclick="switchTab('general')" id="tab-btn-general" class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left bg-purple-600/20 text-purple-300 border border-purple-500/30">
                    <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-400">
                        <i class="fi fi-rr-shop text-sm"></i>
                    </div>
                    <div>
                        <div class="leading-tight">Genel Restoran</div>
                        <div class="text-[10px] font-normal text-slate-400 mt-0.5">İşletme Bilgileri & KDV</div>
                    </div>
                </button>

                <!-- Tab 2: POS & Adisyon -->
                <button type="button" onclick="switchTab('pos')" id="tab-btn-pos" class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                    <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                        <i class="fi fi-rr-cash-register text-sm"></i>
                    </div>
                    <div>
                        <div class="leading-tight">POS & Adisyon</div>
                        <div class="text-[10px] font-normal text-slate-400 mt-0.5">Otomatik Masa & PIN</div>
                    </div>
                </button>

                <!-- Tab 3: Fiş & Yazıcı -->
                <button type="button" onclick="switchTab('receipt')" id="tab-btn-receipt" class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400">
                        <i class="fi fi-rr-print text-sm"></i>
                    </div>
                    <div>
                        <div class="leading-tight">Fiş & Yazıcı</div>
                        <div class="text-[10px] font-normal text-slate-400 mt-0.5">Adisyon Başlığı & Nüsha</div>
                    </div>
                </button>

                <!-- Tab 3.5: Yazıcı Durumu & Kuyruk (salt okunur — ayarlar servis programında) -->
                <button type="button" onclick="switchTab('printers')" id="tab-btn-printers" class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-400">
                        <i class="fi fi-rr-list-check text-sm"></i>
                    </div>
                    <div>
                        <div class="leading-tight">Yazıcı Durumu</div>
                        <div class="text-[10px] font-normal text-slate-400 mt-0.5">Kuyruk & Cihaz Bildirimi</div>
                    </div>
                </button>

                <!-- Tab 4: Ödeme Yöntemleri -->
                <button type="button" onclick="switchTab('payment')" id="tab-btn-payment" class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                        <i class="fi fi-rr-credit-card text-sm"></i>
                    </div>
                    <div>
                        <div class="leading-tight">Ödeme Yöntemleri</div>
                        <div class="text-[10px] font-normal text-slate-400 mt-0.5">Kasa Kabul Seçenekleri</div>
                    </div>
                </button>

                <!-- Tab 5: Mutfak & Ekran -->
                <button type="button" onclick="switchTab('kitchen')" id="tab-btn-kitchen" class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                    <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-400">
                        <i class="fi fi-rr-restaurant text-sm"></i>
                    </div>
                    <div>
                        <div class="leading-tight">Mutfak & Ekran</div>
                        <div class="text-[10px] font-normal text-slate-400 mt-0.5">Yenileme & İkaz Süreleri</div>
                    </div>
                </button>
            </div>
        </aside>

        <!-- RIGHT CONTENT PANEL FOR SELECTED TAB -->
        <section class="flex-1 min-w-0">
            <div class="bg-[#131625] border border-slate-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl relative">

                <!-- 🏢 FORM 1: GENEL RESTORAN AYARLARI -->
                <form action="{{ route('settings.update') }}" method="POST" id="form-general" class="tab-content space-y-6">
                    @csrf
                    <input type="hidden" name="group" value="general">

                    <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fi fi-rr-shop text-purple-400"></i>
                                <span>Genel Restoran Bilgileri</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Adisyon fişlerinde ve sistem genelinde görünecek işletme detayları.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 text-xs">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-300 mb-1.5">Restoran / İşletme Adı</label>
                            <input type="text" name="restaurant_name" value="{{ $merged['restaurant_name'] }}" required class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Telefon Numarası</label>
                            <input type="text" name="restaurant_phone" value="{{ $merged['restaurant_phone'] }}" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">E-posta Adresi</label>
                            <input type="email" name="restaurant_email" value="{{ $merged['restaurant_email'] }}" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-300 mb-1.5">Açık Adres</label>
                            <textarea name="restaurant_address" rows="3" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl p-4 text-white focus:border-purple-500 focus:outline-none transition">{{ $merged['restaurant_address'] }}</textarea>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Para Birimi Simgesi</label>
                            <select name="currency_symbol" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                                <option value="₺" {{ $merged['currency_symbol'] === '₺' ? 'selected' : '' }}>Türk Lirası (₺)</option>
                                <option value="$" {{ $merged['currency_symbol'] === '$' ? 'selected' : '' }}>US Dollar ($)</option>
                                <option value="€" {{ $merged['currency_symbol'] === '€' ? 'selected' : '' }}>Euro (€)</option>
                                <option value="£" {{ $merged['currency_symbol'] === '£' ? 'selected' : '' }}>GBP (£)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Varsayılan KDV Oranı (%)</label>
                            <select name="default_vat_rate" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                                <option value="1" {{ $merged['default_vat_rate'] === '1' ? 'selected' : '' }}>%1 KDV</option>
                                <option value="10" {{ $merged['default_vat_rate'] === '10' ? 'selected' : '' }}>%10 KDV (Yiyecek & İçecek)</option>
                                <option value="20" {{ $merged['default_vat_rate'] === '20' ? 'selected' : '' }}>%20 KDV (Standart)</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-lg shadow-purple-600/30 transition flex items-center gap-2">
                            <i class="fi fi-rr-disk text-sm"></i>
                            <span>Genel Ayarları Kaydet</span>
                        </button>
                    </div>
                </form>

                <!-- 🖥️ FORM 2: POS & ADİSYON AYARLARI -->
                <form action="{{ route('settings.update') }}" method="POST" id="form-pos" class="tab-content hidden space-y-6">
                    @csrf
                    <input type="hidden" name="group" value="pos">

                    <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fi fi-rr-cash-register text-indigo-400"></i>
                                <span>POS & Adisyon Kuralları</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Kasa ve garson işlemlerindeki otomatik kuralları belirleyin.</p>
                        </div>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div>
                                <div class="font-bold text-white">Ödeme Sonrası Masayı Otomatik Kapat</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">Adisyon tutarı tam ödendiğinde masa durumu anında "Boş" yapılır.</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="auto_close_table" value="0">
                                <input type="checkbox" name="auto_close_table" value="1" {{ $merged['auto_close_table'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div>
                                <div class="font-bold text-white">Her İşlemde Garson PIN Kodu İste</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">Masa açma ve ürün ekleme işlemlerinde 4 haneli PIN doğrulaması yapılır.</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="require_staff_pin" value="0">
                                <input type="checkbox" name="require_staff_pin" value="1" {{ $merged['require_staff_pin'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Maksimum İndirim Oranı (%)</label>
                                <input type="number" name="max_discount_percent" value="{{ $merged['max_discount_percent'] }}" min="0" max="100" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none transition">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Ürün İptal / Ziyan Yetkisi</label>
                                <select name="allow_item_void" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none transition">
                                    <option value="1" {{ $merged['allow_item_void'] == '1' ? 'selected' : '' }}>Tüm Personeller İptal Edebilir</option>
                                    <option value="0" {{ $merged['allow_item_void'] == '0' ? 'selected' : '' }}>Sadece Kasa ve Müdür İptal Edebilir</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition flex items-center gap-2">
                            <i class="fi fi-rr-disk text-sm"></i>
                            <span>POS Ayarlarını Kaydet</span>
                        </button>
                    </div>
                </form>

                <!-- 🧾 FORM 3: FİŞ & YAZICI AYARLARI -->
                <form action="{{ route('settings.update') }}" method="POST" id="form-receipt" class="tab-content hidden space-y-6">
                    @csrf
                    <input type="hidden" name="group" value="receipt">

                    <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fi fi-rr-print text-amber-400"></i>
                                <span>Fiş & Termal Yazıcı Ayarları</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Adisyon çıktıları ve termal fiş şablonu özelleştirmeleri.</p>
                        </div>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Fiş Üst Başlığı (Header)</label>
                            <input type="text" name="receipt_title" value="{{ $merged['receipt_title'] }}" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Fiş Alt Dipnotu (Footer)</label>
                            <input type="text" name="receipt_footer" value="{{ $merged['receipt_footer'] }}" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div>
                                    <div class="font-bold text-white">Mutfak Yazıcısına Otomatik Gönder</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Sipariş alındığı an mutfak yazıcısına çıktı iletilir.</div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="auto_print_kitchen" value="0">
                                    <input type="checkbox" name="auto_print_kitchen" value="1" {{ $merged['auto_print_kitchen'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </label>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Adisyon Nüsha Sayısı</label>
                                <select name="receipt_copies" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                                    <option value="1" {{ $merged['receipt_copies'] == '1' ? 'selected' : '' }}>1 Nüsha (Tek Çıktı)</option>
                                    <option value="2" {{ $merged['receipt_copies'] == '2' ? 'selected' : '' }}>2 Nüsha (Müşteri + Kasa)</option>
                                    <option value="3" {{ $merged['receipt_copies'] == '3' ? 'selected' : '' }}>3 Nüsha (Müşteri + Kasa + Mutfak)</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Fişte Basılacak Para Birimi Metni</label>
                            <input type="text" name="receipt_currency_text" maxlength="8" value="{{ $merged['receipt_currency_text'] }}" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                            <p class="text-[11px] text-slate-500 mt-1.5 flex items-start gap-1.5">
                                <i class="fi fi-rr-info text-amber-400 mt-0.5"></i>
                                <span>Termal yazıcıların kod sayfasında <strong class="text-slate-300">₺</strong> karakteri bulunmaz ve <strong class="text-slate-300">?</strong> olarak basılır. Fiş çıktısında bunun yerine buradaki metin (<strong class="text-slate-300">TL</strong>) kullanılır. Ekrandaki görünüm etkilenmez.</span>
                            </p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/20 transition flex items-center gap-2">
                            <i class="fi fi-rr-disk text-sm"></i>
                            <span>Fiş Ayarlarını Kaydet</span>
                        </button>
                    </div>
                </form>

                <!-- 🖨️ PANEL: YAZICI DURUMU & YAZDIRMA KUYRUĞU (SALT OKUNUR) -->
                <div id="form-printers" class="tab-content hidden space-y-6">

                    <div class="border-b border-slate-800 pb-4">
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fi fi-rr-settings-sliders text-sky-400"></i>
                            <span>Yazıcı Durumu & Yazdırma Kuyruğu</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">Cihazlardan bildirilen yazıcı yapılandırması ve merkezi yazdırma kuyruğu takibi.</p>
                    </div>

                    <!-- YAZICI AYARLARI NEREDEN YAPILIR -->
                    <div class="p-4 rounded-2xl bg-sky-950/40 border border-sky-500/30 flex items-start gap-3">
                        <i class="fi fi-rr-info text-sky-400 text-base mt-0.5"></i>
                        <div class="text-xs text-sky-100/90 leading-relaxed">
                            <div class="font-bold text-sky-200 mb-1">Yazıcı ayarları buradan değil, kasadaki servis programından yapılır.</div>
                            Hangi Windows yazıcısının kurulu olduğunu yalnızca cihazın kendisi bilebilir. Eşleştirmeyi yapmak için:
                            <span class="font-bold text-white">AltF4 Servis Programı &rarr; Sistem Tepsisi ikonu &rarr; Yönetim Paneli &rarr; 🖨️ Termal Yazıcılar</span>.
                            Aşağıdaki liste, cihazların merkeze bildirdiği yapılandırmayı gösterir.
                        </div>
                    </div>

                    <!-- CİHAZLARDAN BİLDİRİLEN YAZICILAR -->
                    <div class="space-y-3">
                        @forelse($printers as $printer)
                            @php
                                $typeLabels = ['kitchen' => 'Mutfak', 'cashier' => 'Kasa', 'bar' => 'Bar'];
                                $typeColors = ['kitchen' => 'emerald', 'cashier' => 'amber', 'bar' => 'fuchsia'];
                                $color = $typeColors[$printer->type] ?? 'slate';
                            @endphp
                            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-{{ $color }}-500/15 text-{{ $color }}-400 flex items-center justify-center shrink-0">
                                    <i class="fi fi-rr-print"></i>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-white text-sm">{{ $printer->name }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-{{ $color }}-500/15 text-{{ $color }}-300">{{ $typeLabels[$printer->type] ?? $printer->type }}</span>
                                        @if(!$printer->is_active)
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/15 text-rose-300">PASİF</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-1 font-mono truncate">
                                        {{ $printer->printer_target ?: 'Windows varsayılan yazıcısı' }}
                                        <span class="text-slate-600">•</span>
                                        {{ $printer->paper_width }}mm / {{ $printer->effectiveCharWidth() }} karakter
                                        <span class="text-slate-600">•</span>
                                        {{ strtoupper($printer->codepage) }}
                                    </div>
                                </div>

                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider shrink-0">Cihazdan bildirildi</span>
                            </div>
                        @empty
                            <div class="p-6 rounded-2xl bg-slate-900/60 border border-dashed border-slate-700 text-center">
                                <i class="fi fi-rr-print text-2xl text-slate-600"></i>
                                <p class="text-xs text-slate-400 mt-2 font-semibold">Henüz hiçbir cihaz yazıcı yapılandırması bildirmedi.</p>
                                <p class="text-[11px] text-slate-500 mt-1">Servis programının Termal Yazıcılar ekranından kaydettiğinizde burada görünecek. Bildirim gelmezse fişler cihazdaki <strong class="text-slate-300">varsayılan Windows yazıcısına</strong> gönderilir.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- YAZDIRMA KUYRUĞU -->
                    <div class="bg-slate-900/70 border border-slate-800 rounded-2xl overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                <i class="fi fi-rr-list-check text-sky-400"></i>
                                <span>Son Yazdırma İşleri</span>
                            </h3>
                            <button type="button" onclick="window.location.reload()" class="text-[11px] font-bold text-slate-400 hover:text-white transition flex items-center gap-1.5">
                                <i class="fi fi-rr-refresh text-xs"></i><span>Yenile</span>
                            </button>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-[11px]">
                                <thead class="bg-slate-950/60 text-slate-400">
                                    <tr>
                                        <th class="text-left font-bold px-4 py-2.5">#</th>
                                        <th class="text-left font-bold px-4 py-2.5">Fiş</th>
                                        <th class="text-left font-bold px-4 py-2.5">Yazıcı</th>
                                        <th class="text-left font-bold px-4 py-2.5">Durum</th>
                                        <th class="text-left font-bold px-4 py-2.5">Zaman</th>
                                        <th class="px-4 py-2.5"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/70">
                                    @forelse($printJobs as $job)
                                        @php
                                            $statusMap = [
                                                'pending'   => ['Kuyrukta', 'slate'],
                                                'claimed'   => ['Cihaz Aldı', 'sky'],
                                                'received'  => ['Cihaz Aldı', 'sky'],
                                                'processing'=> ['Hazırlanıyor', 'indigo'],
                                                'printing'  => ['Basılıyor', 'indigo'],
                                                'completed' => ['Yazdırıldı', 'emerald'],
                                                'printed'   => ['Yazdırıldı', 'emerald'],
                                                'failed'    => ['Başarısız', 'rose'],
                                            ];
                                            [$statusLabel, $statusColor] = $statusMap[$job->status] ?? [$job->status, 'slate'];
                                        @endphp
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="px-4 py-2.5 font-mono text-slate-500">{{ $job->id }}</td>
                                            <td class="px-4 py-2.5 text-slate-200 max-w-[220px] truncate" title="{{ $job->title }}">{{ $job->title }}</td>
                                            <td class="px-4 py-2.5 text-slate-400">{{ $job->printer->name ?? 'Varsayılan' }}</td>
                                            <td class="px-4 py-2.5">
                                                <span class="px-2 py-0.5 rounded-md font-bold bg-{{ $statusColor }}-500/15 text-{{ $statusColor }}-300">{{ $statusLabel }}</span>
                                                @if($job->status === 'failed' && $job->error_message)
                                                    <div class="text-[10px] text-rose-400/80 mt-1 max-w-[240px] truncate" title="{{ $job->error_message }}">{{ $job->error_message }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-slate-500 font-mono">{{ $job->created_at?->format('d.m H:i:s') }}</td>
                                            <td class="px-4 py-2.5 text-right">
                                                @if($job->status === 'failed')
                                                    <button type="button" onclick="requeueJob({{ $job->id }}, this)" class="px-2.5 py-1.5 rounded-lg bg-amber-600/20 hover:bg-amber-600/40 border border-amber-500/30 text-amber-300 font-bold transition">
                                                        Tekrar Dene
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Henüz yazdırma işi oluşturulmadı.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 💳 FORM 4: ÖDEME YÖNTEMLERİ -->
                <form action="{{ route('settings.update') }}" method="POST" id="form-payment" class="tab-content hidden space-y-6">
                    @csrf
                    <input type="hidden" name="group" value="payment">

                    <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fi fi-rr-credit-card text-emerald-400"></i>
                                <span>Kabul Edilen Ödeme Yöntemleri</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Kasada aktif tutulacak ödeme seçeneklerini işaretleyin.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold">💵</div>
                                <div>
                                    <div class="font-bold text-white">Nakit Ödeme</div>
                                    <div class="text-[10px] text-slate-400">Nakit para ile tahsilat</div>
                                </div>
                            </div>
                            <input type="checkbox" name="enable_cash" value="1" {{ $merged['enable_cash'] == '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-0">
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-sky-500/20 flex items-center justify-center text-sky-400 font-bold">💳</div>
                                <div>
                                    <div class="font-bold text-white">Kredi / Banka Kartı</div>
                                    <div class="text-[10px] text-slate-400">POS Cihazı ile tahsilat</div>
                                </div>
                            </div>
                            <input type="checkbox" name="enable_card" value="1" {{ $merged['enable_card'] == '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-0">
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-purple-500/20 flex items-center justify-center text-purple-400 font-bold">🎟️</div>
                                <div>
                                    <div class="font-bold text-white">Sodexo / Pluxee</div>
                                    <div class="text-[10px] text-slate-400">Yemek kartı tahsilatı</div>
                                </div>
                            </div>
                            <input type="checkbox" name="enable_sodexo" value="1" {{ $merged['enable_sodexo'] == '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-0">
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-400 font-bold">🎫</div>
                                <div>
                                    <div class="font-bold text-white">Multinet</div>
                                    <div class="text-[10px] text-slate-400">Multinet yemek kartı</div>
                                </div>
                            </div>
                            <input type="checkbox" name="enable_multinet" value="1" {{ $merged['enable_multinet'] == '1' ? 'checked' : '' }} class="w-5 h-5 rounded bg-slate-800 border-slate-700 text-emerald-500 focus:ring-0">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition flex items-center gap-2">
                            <i class="fi fi-rr-disk text-sm"></i>
                            <span>Ödeme Ayarlarını Kaydet</span>
                        </button>
                    </div>
                </form>

                <!-- 👨‍🍳 FORM 5: MUTFAK & EKRAN AYARLARI -->
                <form action="{{ route('settings.update') }}" method="POST" id="form-kitchen" class="tab-content hidden space-y-6">
                    @csrf
                    <input type="hidden" name="group" value="kitchen">

                    <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                <i class="fi fi-rr-restaurant text-rose-400"></i>
                                <span>Mutfak Ekranı Ayarları</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Mutfak sipariş ekranı ve ikaz süreleri.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Ekran Otomatik Yenileme Süresi</label>
                            <select name="kitchen_refresh_sec" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-rose-500 focus:outline-none transition">
                                <option value="5" {{ $merged['kitchen_refresh_sec'] == '5' ? 'selected' : '' }}>Her 5 Saniyede Bir</option>
                                <option value="10" {{ $merged['kitchen_refresh_sec'] == '10' ? 'selected' : '' }}>Her 10 Saniyede Bir</option>
                                <option value="15" {{ $merged['kitchen_refresh_sec'] == '15' ? 'selected' : '' }}>Her 15 Saniyede Bir</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1.5">Geciken Sipariş İkaz Süresi</label>
                            <select name="kitchen_warning_min" class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-rose-500 focus:outline-none transition">
                                <option value="10" {{ $merged['kitchen_warning_min'] == '10' ? 'selected' : '' }}>10 Dakika Sonra Kırmızıya Dönüşsün</option>
                                <option value="15" {{ $merged['kitchen_warning_min'] == '15' ? 'selected' : '' }}>15 Dakika Sonra Kırmızıya Dönüşsün</option>
                                <option value="20" {{ $merged['kitchen_warning_min'] == '20' ? 'selected' : '' }}>20 Dakika Sonra Kırmızıya Dönüşsün</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2 flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                            <div>
                                <div class="font-bold text-white">Yeni Sipariş Geldiğinde Sesli Uyarı Çal</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">Mutfak ekranında yeni adisyon düştüğünde biper/zil sesi çalar.</div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="kitchen_sound_alert" value="0">
                                <input type="checkbox" name="kitchen_sound_alert" value="1" {{ $merged['kitchen_sound_alert'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-lg shadow-rose-600/30 transition flex items-center gap-2">
                            <i class="fi fi-rr-disk text-sm"></i>
                            <span>Mutfak Ayarlarını Kaydet</span>
                        </button>
                    </div>
                </form>

            </div>
        </section>

    </main>
</div>

<!-- JAVASCRIPT TAB SWITCHER -->
<script>
    function switchTab(tabId) {
        // Hide all form tab contents
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));

        // Reset all sidebar buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-purple-600/20', 'text-purple-300', 'border-purple-500/30');
            btn.classList.add('text-slate-400', 'border-transparent');
        });

        // Show selected form
        const selectedForm = document.getElementById('form-' + tabId);
        if (selectedForm) {
            selectedForm.classList.remove('hidden');
        }

        // Highlight selected tab button
        const selectedBtn = document.getElementById('tab-btn-' + tabId);
        if (selectedBtn) {
            selectedBtn.classList.remove('text-slate-400', 'border-transparent');
            selectedBtn.classList.add('bg-purple-600/20', 'text-purple-300', 'border-purple-500/30');
        }
    }

    // URL parametresine göre varsayılan tabı seç (?tab=pos)
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'general';
        switchTab(activeTab);
    });

    /* ---------------- YAZDIRMA KUYRUĞU ---------------- */

    const CSRF = '{{ csrf_token() }}';

    /**
     * Başarısız bir yazdırma işini kuyruğa geri koyar.
     * Yazıcı seçimi/testi cihazdaki servis programında yapılır; burada yalnızca
     * merkezi kuyruk yönetilir.
     */
    async function requeueJob(jobId, btn) {
        btn.disabled = true;
        try {
            const res = await fetch(`/settings/printers/jobs/${jobId}/requeue`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const data = await res.json();
            alert(data.message || 'İşlem tamamlandı.');
            window.location.reload();
        } catch (e) {
            alert('❌ İşlem başarısız: ' + e.message);
            btn.disabled = false;
        }
    }
</script>
@endsection
