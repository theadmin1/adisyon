@extends('layouts.app')

@section('title', 'Sistem & Restoran Ayarları - Adisyon POS')

@section('styles')
    <style>
        html.light-mode #settingsCategoriesPanel,
        html.light-mode #settingsContentPanel {
            background-color: #ffffff !important;
        }
    </style>
@endsection

@section('content')
    @php
        $hallEditData = $halls->mapWithKeys(fn ($hall) => [$hall->id => [
            'id' => $hall->id,
            'name' => $hall->name,
            'sort_order' => $hall->sort_order ?? 0,
        ]]);
        $tableEditData = $tables->mapWithKeys(fn ($table) => [$table->id => [
            'id' => $table->id,
            'name' => $table->name,
            'code' => $table->code,
            'hall_id' => $table->hall_id,
            'capacity' => $table->capacity,
            'status' => is_object($table->status) ? $table->status->value : $table->status,
            'is_active' => $table->is_active,
            'notes' => $table->notes,
        ]]);
    @endphp
    <div class="min-h-screen flex flex-col bg-[#0b0c12] text-slate-100 font-sans antialiased">

        <!-- TOP HEADER NAVBAR -->
        <header
            class="bg-[#121522]/90 backdrop-blur-xl sticky top-0 z-50 border-b border-slate-800/80 px-4 lg:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}"
                    class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center text-slate-300 hover:text-white transition-all shadow-md"
                    title="Ana Panele Dön">
                    <i class="fi fi-rr-arrow-left text-base"></i>
                </a>
                <div>
                    <h1 class="font-extrabold text-lg tracking-wide text-white flex items-center gap-2">
                        <span>Restoran & POS Ayarları</span>
                    </h1>
                    <p class="text-xs text-slate-400">Sistem yapılandırma ve işletme ayarlarınızı buradan yönetin.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}"
                    class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold transition-all flex items-center gap-2">
                    <i class="fi fi-rr-cross-small text-sm"></i>
                    <span>Kapat</span>
                </a>
            </div>
        </header>

        <!-- SETTINGS MAIN BODY (LEFT SIDEBAR MENU + RIGHT PANEL) -->
        <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col md:flex-row gap-6">

            <!-- LEFT SIDEBAR NAVIGATION MENU -->
            <aside class="w-full md:w-72 shrink-0 space-y-2">
                <div id="settingsCategoriesPanel"
                    class="p-3 bg-[#131625] border border-slate-800/80 rounded-2xl shadow-xl space-y-1 sticky top-20">
                    <div class="px-3 py-2 text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
                        Ayar Kategorileri
                    </div>

                    <!-- Tab 1: Genel Restoran -->
                    <button type="button" onclick="switchTab('general')" id="tab-btn-general"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left bg-purple-600/20 text-purple-300 border border-purple-500/30">
                        <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-400">
                            <i class="fi fi-rr-shop text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Genel Restoran</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">İşletme Bilgileri & KDV</div>
                        </div>
                    </button>

                    <!-- Tab 2: POS & Adisyon -->
                    <button type="button" onclick="switchTab('pos')" id="tab-btn-pos"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                            <i class="fi fi-rr-cash-register text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">POS & Adisyon</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Otomatik Masa & PIN</div>
                        </div>
                    </button>

                    <!-- Tab 3: Fiş & Yazıcı -->
                    <button type="button" onclick="switchTab('receipt')" id="tab-btn-receipt"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400">
                            <i class="fi fi-rr-print text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Fiş & Yazıcı</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Adisyon Başlığı & Nüsha</div>
                        </div>
                    </button>

                    <!-- Tab 3.5: Yazıcı Durumu & Kuyruk (salt okunur — ayarlar servis programında) -->
                    <button type="button" onclick="switchTab('printers')" id="tab-btn-printers"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-400">
                            <i class="fi fi-rr-list-check text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Yazıcı Durumu</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Kuyruk & Cihaz Bildirimi</div>
                        </div>
                    </button>

                    <!-- Tab 4: Ödeme Yöntemleri -->
                    <button type="button" onclick="switchTab('payment')" id="tab-btn-payment"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400">
                            <i class="fi fi-rr-credit-card text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Ödeme Yöntemleri</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Kasa Kabul Seçenekleri</div>
                        </div>
                    </button>

                    <!-- Tab 5: Mutfak & Ekran -->
                    <button type="button" onclick="switchTab('kitchen')" id="tab-btn-kitchen"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-400">
                            <i class="fi fi-rr-restaurant text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Mutfak & Ekran</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Yenileme & İkaz Süreleri</div>
                        </div>
                    </button>

                    <!-- Tab 5.5: Bildirim & Ses Ayarları -->
                    <button type="button" onclick="switchTab('sounds')" id="tab-btn-sounds"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-yellow-500/10 flex items-center justify-center text-yellow-400">
                            <i class="fi fi-rr-volume text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Bildirim & Sesler</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Melodi, Ses Seviyesi & İkazlar</div>
                        </div>
                    </button>

                    <!-- Tab 6: Masa Ayarları -->
                    <button type="button" onclick="switchTab('tables')" id="tab-btn-tables"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-teal-500/10 flex items-center justify-center text-teal-400">
                            <i class="fi fi-rr-chair text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Masa Ayarları</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Salon & Masa Yapılandırması</div>
                        </div>
                    </button>

                    <!-- Tab 7: Online Paket Servis Entegrasyonları -->
                    <button type="button" onclick="switchTab('integrations')" id="tab-btn-integrations"
                        class="tab-btn w-full flex items-center gap-3 px-3.5 py-3 rounded-xl text-xs font-bold transition-all text-left text-slate-400 hover:bg-slate-800/60 hover:text-white border border-transparent">
                        <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center text-orange-400">
                            <i class="fi fi-rr-box-alt text-sm"></i>
                        </div>
                        <div>
                            <div class="leading-tight">Online Entegrasyonlar</div>
                            <div class="text-[10px] font-normal text-slate-400 mt-0.5">Trendyol, Yemeksepeti, Getir, Migros
                            </div>
                        </div>
                    </button>
                </div>
            </aside>

            <!-- RIGHT CONTENT PANEL FOR SELECTED TAB -->
            <section class="flex-1 min-w-0">
                <div id="settingsContentPanel"
                    class="bg-[#131625] border border-slate-800/80 rounded-2xl p-4 sm:p-8 shadow-2xl relative">

                    <!-- 🏢 FORM 1: GENEL RESTORAN AYARLARI -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-general"
                        class="tab-content space-y-6">
                        @csrf
                        <input type="hidden" name="group" value="general">

                        <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fi fi-rr-shop text-purple-400"></i>
                                    <span>Genel Restoran Bilgileri</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">Adisyon fişlerinde ve sistem genelinde görünecek
                                    işletme detayları.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 text-xs">
                            <div class="sm:col-span-2">
                                <label class="block font-bold text-slate-300 mb-1.5">Restoran / İşletme Adı</label>
                                <input type="text" name="restaurant_name" value="{{ $merged['restaurant_name'] }}" required
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Telefon Numarası</label>
                                <input type="text" name="restaurant_phone" value="{{ $merged['restaurant_phone'] }}"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">E-posta Adresi</label>
                                <input type="email" name="restaurant_email" value="{{ $merged['restaurant_email'] }}"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block font-bold text-slate-300 mb-1.5">Açık Adres</label>
                                <textarea name="restaurant_address" rows="3"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl p-4 text-white focus:border-purple-500 focus:outline-none transition">{{ $merged['restaurant_address'] }}</textarea>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Para Birimi Simgesi</label>
                                <select name="currency_symbol"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                                    <option value="₺" {{ $merged['currency_symbol'] === '₺' ? 'selected' : '' }}>Türk Lirası
                                        (₺)</option>
                                    <option value="$" {{ $merged['currency_symbol'] === '$' ? 'selected' : '' }}>US Dollar ($)
                                    </option>
                                    <option value="€" {{ $merged['currency_symbol'] === '€' ? 'selected' : '' }}>Euro (€)
                                    </option>
                                    <option value="£" {{ $merged['currency_symbol'] === '£' ? 'selected' : '' }}>GBP (£)
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Varsayılan KDV Oranı (%)</label>
                                <select name="default_vat_rate"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-purple-500 focus:outline-none transition">
                                    <option value="1" {{ $merged['default_vat_rate'] === '1' ? 'selected' : '' }}>%1 KDV
                                    </option>
                                    <option value="10" {{ $merged['default_vat_rate'] === '10' ? 'selected' : '' }}>%10 KDV
                                        (Yiyecek & İçecek)</option>
                                    <option value="20" {{ $merged['default_vat_rate'] === '20' ? 'selected' : '' }}>%20 KDV
                                        (Standart)</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-lg shadow-purple-600/30 transition flex items-center gap-2">
                                <i class="fi fi-rr-disk text-sm"></i>
                                <span>Genel Ayarları Kaydet</span>
                            </button>
                        </div>
                    </form>

                    <!-- 🖥️ FORM 2: POS & ADİSYON AYARLARI -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-pos"
                        class="tab-content hidden space-y-6">
                        @csrf
                        <input type="hidden" name="group" value="pos">

                        <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fi fi-rr-cash-register text-indigo-400"></i>
                                    <span>POS & Adisyon Kuralları</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">Kasa ve garson işlemlerindeki otomatik kuralları
                                    belirleyin.</p>
                            </div>
                        </div>

                        <div class="space-y-4 text-xs">
                            <div
                                class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div>
                                    <div class="font-bold text-white">Ödeme Sonrası Masayı Otomatik Kapat</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Adisyon tutarı tam ödendiğinde masa
                                        durumu anında "Boş" yapılır.</div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="auto_close_table" value="0">
                                    <input type="checkbox" name="auto_close_table" value="1" {{ $merged['auto_close_table'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                    <div
                                        class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                    </div>
                                </label>
                            </div>

                            <div
                                class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div>
                                    <div class="font-bold text-white">Her İşlemde Garson PIN Kodu İste</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Masa açma ve ürün ekleme işlemlerinde 4
                                        haneli PIN doğrulaması yapılır.</div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="require_staff_pin" value="0">
                                    <input type="checkbox" name="require_staff_pin" value="1" {{ $merged['require_staff_pin'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                    <div
                                        class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600">
                                    </div>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block font-bold text-slate-300 mb-1.5">Maksimum İndirim Oranı (%)</label>
                                    <input type="number" name="max_discount_percent"
                                        value="{{ $merged['max_discount_percent'] }}" min="0" max="100"
                                        class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none transition">
                                </div>

                                <div>
                                    <label class="block font-bold text-slate-300 mb-1.5">Ürün İptal / Ziyan Yetkisi</label>
                                    <select name="allow_item_void"
                                        class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-indigo-500 focus:outline-none transition">
                                        <option value="1" {{ $merged['allow_item_void'] == '1' ? 'selected' : '' }}>Tüm
                                            Personeller İptal Edebilir</option>
                                        <option value="0" {{ $merged['allow_item_void'] == '0' ? 'selected' : '' }}>Sadece
                                            Kasa ve Müdür İptal Edebilir</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition flex items-center gap-2">
                                <i class="fi fi-rr-disk text-sm"></i>
                                <span>POS Ayarlarını Kaydet</span>
                            </button>
                        </div>
                    </form>

                    <!-- 🧾 FORM 3: FİŞ & YAZICI AYARLARI -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-receipt"
                        class="tab-content hidden space-y-6">
                        @csrf
                        <input type="hidden" name="group" value="receipt">

                        <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fi fi-rr-print text-amber-400"></i>
                                    <span>Fiş & Termal Yazıcı Ayarları</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">Adisyon çıktıları ve termal fiş şablonu
                                    özelleştirmeleri.</p>
                            </div>
                        </div>

                        <div class="space-y-4 text-xs">
                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Fiş Üst Başlığı (Header)</label>
                                <input type="text" name="receipt_title" value="{{ $merged['receipt_title'] }}"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Fiş Alt Dipnotu (Footer)</label>
                                <input type="text" name="receipt_footer" value="{{ $merged['receipt_footer'] }}"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div
                                    class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-white">Mutfak Yazıcısına Otomatik Gönder</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">Sipariş alındığı an mutfak yazıcısına
                                            çıktı iletilir.</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="auto_print_kitchen" value="0">
                                        <input type="checkbox" name="auto_print_kitchen" value="1" {{ $merged['auto_print_kitchen'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500">
                                        </div>
                                    </label>
                                </div>

                                <div>
                                    <label class="block font-bold text-slate-300 mb-1.5">Adisyon Nüsha Sayısı</label>
                                    <select name="receipt_copies"
                                        class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                                        <option value="1" {{ $merged['receipt_copies'] == '1' ? 'selected' : '' }}>1 Nüsha
                                            (Tek Çıktı)</option>
                                        <option value="2" {{ $merged['receipt_copies'] == '2' ? 'selected' : '' }}>2 Nüsha
                                            (Müşteri + Kasa)</option>
                                        <option value="3" {{ $merged['receipt_copies'] == '3' ? 'selected' : '' }}>3 Nüsha
                                            (Müşteri + Kasa + Mutfak)</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Fişte Basılacak Para Birimi
                                    Metni</label>
                                <input type="text" name="receipt_currency_text" maxlength="8"
                                    value="{{ $merged['receipt_currency_text'] }}"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-amber-500 focus:outline-none transition">
                                <p class="text-[11px] text-slate-500 mt-1.5 flex items-start gap-1.5">
                                    <i class="fi fi-rr-info text-amber-400 mt-0.5"></i>
                                    <span>Termal yazıcıların kod sayfasında <strong class="text-slate-300">₺</strong>
                                        karakteri bulunmaz ve <strong class="text-slate-300">?</strong> olarak basılır. Fiş
                                        çıktısında bunun yerine buradaki metin (<strong class="text-slate-300">TL</strong>)
                                        kullanılır. Ekrandaki görünüm etkilenmez.</span>
                                </p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-lg shadow-amber-500/20 transition flex items-center gap-2">
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
                            <p class="text-xs text-slate-400 mt-0.5">Cihazlardan bildirilen yazıcı yapılandırması ve merkezi
                                yazdırma kuyruğu takibi.</p>
                        </div>

                        <!-- YAZICI AYARLARI NEREDEN YAPILIR -->
                        <div class="p-4 rounded-2xl bg-sky-950/40 border border-sky-500/30 flex items-start gap-3">
                            <i class="fi fi-rr-info text-sky-400 text-base mt-0.5"></i>
                            <div class="text-xs text-sky-100/90 leading-relaxed">
                                <div class="font-bold text-sky-200 mb-1">Yazıcı ayarları buradan değil, kasadaki servis
                                    programından yapılır.</div>
                                Hangi Windows yazıcısının kurulu olduğunu yalnızca cihazın kendisi bilebilir. Eşleştirmeyi
                                yapmak için:
                                <span class="font-bold text-white">AltF4 Servis Programı &rarr; Sistem Tepsisi ikonu &rarr;
                                    Yönetim Paneli &rarr; 🖨️ Termal Yazıcılar</span>.
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
                                <div
                                    class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 flex flex-wrap items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl bg-{{ $color }}-500/15 text-{{ $color }}-400 flex items-center justify-center shrink-0">
                                        <i class="fi fi-rr-print"></i>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-white text-sm">{{ $printer->name }}</span>
                                            <span
                                                class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-{{ $color }}-500/15 text-{{ $color }}-300">{{ $typeLabels[$printer->type] ?? $printer->type }}</span>
                                            @if(!$printer->is_active)
                                                <span
                                                    class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/15 text-rose-300">PASİF</span>
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

                                    <span
                                        class="text-[10px] font-bold text-slate-500 uppercase tracking-wider shrink-0">Cihazdan
                                        bildirildi</span>
                                </div>
                            @empty
                                <div class="p-6 rounded-2xl bg-slate-900/60 border border-dashed border-slate-700 text-center">
                                    <i class="fi fi-rr-print text-2xl text-slate-600"></i>
                                    <p class="text-xs text-slate-400 mt-2 font-semibold">Henüz hiçbir cihaz yazıcı
                                        yapılandırması bildirmedi.</p>
                                    <p class="text-[11px] text-slate-500 mt-1">Servis programının Termal Yazıcılar ekranından
                                        kaydettiğinizde burada görünecek. Bildirim gelmezse fişler cihazdaki <strong
                                            class="text-slate-300">varsayılan Windows yazıcısına</strong> gönderilir.</p>
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
                                <button type="button" onclick="window.location.reload()"
                                    class="text-[11px] font-bold text-slate-400 hover:text-white transition flex items-center gap-1.5">
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
                                                    'pending' => ['Kuyrukta', 'slate'],
                                                    'claimed' => ['Cihaz Aldı', 'sky'],
                                                    'received' => ['Cihaz Aldı', 'sky'],
                                                    'processing' => ['Hazırlanıyor', 'indigo'],
                                                    'printing' => ['Basılıyor', 'indigo'],
                                                    'completed' => ['Yazdırıldı', 'emerald'],
                                                    'printed' => ['Yazdırıldı', 'emerald'],
                                                    'failed' => ['Başarısız', 'rose'],
                                                ];
                                                [$statusLabel, $statusColor] = $statusMap[$job->status] ?? [$job->status, 'slate'];
                                            @endphp
                                            <tr class="hover:bg-slate-800/30 transition">
                                                <td class="px-4 py-2.5 font-mono text-slate-500">{{ $job->id }}</td>
                                                <td class="px-4 py-2.5 text-slate-200 max-w-[220px] truncate"
                                                    title="{{ $job->title }}">{{ $job->title }}</td>
                                                <td class="px-4 py-2.5 text-slate-400">{{ $job->printer->name ?? 'Varsayılan' }}
                                                </td>
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="px-2 py-0.5 rounded-md font-bold bg-{{ $statusColor }}-500/15 text-{{ $statusColor }}-300">{{ $statusLabel }}</span>
                                                    @if($job->status === 'failed' && $job->error_message)
                                                        <div class="text-[10px] text-rose-400/80 mt-1 max-w-[240px] truncate"
                                                            title="{{ $job->error_message }}">{{ $job->error_message }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-slate-500 font-mono">
                                                    {{ $job->created_at?->format('d.m H:i:s') }}</td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @if($job->status === 'failed')
                                                        <button type="button" onclick="requeueJob({{ $job->id }}, this)"
                                                            class="px-2.5 py-1.5 rounded-lg bg-amber-600/20 hover:bg-amber-600/40 border border-amber-500/30 text-amber-300 font-bold transition">
                                                            Tekrar Dene
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Henüz yazdırma işi
                                                    oluşturulmadı.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 💳 FORM 4: ÖDEME YÖNTEMLERİ -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-payment"
                        class="tab-content hidden space-y-6">
                        @csrf
                        <input type="hidden" name="group" value="payment">

                        <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fi fi-rr-credit-card text-emerald-400"></i>
                                    <span>Kabul Edilen Ödeme Yöntemleri</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">Kasada aktif tutulacak ödeme seçeneklerini
                                    işaretleyin.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                            @foreach($paymentMethods as $method)
                            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-800 bg-slate-900/80 p-4 transition hover:border-emerald-500/30">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-800 font-bold text-emerald-400">
                                        <i class="fi {{ $method['icon'] }} text-base"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-white">{{ $method['label'] }}</div>
                                        <div class="text-[10px] text-slate-400">{{ $method['description'] }}</div>
                                    </div>
                                </div>
                                <input type="checkbox" name="{{ $method['setting'] }}" value="1" @checked((string) ($merged[$method['setting']] ?? ($method['default'] ? '1' : '0')) === '1')
                                    class="h-5 w-5 shrink-0 rounded border-slate-700 bg-slate-800 text-emerald-500 focus:ring-0">
                            </label>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition flex items-center gap-2">
                                <i class="fi fi-rr-disk text-sm"></i>
                                <span>Ödeme Ayarlarını Kaydet</span>
                            </button>
                        </div>
                    </form>

                    <!-- 👨‍🍳 FORM 5: MUTFAK & EKRAN AYARLARI -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-kitchen"
                        class="tab-content hidden space-y-6">
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
                                <select name="kitchen_refresh_sec"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-rose-500 focus:outline-none transition">
                                    <option value="5" {{ $merged['kitchen_refresh_sec'] == '5' ? 'selected' : '' }}>Her 5
                                        Saniyede Bir</option>
                                    <option value="10" {{ $merged['kitchen_refresh_sec'] == '10' ? 'selected' : '' }}>Her 10
                                        Saniyede Bir</option>
                                    <option value="15" {{ $merged['kitchen_refresh_sec'] == '15' ? 'selected' : '' }}>Her 15
                                        Saniyede Bir</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Geciken Sipariş İkaz Süresi</label>
                                <select name="kitchen_warning_min"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-rose-500 focus:outline-none transition">
                                    <option value="10" {{ $merged['kitchen_warning_min'] == '10' ? 'selected' : '' }}>10
                                        Dakika Sonra Kırmızıya Dönüşsün</option>
                                    <option value="15" {{ $merged['kitchen_warning_min'] == '15' ? 'selected' : '' }}>15
                                        Dakika Sonra Kırmızıya Dönüşsün</option>
                                    <option value="20" {{ $merged['kitchen_warning_min'] == '20' ? 'selected' : '' }}>20
                                        Dakika Sonra Kırmızıya Dönüşsün</option>
                                </select>
                            </div>

                            <div
                                class="sm:col-span-2 flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                <div>
                                    <div class="font-bold text-white">Yeni Sipariş Geldiğinde Sesli Uyarı Çal</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Mutfak ekranında yeni adisyon düştüğünde
                                        biper/zil sesi çalar.</div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="kitchen_sound_alert" value="0">
                                    <input type="checkbox" name="kitchen_sound_alert" value="1" {{ $merged['kitchen_sound_alert'] == '1' ? 'checked' : '' }} class="sr-only peer">
                                    <div
                                        class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600">
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-lg shadow-rose-600/30 transition flex items-center gap-2">
                                <i class="fi fi-rr-disk text-sm"></i>
                                <span>Mutfak Ayarlarını Kaydet</span>
                            </button>
                        </div>
                    </form>

                    <!-- 🔔 FORM 5.5: BİLDİRİM & SES AYARLARI -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-sounds"
                        class="tab-content hidden space-y-6">
                        @csrf
                        <input type="hidden" name="group" value="sounds">

                        <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fi fi-rr-volume text-yellow-400"></i>
                                    <span>Bildirim Sesleri & İkaz Ayarları</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">Sipariş ve adisyon düştüğünde çalacak melodi ve ses
                                    tercihlerini yapılandırın.</p>
                            </div>

                            <!-- LIVE SOUND TEST BUTTON -->
                            <button type="button" onclick="previewSelectedSound()"
                                class="px-4 py-2 rounded-xl bg-yellow-500/20 hover:bg-yellow-500/30 border border-yellow-500/40 text-yellow-300 text-xs font-extrabold transition flex items-center gap-2 cursor-pointer shadow-lg shadow-yellow-900/20">
                                <i class="fi fi-rr-volume text-sm"></i>
                                <span>📢 Melodiyi Dinle / Test Et</span>
                            </button>
                        </div>

                        <!-- SOUND OPTIONS GRID -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">

                            <!-- Sound Theme Selection -->
                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Bildirim Melodisi (Ses Teması)</label>
                                <select id="soundThemeSelect" name="notification_sound_theme"
                                    onchange="previewSelectedSound()"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-yellow-500 focus:outline-none transition">
                                    <option value="beep" {{ ($merged['notification_sound_theme'] ?? 'chime') == 'beep' ? 'selected' : '' }}>🔔 Standart Biper (Bip)</option>
                                    <option value="chime" {{ ($merged['notification_sound_theme'] ?? 'chime') == 'chime' ? 'selected' : '' }}>🎵 Yumuşak Ziller (Chime)</option>
                                    <option value="ding" {{ ($merged['notification_sound_theme'] ?? 'chime') == 'ding' ? 'selected' : '' }}>🎺 Dijital Pozitif (Ding)</option>
                                    <option value="siren" {{ ($merged['notification_sound_theme'] ?? 'chime') == 'siren' ? 'selected' : '' }}>🚨 Yüksek Mutfak Sireni</option>
                                    <option value="melodic" {{ ($merged['notification_sound_theme'] ?? 'chime') == 'melodic' ? 'selected' : '' }}>🎶 Melodik Pop Tonu</option>
                                </select>
                            </div>

                            <!-- Volume Level Selection -->
                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">Ses Seviyesi</label>
                                <select id="soundVolumeSelect" name="notification_sound_volume"
                                    onchange="previewSelectedSound()"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-yellow-500 focus:outline-none transition">
                                    <option value="20" {{ ($merged['notification_sound_volume'] ?? '80') == '20' ? 'selected' : '' }}>🔈 %20 (Kısık)</option>
                                    <option value="50" {{ ($merged['notification_sound_volume'] ?? '80') == '50' ? 'selected' : '' }}>🔉 %50 (Orta)</option>
                                    <option value="80" {{ ($merged['notification_sound_volume'] ?? '80') == '80' ? 'selected' : '' }}>🔊 %80 (Yüksek)</option>
                                    <option value="100" {{ ($merged['notification_sound_volume'] ?? '80') == '100' ? 'selected' : '' }}>📢 %100 (Maksimum)</option>
                                </select>
                            </div>

                            <!-- Sound Repeat Count -->
                            <div>
                                <label class="block font-bold text-slate-300 mb-1.5">İkaz Tekrar Sayısı</label>
                                <select name="notification_sound_repeat"
                                    class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-yellow-500 focus:outline-none transition">
                                    <option value="1" {{ ($merged['notification_sound_repeat'] ?? '1') == '1' ? 'selected' : '' }}>1 Defa Çalsın</option>
                                    <option value="2" {{ ($merged['notification_sound_repeat'] ?? '1') == '2' ? 'selected' : '' }}>2 Defa Üst Üste</option>
                                    <option value="3" {{ ($merged['notification_sound_repeat'] ?? '1') == '3' ? 'selected' : '' }}>3 Defa Üst Üste (Daha Belirgin)</option>
                                </select>
                            </div>

                        </div>

                        <!-- MODULE BASED TOGGLES -->
                        <div class="space-y-3 pt-2">
                            <label class="block font-bold text-slate-300">Modül Bazlı Sesli İkaz İzinleri:</label>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">

                                <!-- Delivery Sound Toggle -->
                                <div
                                    class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-white">Paket Servis</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Trendyol, YS, Getir siparişleri</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="delivery_sound_alert" value="0">
                                        <input type="checkbox" name="delivery_sound_alert" value="1" {{ ($merged['delivery_sound_alert'] ?? '1') == '1' ? 'checked' : '' }}
                                            class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500">
                                        </div>
                                    </label>
                                </div>

                                <!-- Kitchen Sound Toggle -->
                                <div
                                    class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-white">Mutfak Ekranı</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Yeni sipariş adisyonu</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="kitchen_sound_alert" value="0">
                                        <input type="checkbox" name="kitchen_sound_alert" value="1" {{ ($merged['kitchen_sound_alert'] ?? '1') == '1' ? 'checked' : '' }}
                                            class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500">
                                        </div>
                                    </label>
                                </div>

                                <!-- Table Sound Toggle -->
                                <div
                                    class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-white">Masa / Garson</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">Masa bildirim ikazı</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="table_sound_alert" value="0">
                                        <input type="checkbox" name="table_sound_alert" value="1" {{ ($merged['table_sound_alert'] ?? '1') == '1' ? 'checked' : '' }}
                                            class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500">
                                        </div>
                                    </label>
                                </div>

                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-yellow-600 hover:bg-yellow-500 text-white font-bold text-xs shadow-lg shadow-yellow-600/30 transition flex items-center gap-2">
                                <i class="fi fi-rr-disk text-sm"></i>
                                <span>Ses Ayarlarını Kaydet</span>
                            </button>
                        </div>
                    </form>

                    <!-- 🪑 FORM 6: MASA & SALON AYARLARI -->
                    <div id="form-tables" class="tab-content hidden space-y-8">

                        <!-- 1. MASA KURALLARI VE PARAMETRELERİ -->
                        <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
                            @csrf
                            <input type="hidden" name="group" value="tables">

                            <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                        <i class="fi fi-rr-chair text-teal-400"></i>
                                        <span>Masa & Salon Kuralları</span>
                                    </h2>
                                    <p class="text-xs text-slate-400 mt-0.5">Adisyon taşıma, masa birleştirme ve ekran
                                        görünüm ayarları.</p>
                                </div>
                            </div>

                            <div class="space-y-4 text-xs">
                                <div
                                    class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-white">Masalar Arası Adisyon Transferi / Taşıma</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">Açık adisyonu olan masadaki ürünleri
                                            başka masaya transfer etmeye izin verir.</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="enable_table_transfer" value="0">
                                        <input type="checkbox" name="enable_table_transfer" value="1" {{ ($merged['enable_table_transfer'] ?? '1') == '1' ? 'checked' : '' }}
                                            class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600">
                                        </div>
                                    </label>
                                </div>

                                <div
                                    class="flex items-center justify-between p-4 rounded-xl bg-slate-900/80 border border-slate-800">
                                    <div>
                                        <div class="font-bold text-white">Masa Birleştirme Yetkisi</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">Farklı masaların hesaplarını tek
                                            adisyonda birleştirmeye izin verir.</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="hidden" name="enable_table_merge" value="0">
                                        <input type="checkbox" name="enable_table_merge" value="1" {{ ($merged['enable_table_merge'] ?? '1') == '1' ? 'checked' : '' }}
                                            class="sr-only peer">
                                        <div
                                            class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600">
                                        </div>
                                    </label>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-4 p-4 rounded-xl bg-cyan-950/30 border border-cyan-500/25">
                                    <div>
                                        <div class="font-bold text-white flex items-center gap-2"><i class="fi fi-rr-qrcode text-cyan-400"></i>QR Menüden Masaya Sipariş</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">Aktifken müşteriler masa QR kodundan sepete ürün ekleyip adisyona sipariş gönderebilir. Pasifken QR menü yalnız görüntülenir.</div>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                        <input type="hidden" name="enable_qr_ordering" value="0">
                                        <input type="checkbox" name="enable_qr_ordering" value="1" {{ ($merged['enable_qr_ordering'] ?? '1') == '1' ? 'checked' : '' }} class="sr-only peer">
                                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-500"></div>
                                    </label>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1.5">Varsayılan Masa
                                            Kapasitesi</label>
                                        <select name="default_table_capacity"
                                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-teal-500 focus:outline-none transition">
                                            <option value="2" {{ ($merged['default_table_capacity'] ?? '4') == '2' ? 'selected' : '' }}>2 Kişilik Masa</option>
                                            <option value="4" {{ ($merged['default_table_capacity'] ?? '4') == '4' ? 'selected' : '' }}>4 Kişilik Masa</option>
                                            <option value="6" {{ ($merged['default_table_capacity'] ?? '4') == '6' ? 'selected' : '' }}>6 Kişilik Masa</option>
                                            <option value="8" {{ ($merged['default_table_capacity'] ?? '4') == '8' ? 'selected' : '' }}>8 Kişilik Masa</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1.5">Boşta/İkaz Masa Süresi</label>
                                        <select name="table_idle_warning_min"
                                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-teal-500 focus:outline-none transition">
                                            <option value="30" {{ ($merged['table_idle_warning_min'] ?? '45') == '30' ? 'selected' : '' }}>30 Dakika Sonra İkaz</option>
                                            <option value="45" {{ ($merged['table_idle_warning_min'] ?? '45') == '45' ? 'selected' : '' }}>45 Dakika Sonra İkaz</option>
                                            <option value="60" {{ ($merged['table_idle_warning_min'] ?? '45') == '60' ? 'selected' : '' }}>60 Dakika Sonra İkaz</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block font-bold text-slate-300 mb-1.5">Masa Kart Görünümü
                                            (Sütun)</label>
                                        <select name="table_grid_columns"
                                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-3 text-white focus:border-teal-500 focus:outline-none transition">
                                            <option value="3" {{ ($merged['table_grid_columns'] ?? '4') == '3' ? 'selected' : '' }}>3 Sütunlu Izgara</option>
                                            <option value="4" {{ ($merged['table_grid_columns'] ?? '4') == '4' ? 'selected' : '' }}>4 Sütunlu Izgara (Varsayılan)</option>
                                            <option value="6" {{ ($merged['table_grid_columns'] ?? '4') == '6' ? 'selected' : '' }}>6 Sütunlu Kompakt</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-slate-800 flex justify-end">
                                <button type="submit"
                                    class="px-6 py-3 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-lg shadow-teal-600/30 transition flex items-center gap-2">
                                    <i class="fi fi-rr-disk text-sm"></i>
                                    <span>Masa Kurallarını Kaydet</span>
                                </button>
                            </div>
                        </form>

                        <!-- 2. SALON YÖNETİMİ & EKLEME -->
                        <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-6 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                        <i class="fi fi-rr-building text-teal-400"></i>
                                        <span>Salon Yapılandırması</span>
                                    </h3>
                                    <p class="text-[11px] text-slate-400">Restoran salonlarını ekleyin, sıralayın veya
                                        yönetin.</p>
                                </div>
                            </div>

                            <!-- SALON EKLEME FORMU -->
                            <form action="{{ route('halls.store') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
                                @csrf
                                <input type="hidden" name="form_context" value="hall_create">
                                <input type="text" name="name" value="{{ old('form_context') === 'hall_create' ? old('name') : '' }}" placeholder="Örn: Teras, Bahçe, VIP Salon" required
                                    class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:border-teal-500 focus:outline-none">
                                <input type="number" name="sort_order" value="{{ old('form_context') === 'hall_create' ? old('sort_order') : '' }}" placeholder="Sıra (1, 2...)"
                                    class="w-28 bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:border-teal-500 focus:outline-none">
                                <button type="submit"
                                    class="px-5 py-2.5 bg-teal-600/90 hover:bg-teal-600 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-1.5 shrink-0">
                                    <i class="fi fi-rr-plus text-xs"></i>
                                    <span>Salon Ekle</span>
                                </button>
                            </form>

                            <!-- MEVCUT SALONLAR LİSTESİ -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                                @forelse($halls as $hall)
                                    <div
                                        class="bg-slate-950/80 border border-slate-800/80 rounded-xl p-3.5 flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-xs text-white">{{ $hall->name }}</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5">{{ $hall->tables_count }} Masa
                                                Tanımlı</div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button"
                                                onclick="openEditHallModal({{ Illuminate\Support\Js::from(['id' => $hall->id, 'name' => $hall->name, 'code' => $hall->code, 'sort_order' => $hall->sort_order ?? 0]) }})"
                                                class="w-8 h-8 rounded-lg bg-teal-500/15 hover:bg-teal-500/30 text-teal-300 flex items-center justify-center transition"
                                                title="Salonu Düzenle">
                                                <i class="fi fi-rr-edit text-xs"></i>
                                            </button>
                                            <form action="{{ route('halls.destroy', $hall->id) }}" method="POST"
                                                onsubmit="return confirm('Bu salonu silmek istediğinize emin misiniz?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="w-8 h-8 rounded-lg bg-rose-500/15 hover:bg-rose-500/30 text-rose-400 flex items-center justify-center transition"
                                                    title="Salonu Sil">
                                                    <i class="fi fi-rr-trash text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <div class="sm:col-span-3 p-4 text-center text-xs text-slate-500">Henüz salon eklenmemiş.
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- 3. HIZLI MASA EKLEME & MASA LİSTESİ -->
                        <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-6 space-y-4">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                                <div>
                                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                                        <i class="fi fi-rr-chair text-teal-400"></i>
                                        <span>Masa Tanımları & Yönetimi</span>
                                    </h3>
                                    <p class="text-[11px] text-slate-400">Sisteme yeni masa ekleyin veya mevcut masaları
                                        güncelleyin.</p>
                                </div>
                            </div>

                            <!-- MASA EKLEME FORMU -->
                            <form action="{{ route('tables.store') }}" method="POST"
                                class="app-form-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                                @csrf
                                <input type="hidden" name="form_context" value="table_create">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Masa Adı</label>
                                    <input type="text" name="name" value="{{ old('form_context') === 'table_create' ? old('name') : '' }}" placeholder="Örn: Masa 13" required
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-teal-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Masa Kodu <span class="font-normal text-slate-600">(opsiyonel)</span></label>
                                    <input type="text" name="code" value="{{ old('form_context') === 'table_create' ? old('code') : '' }}" maxlength="50" placeholder="Örn: M-13"
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-teal-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Salon</label>
                                    <select name="hall_id"
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-teal-500 focus:outline-none">
                                        <option value="">-- Salonsuz --</option>
                                        @foreach($halls as $hall)
                                            <option value="{{ $hall->id }}" @selected(old('form_context') === 'table_create' && old('hall_id') == $hall->id)>{{ $hall->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Kapasite (Kişi)</label>
                                    <input type="number" name="capacity" value="{{ old('form_context') === 'table_create' ? old('capacity', 4) : 4 }}" min="1" max="100" required
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-white focus:border-teal-500 focus:outline-none">
                                </div>
                                <div class="flex items-end">
                                    <button type="submit"
                                        class="w-full py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center justify-center gap-1.5">
                                        <i class="fi fi-rr-plus text-xs"></i>
                                        <span>Masa Ekle</span>
                                    </button>
                                </div>
                            </form>

                            <!-- MEVCUT MASALAR TABLOSU -->
                            <div class="overflow-x-auto pt-2">
                                <table class="w-full text-left text-xs text-slate-300">
                                    <thead
                                        class="bg-slate-950/80 text-[10px] font-extrabold uppercase text-slate-400 border-b border-slate-800">
                                        <tr>
                                            <th class="px-4 py-3">Masa Adı</th>
                                            <th class="px-4 py-3">Salon</th>
                                            <th class="px-4 py-3">Kapasite</th>
                                            <th class="px-4 py-3">Durum</th>
                                            <th class="px-4 py-3 text-right">İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/60">
                                        @forelse($tables as $t)
                                            @php
                                                $tableStatus = is_object($t->status) ? $t->status->value : $t->status;
                                            @endphp
                                            <tr class="hover:bg-slate-800/30 transition">
                                                <td class="px-4 py-3 font-bold text-white">{{ $t->name }}</td>
                                                <td class="px-4 py-3 text-slate-400">{{ $t->hall?->name ?? 'Salonsuz' }}</td>
                                                <td class="px-4 py-3 text-slate-300">{{ $t->capacity }} Kişilik</td>
                                                <td class="px-4 py-3">
                                                    @if($tableStatus === 'occupied')
                                                        <span
                                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300">DOLU</span>
                                                    @elseif($tableStatus === 'awaiting_payment')
                                                        <span
                                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300">HESAP
                                                            İSTENDİ</span>
                                                    @else
                                                        <span
                                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">BOŞ</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button type="button"
                                                            onclick="openEditTableModal({{ Illuminate\Support\Js::from(['id' => $t->id, 'name' => $t->name, 'code' => $t->code, 'hall_id' => $t->hall_id, 'capacity' => $t->capacity, 'status' => $tableStatus, 'is_active' => $t->is_active, 'notes' => $t->notes]) }})"
                                                            class="px-2.5 py-1.5 rounded-lg bg-teal-500/15 hover:bg-teal-500/30 text-teal-300 font-bold transition flex items-center gap-1">
                                                            <i class="fi fi-rr-edit text-xs"></i>
                                                            <span>Düzenle</span>
                                                        </button>
                                                        <form action="{{ route('tables.destroy', $t->id) }}" method="POST"
                                                            class="inline-block"
                                                            onsubmit="return confirm('Masa {{ $t->name }} silinsin mi?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="px-2.5 py-1.5 rounded-lg bg-rose-500/15 hover:bg-rose-500/30 text-rose-400 font-bold transition flex items-center gap-1">
                                                                <i class="fi fi-rr-trash text-xs"></i>
                                                                <span>Sil</span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="px-4 py-6 text-center text-slate-500">Sistemde henüz
                                                    kayıtlı masa yok.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- 🛵 FORM 7: ONLINE PAKET SERVİS ENTEGRASYONLARI -->
                    <form action="{{ route('settings.update') }}" method="POST" id="form-integrations"
                        class="tab-content hidden space-y-6">
                        @csrf
                        <input type="hidden" name="group" value="integrations">

                        <div class="border-b border-slate-800 pb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fi fi-rr-box-alt text-orange-400"></i>
                                    <span>Online Paket Servis Entegrasyonları</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-1">Trendyol Go, Yemeksepeti, GetirYemek ve Migros Yemek
                                    API ve Mağaza Yapılandırması</p>
                            </div>

                            <button type="submit"
                                class="px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-extrabold text-xs shadow-lg shadow-orange-900/30 transition flex items-center gap-2 cursor-pointer">
                                <i class="fi fi-rr-disk text-xs"></i>
                                <span>Ayarları Kaydet</span>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-5 text-xs">
                            @foreach(['trendyol' => 'Trendyol Go', 'yemeksepeti' => 'Yemeksepeti', 'getir' => 'GetirYemek', 'migros' => 'Migros Yemek'] as $key => $name)
                                @php $integ = $integrations[$key] ?? null; @endphp
                                <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div class="font-extrabold text-sm text-white flex items-center gap-2.5">
                                            @if($key === 'trendyol')
                                                <svg class="w-5 h-5 text-orange-400 fill-current" viewBox="0 0 24 24">
                                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                                                </svg>
                                            @elseif($key === 'yemeksepeti')
                                                <svg class="w-5 h-5 text-pink-400 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.46 3.89 3.44 4.37L5 22h2.1l1.1-7h.6l1.1 7H12l-1.44-8.63C12.54 12.89 14 11.12 14 9V2h-2v7h-1zm9-7v8h-2V2h-2v8c0 2.21 1.79 4 4 4v8h2V2h-2z" />
                                                </svg>
                                            @elseif($key === 'getir')
                                                <svg class="w-5 h-5 text-purple-400 fill-current" viewBox="0 0 24 24">
                                                    <path d="M13 2L3 14h7v8l10-12h-7z" />
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-amber-400 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm7 17H5V8h14v12z" />
                                                </svg>
                                            @endif
                                            <span>{{ $name }} Entegrasyonu</span>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <label
                                                class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-200">
                                                <input type="checkbox" name="integrations[{{ $key }}][is_active]" value="1" {{ ($integ && $integ->is_active) ? 'checked' : '' }}
                                                    class="w-4 h-4 accent-emerald-500 rounded">
                                                <span>Kanal Aktif</span>
                                            </label>
                                            <label
                                                class="flex items-center gap-2 cursor-pointer text-xs font-bold text-sky-400">
                                                <input type="checkbox" name="integrations[{{ $key }}][auto_accept]" value="1" {{ ($integ && $integ->auto_accept) ? 'checked' : '' }}
                                                    class="w-4 h-4 accent-sky-500 rounded">
                                                <span>Otomatik Onay (Auto-Accept)</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block font-bold text-slate-300 mb-1">Mağaza Adı</label>
                                            <input type="text" name="integrations[{{ $key }}][store_name]"
                                                value="{{ $integ ? $integ->store_name : '' }}"
                                                placeholder="{{ $name }} Restoran"
                                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white">
                                        </div>
                                        <div>
                                            <label class="block font-bold text-slate-300 mb-1">Tedarikçi / Mağaza ID (Supplier
                                                ID)</label>
                                            <input type="text" name="integrations[{{ $key }}][store_id]"
                                                value="{{ $integ ? $integ->store_id : '' }}" placeholder="Örn: 1098412"
                                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white font-mono">
                                        </div>
                                        <div>
                                            <label class="block font-bold text-slate-300 mb-1">API Key (API Anahtarı)</label>
                                            <input type="password" name="integrations[{{ $key }}][api_key]"
                                                value="" autocomplete="new-password"
                                                placeholder="{{ $integ && $integ->getRawOriginal('api_key') ? 'Kayıtlı — değiştirmek için yeni değer girin' : 'API Key' }}"
                                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white font-mono">
                                        </div>
                                        <div>
                                            <label class="block font-bold text-slate-300 mb-1">API Secret (Gizli
                                                Anahtar)</label>
                                            <input type="password" name="integrations[{{ $key }}][api_secret]"
                                                value="" autocomplete="new-password"
                                                placeholder="{{ $integ && $integ->getRawOriginal('api_secret') ? 'Kayıtlı — değiştirmek için yeni değer girin' : 'API Secret' }}"
                                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-white font-mono">
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pt-4 border-t border-slate-800 flex justify-end">
                            <button type="submit"
                                class="px-6 py-3 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-extrabold text-xs shadow-lg shadow-orange-900/30 transition flex items-center gap-2 cursor-pointer">
                                <i class="fi fi-rr-disk text-sm"></i>
                                <span>Entegrasyon Ayarlarını Kaydet</span>
                            </button>
                        </div>
                    </form>

                </div>
            </section>

        </main>
    </div>

    <!-- 🏢 SALON DÜZENLEME MODALI -->
    <div id="edit-hall-modal" role="dialog" aria-modal="true" aria-hidden="true" data-close-on-overlay="true"
        class="app-modal fixed inset-0 z-[70] hidden bg-slate-950/80 backdrop-blur-md flex justify-center p-3 sm:p-4">
        <div
            class="app-modal-panel modal-card bg-[#121525] border border-slate-800 rounded-2xl max-w-md w-full p-4 sm:p-6 shadow-2xl space-y-5 animate-fade-in relative">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fi fi-rr-edit text-teal-400"></i>
                    <span id="edit-hall-modal-title">Salon Düzenle</span>
                </h3>
                <button type="button" onclick="closeEditHallModal()"
                    class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                    <i class="fi fi-rr-cross text-xs"></i>
                </button>
            </div>

            <form id="edit-hall-form" action="" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PATCH')
                <input type="hidden" name="form_context" id="edit-hall-form-context" value="hall_update">

                <div>
                    <label class="block font-bold text-slate-300 mb-1.5">Salon Adı</label>
                    <input type="text" name="name" id="edit-hall-name" required
                        class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1.5">Sıralama (İsteğe Bağlı)</label>
                    <input type="number" name="sort_order" id="edit-hall-sort-order"
                        class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                    <button type="button" onclick="closeEditHallModal()"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition">
                        İptal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-lg shadow-teal-600/30 transition flex items-center gap-1.5">
                        <i class="fi fi-rr-disk text-xs"></i>
                        <span>Kaydet</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 🪑 MASA DÜZENLEME MODALI -->
    <div id="edit-table-modal" role="dialog" aria-modal="true" aria-hidden="true" data-close-on-overlay="true"
        class="app-modal fixed inset-0 z-[70] hidden bg-slate-950/80 backdrop-blur-md flex justify-center p-3 sm:p-4">
        <div
            class="app-modal-panel modal-card bg-[#121525] border border-slate-800 rounded-2xl max-w-lg w-full p-4 sm:p-6 shadow-2xl space-y-5 animate-fade-in relative">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                    <i class="fi fi-rr-edit text-teal-400"></i>
                    <span id="edit-modal-title">Masa Düzenle</span>
                </h3>
                <button type="button" onclick="closeEditTableModal()"
                    class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition">
                    <i class="fi fi-rr-cross text-xs"></i>
                </button>
            </div>

            <form id="edit-table-form" action="" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PATCH')
                <input type="hidden" name="form_context" id="edit-table-form-context" value="table_update">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">Masa Adı</label>
                        <input type="text" name="name" id="edit-table-name" required
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">Masa Kodu (İsteğe Bağlı)</label>
                        <input type="text" name="code" id="edit-table-code" placeholder="Örn: T-01"
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">Salon</label>
                        <select name="hall_id" id="edit-table-hall-id"
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                            <option value="">-- Salonsuz --</option>
                            @foreach($halls as $hall)
                                <option value="{{ $hall->id }}">{{ $hall->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">Kapasite (Kişi Sayısı)</label>
                        <input type="number" name="capacity" id="edit-table-capacity" min="1" max="50" required
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">Masa Durumu</label>
                        <select name="status" id="edit-table-status"
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                            <option value="available">Boş / Kullanıma Hazır</option>
                            <option value="reserved">Rezerve</option>
                            <option value="occupied" disabled>Dolu · Adisyondan otomatik</option>
                            <option value="awaiting_payment" disabled>Hesap Bekliyor · Adisyondan otomatik</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-300 mb-1.5">Aktiflik Durumu</label>
                        <select name="is_active" id="edit-table-is-active"
                            class="w-full bg-slate-900 border border-slate-700/80 rounded-xl px-4 py-2.5 text-white focus:border-teal-500 focus:outline-none transition">
                            <option value="1">Aktif (Kullanımda)</option>
                            <option value="0">Pasif (Devre Dışı)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-300 mb-1.5">Notlar / Açıklama</label>
                    <textarea name="notes" id="edit-table-notes" rows="2" placeholder="Örn: Cam kenarı, rezervasyonlu vb."
                        class="w-full bg-slate-900 border border-slate-700/80 rounded-xl p-3 text-white focus:border-teal-500 focus:outline-none transition"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                    <button type="button" onclick="closeEditTableModal()"
                        class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition">
                        İptal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs shadow-lg shadow-teal-600/30 transition flex items-center gap-1.5">
                        <i class="fi fi-rr-disk text-xs"></i>
                        <span>Kaydet</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT TAB SWITCHER -->
    <script>
        function switchTab(tabId, syncUrl = true) {
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

            if (syncUrl) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.replaceState({}, '', url);
            }
        }

        // URL parametresine göre varsayılan tabı seç (?tab=pos)
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const failedContext = {{ Illuminate\Support\Js::from(old('form_context')) }};
            const activeTab = failedContext?.startsWith('hall_') || failedContext?.startsWith('table_')
                ? 'tables'
                : (urlParams.get('tab') || 'general');
            switchTab(activeTab, false);

            if (failedContext?.startsWith('hall_update_')) {
                const id = Number(failedContext.replace('hall_update_', ''));
                if (hallEditData[id]) {
                    openEditHallModal({
                        ...hallEditData[id],
                        name: {{ Illuminate\Support\Js::from(old('name')) }} || hallEditData[id].name,
                        sort_order: {{ Illuminate\Support\Js::from(old('sort_order')) }} ?? hallEditData[id].sort_order,
                    });
                }
            } else if (failedContext?.startsWith('table_update_')) {
                const id = Number(failedContext.replace('table_update_', ''));
                if (tableEditData[id]) {
                    openEditTableModal({
                        ...tableEditData[id],
                        name: {{ Illuminate\Support\Js::from(old('name')) }} || tableEditData[id].name,
                        code: {{ Illuminate\Support\Js::from(old('code')) }},
                        hall_id: {{ Illuminate\Support\Js::from(old('hall_id')) }},
                        capacity: {{ Illuminate\Support\Js::from(old('capacity')) }} || tableEditData[id].capacity,
                        status: {{ Illuminate\Support\Js::from(old('status')) }} || tableEditData[id].status,
                        is_active: {{ Illuminate\Support\Js::from(old('is_active')) }} ?? tableEditData[id].is_active,
                        notes: {{ Illuminate\Support\Js::from(old('notes')) }},
                    });
                }
            }
        });

        /* ---------------- YAZDIRMA KUYRUĞU ---------------- */

        const CSRF = '{{ csrf_token() }}';
        const hallEditData = {{ Illuminate\Support\Js::from($hallEditData) }};
        const tableEditData = {{ Illuminate\Support\Js::from($tableEditData) }};
        const hallUpdateUrlTemplate = {{ Illuminate\Support\Js::from(route('halls.update', ['hall' => '__HALL__'])) }};
        const tableUpdateUrlTemplate = {{ Illuminate\Support\Js::from(route('tables.update', ['table' => '__TABLE__'])) }};

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

        /* ---------------- SALON DÜZENLEME MODALI ---------------- */

        function openEditHallModal(hall) {
            document.getElementById('edit-hall-form').action = hallUpdateUrlTemplate.replace('__HALL__', encodeURIComponent(hall.id));
            document.getElementById('edit-hall-form-context').value = 'hall_update_' + hall.id;
            document.getElementById('edit-hall-modal-title').innerText = hall.name + ' - Salon Düzenle';
            document.getElementById('edit-hall-name').value = hall.name || '';
            document.getElementById('edit-hall-sort-order').value = hall.sort_order || 0;
            window.openAppModal('edit-hall-modal');
        }

        function closeEditHallModal() {
            window.closeAppModal('edit-hall-modal');
        }

        /* ---------------- MASA DÜZENLEME MODALI ---------------- */

        function openEditTableModal(table) {
            document.getElementById('edit-table-form').action = tableUpdateUrlTemplate.replace('__TABLE__', encodeURIComponent(table.id));
            document.getElementById('edit-table-form-context').value = 'table_update_' + table.id;
            document.getElementById('edit-modal-title').innerText = table.name + ' - Masa Düzenle';
            document.getElementById('edit-table-name').value = table.name || '';
            document.getElementById('edit-table-code').value = table.code || '';
            document.getElementById('edit-table-hall-id').value = table.hall_id || '';
            document.getElementById('edit-table-capacity').value = table.capacity || 4;
            document.getElementById('edit-table-status').value = ['available', 'reserved', 'occupied', 'awaiting_payment'].includes(table.status) ? table.status : 'available';
            document.getElementById('edit-table-is-active').value = table.is_active ? 1 : 0;
            document.getElementById('edit-table-notes').value = table.notes || '';
            window.openAppModal('edit-table-modal');
        }

        function closeEditTableModal() {
            window.closeAppModal('edit-table-modal');
        }

        /* ---------------- BİLDİRİM SESİ PREVIEW (WEB AUDIO API) ---------------- */
        function previewSelectedSound() {
            const theme = document.getElementById('soundThemeSelect')?.value || 'chime';
            const volumeVal = (parseInt(document.getElementById('soundVolumeSelect')?.value || '80', 10)) / 100;

            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const gain = ctx.createGain();
                gain.gain.setValueAtTime(volumeVal, ctx.currentTime);
                gain.connect(ctx.destination);

                if (theme === 'beep') {
                    const osc = ctx.createOscillator();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    osc.connect(gain);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.3);
                } else if (theme === 'chime') {
                    const notes = [523.25, 659.25, 783.99]; // C5, E5, G5
                    notes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.12);
                        osc.connect(gain);
                        osc.start(ctx.currentTime + idx * 0.12);
                        osc.stop(ctx.currentTime + idx * 0.12 + 0.25);
                    });
                } else if (theme === 'ding') {
                    const osc = ctx.createOscillator();
                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(1046.5, ctx.currentTime); // C6
                    osc.connect(gain);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.4);
                } else if (theme === 'siren') {
                    const freqs = [800, 1200, 800, 1200];
                    freqs.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        osc.type = 'sawtooth';
                        osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.1);
                        osc.connect(gain);
                        osc.start(ctx.currentTime + idx * 0.1);
                        osc.stop(ctx.currentTime + idx * 0.1 + 0.08);
                    });
                } else if (theme === 'melodic') {
                    const notes = [659.25, 830.61, 987.77, 1318.51]; // E5, G#5, B5, E6
                    notes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, ctx.currentTime + idx * 0.09);
                        osc.connect(gain);
                        osc.start(ctx.currentTime + idx * 0.09);
                        osc.stop(ctx.currentTime + idx * 0.09 + 0.3);
                    });
                }
            } catch (e) {
                console.error('Audio play error', e);
            }
        }
    </script>
@endsection
