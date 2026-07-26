@extends('layouts.app')

@section('title', '⚡ Hızlı Satış - POS Portalı')

@section('styles')
<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(15, 23, 42, 0.6);
        border-radius: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(99, 102, 241, 0.3);
        border-radius: 8px;
    }
</style>
@endsection

@section('content')
<div class="flex flex-col h-screen bg-[#07090e] text-slate-100 font-sans overflow-hidden">
    
    <!-- Status Alert Container -->
    <div id="alertContainer" class="fixed top-6 right-6 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- Hidden Input for Kitchen Toggle JS compatibility -->
    <input type="checkbox" id="sendToKitchenToggle" checked class="hidden">

    <!-- Main Content Area: Far Left Actions, Left Cart, & Right Product Catalog -->
    <div class="flex-1 flex flex-col md:flex-row overflow-hidden relative">

        <!-- 1. FAR LEFT SIDEBAR (QUICK SALE POS ACTIONS) -->
        <div class="w-20 md:w-24 shrink-0 bg-[#0d101a] border-r border-slate-800/80 flex flex-col items-center py-3.5 px-2 gap-2.5 z-30 shadow-2xl">
            <!-- BEYAZ / KARANLIK MOD TOGGLE -->
            <button type="button" onclick="toggleTheme()" title="Temayı Değiştir (Beyaz / Karanlık Mod)"
                class="flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-white transition-all w-full py-2 px-1.5 rounded-2xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 group cursor-pointer shadow-md mb-0.5">
                <i class="fi fi-rr-moon text-lg text-indigo-400 theme-toggle-icon group-hover:scale-110 transition-transform"></i>
                <span class="text-[9px] font-bold text-center theme-toggle-text text-slate-300">Karanlık</span>
            </button>

            <!-- DÖNÜŞ (DASHBOARD) -->
            <a href="{{ route('dashboard') }}" title="Panele Dön"
                class="flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-white transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/90 hover:bg-slate-800 border border-slate-800 group cursor-pointer shadow-md mb-1">
                <i class="fi fi-rr-arrow-left text-xl text-slate-300 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-bold text-center">Geri</span>
            </a>

            <!-- İKRAM ET -->
            <button type="button" onclick="openQuickTreatModal()" title="Seçilen ürünleri ikram yap"
                class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/80 hover:bg-amber-600/30 border border-slate-800/80 hover:border-amber-500/50 group cursor-pointer shadow-md">
                <i class="fi fi-rr-gift text-xl text-amber-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-bold text-center">İkram Et</span>
            </button>

            <!-- BÖL & ÖDE -->
            <button type="button" onclick="openQuickSplitModal()" title="Seçilen ürünleri böl ve ayrı öde"
                class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/80 hover:bg-violet-600/30 border border-slate-800/80 hover:border-violet-500/50 group cursor-pointer shadow-md">
                <i class="fi fi-rr-code-branch text-xl text-violet-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-bold text-center">Böl & Öde</span>
            </button>

            <!-- MASAYA AKTAR -->
            <button type="button" onclick="openTableTransferModal()" title="Hızlı Satış sepetini masaya aktar"
                class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/80 hover:bg-sky-600/30 border border-slate-800/80 hover:border-sky-500/50 group cursor-pointer shadow-md">
                <i class="fi fi-rr-shuffle text-xl text-sky-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-bold text-center leading-tight">Masaya<br>Aktar</span>
            </button>

            <!-- İSKONTO -->
            <button type="button" onclick="openQuickDiscountModal()" title="İskonto / İndirim Uygula"
                class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/80 hover:bg-emerald-600/30 border border-slate-800/80 hover:border-emerald-500/50 group cursor-pointer shadow-md">
                <i class="fi fi-rr-tags text-xl text-emerald-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-bold text-center">İskonto</span>
            </button>

            <!-- MUTFAĞA GÖNDER (KDS TOGGLE SWITCH) -->
            <button type="button" id="kitchenToggleBtn" onclick="toggleKitchenSend()" title="Mutfağa Gönder (KDS) Açık/Kapalı"
                class="flex flex-col items-center justify-center gap-1.5 transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/80 hover:bg-slate-800 border border-slate-800 group cursor-pointer shadow-md mt-auto">
                
                <!-- Sleek Toggle Switch Container -->
                <div id="kitchenTogglePill" class="w-9 h-5 rounded-full p-0.5 bg-orange-500 flex items-center transition-colors shadow-inner">
                    <div id="kitchenToggleDot" class="w-4 h-4 rounded-full bg-white shadow-md transform translate-x-4 transition-transform duration-200"></div>
                </div>

                <span id="kitchenToggleLabel" class="text-[10px] font-bold text-orange-300 text-center leading-tight">Mutfak<br>Açık</span>
            </button>

            <!-- SEPETİ SIFIRLA -->
            <button type="button" onclick="clearCart()" title="Sepeti Sıfırla"
                class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-2.5 px-1.5 rounded-2xl bg-slate-900/80 hover:bg-rose-600/30 border border-slate-800/80 hover:border-rose-500/50 group cursor-pointer shadow-md">
                <i class="fi fi-rr-trash text-xl text-rose-400 group-hover:scale-110 transition-transform"></i>
                <span class="text-[10px] font-bold text-center">Temizle</span>
            </button>
        </div>

        <!-- 2. LEFT: Shopping Cart & Quick Checkout Panel (RIGHT NEXT TO POS BUTTONS) -->
        <div class="w-full md:w-[380px] lg:w-[420px] bg-[#111523] border-r border-slate-800/80 flex flex-col shrink-0 z-20">
            
            <!-- Cart Header -->
            <div class="p-4 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/40">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center border border-indigo-500/20">
                        <i class="fi fi-rr-shopping-cart text-sm"></i>
                    </span>
                    <h2 class="text-sm font-bold text-white">Sepet Kalemleri</h2>
                </div>
                <span id="cartCountBadge" class="px-2.5 py-1 rounded-full bg-slate-800 text-xs font-bold text-slate-300 border border-slate-700/50">
                    0 Kalem
                </span>
            </div>

            <!-- Cart Items List Container -->
            <div id="cartContainer" class="flex-1 overflow-y-auto custom-scrollbar p-4 flex flex-col gap-2.5">
                <div id="emptyCartState" class="my-auto py-12 text-center text-slate-500">
                    <div class="w-16 h-16 rounded-3xl bg-slate-900/60 border border-slate-800/80 flex items-center justify-center mx-auto mb-3 text-slate-600">
                        <i class="fi fi-rr-shopping-bag text-2xl"></i>
                    </div>
                    <p class="text-xs font-semibold text-slate-400">Sepetiniz boş</p>
                    <p class="text-[11px] text-slate-500 mt-1">Sağ taraftaki katalogdan ürün seçebilirsiniz</p>
                </div>
                <div id="cartItemsList" class="flex flex-col gap-2.5"></div>
            </div>

            <!-- Cart Summary & Payment Panel (Görseldeki Tasarım) -->
            <div class="p-5 bg-[#0e121d] border-t border-slate-800/80 flex flex-col gap-3">
                
                <!-- TOP ROW: Ara Toplam -->
                <div class="flex justify-between items-center text-sm font-semibold text-slate-400">
                    <span>Ara Toplam:</span>
                    <span id="subtotalDisplay" class="font-sans font-black text-white text-base">₺0.00</span>
                </div>

                <div id="discountRow" class="hidden flex justify-between items-center text-xs font-semibold text-rose-400">
                    <span class="flex items-center gap-1">Uygulanan İskonto:</span>
                    <div class="flex items-center gap-2">
                        <span id="discountDisplay" class="font-sans font-black text-rose-400 text-sm">-₺0.00</span>
                        <button type="button" onclick="removeDiscount()" title="İskontoyu Geri Al / Kaldır" class="w-6 h-6 rounded-lg bg-rose-500/20 hover:bg-rose-600 text-rose-300 hover:text-white flex items-center justify-center transition-all cursor-pointer shadow-sm">
                            <i class="fi fi-rr-cross-small text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Hidden Discount Input for Modal & Calculation -->
                <input type="hidden" id="discountInput" value="0">

                <!-- DIVIDER LINE & BOTTOM ROW -->
                <div class="border-t border-slate-800/80 pt-3.5 flex items-center justify-between">
                    
                    <!-- LEFT: GENEL TOPLAM -->
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black uppercase text-slate-400 tracking-wider">GENEL TOPLAM</span>
                        <span id="grandTotalDisplay" class="text-3xl sm:text-4xl font-black text-white tracking-tight font-sans">₺0.00</span>
                    </div>

                    <!-- RIGHT: ÖDEME AL BUTTON -->
                    <button onclick="openQuickPaymentModal()" id="btnOpenPaymentModal" disabled
                        class="px-6 py-3.5 rounded-2xl bg-[#059669] hover:bg-[#10b981] disabled:bg-slate-800 text-white font-extrabold text-xs tracking-wider uppercase shadow-lg shadow-emerald-900/30 disabled:shadow-none disabled:text-slate-500 disabled:opacity-40 disabled:pointer-events-none transition-all flex items-center justify-center gap-2.5 cursor-pointer shrink-0">
                        <i class="fi fi-rr-credit-card text-base"></i>
                        <span>ÖDEME AL</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- 3. RIGHT: Product Search, Category Filter & Products Grid -->
        <div class="flex-1 flex flex-col p-4 sm:p-6 overflow-hidden bg-[#0d101a]/50">
            
            <!-- Category Filter Bar & Search -->
            <div class="flex flex-col sm:flex-row gap-3 mb-5 shrink-0">
                <!-- Search Box -->
                <div class="relative flex-1">
                    <i class="fi fi-rr-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                    <input type="text" id="searchInput" oninput="filterProducts()" placeholder="Ürün adı, SKU veya kategori ara..." 
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-900/80 border border-slate-800 rounded-2xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all">
                </div>

                <!-- Categories Scroll Bar -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0 custom-scrollbar max-w-full">
                    <button onclick="selectCategory('all')" id="cat-btn-all" class="cat-btn active px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition-all bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 border border-indigo-500">
                        Tümü ({{ count($products) }})
                    </button>
                    @foreach($categories as $category)
                        <button onclick="selectCategory({{ $category->id }})" id="cat-btn-{{ $category->id }}" class="cat-btn px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white bg-slate-900/60 hover:bg-slate-800 border border-slate-800/80 whitespace-nowrap transition-all">
                            {{ $category->name }} ({{ $category->products_count }})
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Product Grid -->
            <div class="flex-1 overflow-y-auto custom-scrollbar pr-1">
                <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-4">
                    @forelse($products as $product)
                        @php
                            $effectivePrice = (float) ($product->discounted_price ?: $product->price);
                            $hasDiscount = $product->discounted_price && $product->discounted_price < $product->price;
                            $image = $product->image_path ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=300&q=80';
                            $isOutOfStock = $product->track_stock && $product->stock_quantity <= 0;
                        @endphp
                        <div class="product-card group relative bg-slate-900/60 {{ $isOutOfStock ? 'opacity-60 border-rose-900/50' : 'hover:bg-slate-800/80 border-slate-800/80 hover:border-indigo-500/50 cursor-pointer' }} rounded-2xl p-3 flex flex-col justify-between select-none overflow-hidden"
                            data-product-id="{{ $product->id }}"
                            data-product-name="{{ e($product->name) }}"
                            data-product-price="{{ $effectivePrice }}"
                            data-product-image="{{ e($image) }}"
                            data-category-id="{{ $product->category_id }}"
                            data-name="{{ mb_strtolower($product->name) }}"
                            data-sku="{{ mb_strtolower($product->sku ?? '') }}"
                            data-out-of-stock="{{ $isOutOfStock ? '1' : '0' }}"
                            onclick="addToCart({{ $product->id }}, '{{ e($product->name) }}', {{ $effectivePrice }}, '{{ e($image) }}', {{ $isOutOfStock ? 1 : 0 }})">
                            
                            <!-- Product Image / Badge -->
                            <div class="relative w-full aspect-[4/3] rounded-xl overflow-hidden mb-2 bg-slate-950">
                                <img src="{{ $image }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=300&q=80';">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-60"></div>
                                @if($isOutOfStock)
                                    <div class="absolute inset-0 bg-slate-950/85 flex flex-col items-center justify-center p-2 backdrop-blur-xs z-20">
                                        <span class="px-2 py-0.5 rounded-lg bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[10px] font-black uppercase tracking-wider mb-0.5">
                                            🚫 Stok Tükendi (0)
                                        </span>
                                        <span class="text-[9px] text-slate-400 font-semibold">Satış Yapılamaz</span>
                                    </div>
                                @elseif($hasDiscount)
                                    <span class="absolute top-2 left-2 px-2 py-0.5 rounded-lg bg-rose-500 text-[10px] font-extrabold text-white shadow-md">
                                        İNDİRİM
                                    </span>
                                @endif
                                <span class="absolute bottom-2 right-2 px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-md text-[10px] font-semibold text-slate-300 border border-slate-700/50">
                                    {{ $product->category->name ?? 'Genel' }}
                                </span>
                            </div>

                            <!-- Title & SKU -->
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-slate-100 group-hover:text-indigo-300 transition-colors line-clamp-1">
                                    {{ $product->name }}
                                </h3>
                                @if($product->sku)
                                    <p class="text-[10px] font-mono text-slate-500">SKU: {{ $product->sku }}</p>
                                @endif
                            </div>

                            <!-- Price & Add Button -->
                            <div class="mt-3 flex items-center justify-between pt-2 border-t border-slate-800/60">
                                <div>
                                    <span class="text-sm font-extrabold text-emerald-400">
                                        ₺{{ number_format($effectivePrice, 2) }}
                                    </span>
                                    @if($hasDiscount)
                                        <span class="block text-[10px] text-slate-500 line-through">
                                            ₺{{ number_format($product->price, 2) }}
                                        </span>
                                    @endif
                                </div>
                                <div class="w-7 h-7 rounded-xl bg-indigo-600/20 group-hover:bg-indigo-600 text-indigo-400 group-hover:text-white flex items-center justify-center transition-all">
                                    <i class="fi fi-rr-plus text-xs"></i>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-16 text-center text-slate-500">
                            <i class="fi fi-rr-box-open text-4xl mb-3 block"></i>
                            <p class="text-sm">Henüz kayıtlı aktif ürün bulunmuyor.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        </div>
    </div>
</div>

<!-- 💳 HIZLI SATIŞ ÖDEME MODALI -->
<div id="quickSalePaymentModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col space-y-4">
        
        <!-- Header -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-emerald-500/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fi fi-rr-credit-card text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Ödeme Al & İşlemi Tamamla</h3>
                    <p class="text-xs text-slate-400">Lütfen ödeme yöntemini seçiniz</p>
                </div>
            </div>
            <button onclick="closeQuickPaymentModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-5">
            <!-- Total Amount Card -->
            <div class="bg-gradient-to-r from-emerald-950/60 to-slate-900 border border-emerald-500/30 rounded-2xl p-4 flex items-center justify-between shadow-inner">
                <div>
                    <span class="text-[10px] font-black uppercase text-emerald-400 tracking-wider block">Ödenecek Toplam Tutar</span>
                    <span id="modalGrandTotalDisplay" class="text-3xl font-black text-white">₺0.00</span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-slate-400 block">Sepet Kalem</span>
                    <span id="modalCartCountDisplay" class="text-xs font-bold text-indigo-300">0 Kalem</span>
                </div>
            </div>

            <!-- Fast Payment Method Buttons Grid -->
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Ödeme Yöntemi Seçin</label>
                <div class="grid grid-cols-3 gap-3">
                    <button onclick="submitQuickSalePayment('nakit')"
                        class="p-4 rounded-2xl bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-300 hover:text-white transition-all flex flex-col items-center justify-center gap-2 group cursor-pointer shadow-md">
                        <i class="fi fi-rr-money-bill-wave text-2xl text-emerald-400 group-hover:text-white group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-black">NAKİT</span>
                    </button>

                    <button onclick="submitQuickSalePayment('kredi_karti')"
                        class="p-4 rounded-2xl bg-indigo-600/20 hover:bg-indigo-600 border border-indigo-500/30 text-indigo-300 hover:text-white transition-all flex flex-col items-center justify-center gap-2 group cursor-pointer shadow-md">
                        <i class="fi fi-rr-credit-card text-2xl text-indigo-400 group-hover:text-white group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-black">K. KARTI</span>
                    </button>

                    <button onclick="submitQuickSalePayment('yemek_karti')"
                        class="p-4 rounded-2xl bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-300 hover:text-white transition-all flex flex-col items-center justify-center gap-2 group cursor-pointer shadow-md">
                        <i class="fi fi-rr-ticket text-2xl text-amber-400 group-hover:text-white group-hover:scale-110 transition-transform"></i>
                        <span class="text-xs font-black">YEMEK K.</span>
                    </button>
                </div>
            </div>

            <!-- Para Üstü Hesaplama (Nakit İçin Opsiyonel POS Aracı) -->
            <div class="pt-3 border-t border-slate-800 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-300">Nakit Para Üstü Hesaplayıcı:</span>
                    <span id="changeDisplay" class="font-mono font-bold text-emerald-400">Para Üstü: ₺0.00</span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" id="givenCashInput" placeholder="Müşterinin Verdiği Tutar (₺)" oninput="calculateCashChange()"
                        class="flex-1 bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 font-mono focus:border-emerald-500 focus:outline-none">
                    <div class="flex gap-1">
                        <button type="button" onclick="setPresetGivenCash(50)" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-mono font-bold cursor-pointer">50₺</button>
                        <button type="button" onclick="setPresetGivenCash(100)" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-mono font-bold cursor-pointer">100₺</button>
                        <button type="button" onclick="setPresetGivenCash(200)" class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-mono font-bold cursor-pointer">200₺</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 bg-slate-900/60 border-t border-slate-800 flex justify-end">
            <button type="button" onclick="closeQuickPaymentModal()" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                İptal / Vazgeç
            </button>
        </div>
    </div>
</div>

<!-- SEPETİ MASAYA AKTAR HIZLI POPUP MODALI -->
<div id="quickSaleTableModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-sky-500/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center">
                    <i class="fi fi-rr-apps text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Sepeti Masaya Aktar</h3>
                    <p class="text-xs text-slate-400">Sepetteki ürünlerin aktarılacağı hedef masaya tıklayınız</p>
                </div>
            </div>
            <button onclick="closeTableTransferModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>
        
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 space-y-6">
            @foreach($halls as $hall)
                <div>
                    <h4 class="text-xs font-black text-indigo-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <i class="fi fi-rr-building text-sm"></i>
                        {{ $hall->name }}
                    </h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($hall->tables as $t)
                            @php $tCheck = $t->activeCheck; @endphp
                            <button type="button" onclick="transferCartToSelectedTable({{ $t->id }}, '{{ e($t->name) }}')"
                                class="p-4 rounded-2xl border text-center transition-all flex flex-col items-center justify-center cursor-pointer {{ $tCheck ? 'bg-indigo-950/40 border-indigo-500/40 text-indigo-200 hover:bg-indigo-900/60' : 'bg-slate-900/80 border-slate-800 text-slate-300 hover:bg-slate-800' }}">
                                <span class="text-sm font-black text-white block">{{ $t->name }}</span>
                                @if($tCheck)
                                    <span class="text-[10px] font-bold text-emerald-400 mt-1 block">Açık (₺{{ number_format($tCheck->total, 2) }})</span>
                                @else
                                    <span class="text-[10px] font-semibold text-slate-500 mt-1 block">Boş Masa</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- 🏷️ HIZLI SATIŞ İSKONTO MODALI -->
<div id="quickDiscountModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col space-y-4">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-emerald-500/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i class="fi fi-rr-tags text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">İskonto / İndirim Uygula</h3>
                    <p class="text-xs text-slate-400">Sepet toplamına oran veya tutar indirimi ekleyin</p>
                </div>
            </div>
            <button onclick="closeQuickDiscountModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 text-xs">
            <div>
                <label class="block font-bold text-slate-300 mb-2">Hızlı İskonto Oranı (%)</label>
                <div class="grid grid-cols-4 gap-2">
                    <button type="button" onclick="applyPresetDiscountModal(5)" class="py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white font-extrabold text-xs transition cursor-pointer">%5</button>
                    <button type="button" onclick="applyPresetDiscountModal(10)" class="py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white font-extrabold text-xs transition cursor-pointer">%10</button>
                    <button type="button" onclick="applyPresetDiscountModal(15)" class="py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white font-extrabold text-xs transition cursor-pointer">%15</button>
                    <button type="button" onclick="applyPresetDiscountModal(20)" class="py-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:bg-emerald-600 text-slate-200 hover:text-white font-extrabold text-xs transition cursor-pointer">%20</button>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-800/80">
                <label class="block font-bold text-slate-300 mb-1.5">Özel Tutar İndirimi (₺)</label>
                <div class="flex gap-2">
                    <input type="number" id="modalDiscountValue" min="0" step="0.5" placeholder="Örn: 25"
                        class="flex-1 bg-slate-900 border border-slate-800 rounded-xl px-3.5 py-2.5 text-white font-mono text-xs focus:border-emerald-500 focus:outline-none">
                    <button type="button" onclick="applyCustomDiscountFromModal()" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs transition cursor-pointer">
                        Uygula
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4 bg-slate-900/60 border-t border-slate-800 flex justify-between items-center">
            <button type="button" onclick="removeDiscount(); closeQuickDiscountModal();" class="px-3.5 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-600/30 text-rose-400 border border-rose-500/30 text-xs font-bold transition cursor-pointer flex items-center gap-1.5">
                <i class="fi fi-rr-trash text-xs"></i>
                <span>İskontoyu Sıfırla</span>
            </button>
            <button type="button" onclick="closeQuickDiscountModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                Kapat
            </button>
        </div>
    </div>
</div>

<!-- 🎁 HIZLI SATIŞ İKRAM MODALI -->
<div id="quickTreatModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col space-y-4">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-amber-500/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                    <i class="fi fi-rr-gift text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Ürün İkram Et</h3>
                    <p class="text-xs text-slate-400">İkram edilecek sepet ürünlerini seçiniz</p>
                </div>
            </div>
            <button onclick="closeQuickTreatModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-4 text-xs">
            <div id="treatModalListContainer" class="space-y-2 max-h-60 overflow-y-auto custom-scrollbar">
                <!-- JS Populates Cart Items Here -->
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="closeQuickTreatModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                    İptal
                </button>
                <button type="button" onclick="applyTreatFromModal()" class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-extrabold text-xs shadow-lg shadow-amber-600/30 transition cursor-pointer">
                    İkramı Uygula
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ✂️ HIZLI SATIŞ BÖL & ÖDE MODALI -->
<div id="quickSplitModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col space-y-4">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-violet-500/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-violet-500/20 text-violet-400 flex items-center justify-center">
                    <i class="fi fi-rr-code-branch text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Sepet Kalemlerini Böl & Öde</h3>
                    <p class="text-xs text-slate-400">Ayrı ödenmesini istediğiniz ürünleri seçiniz</p>
                </div>
            </div>
            <button onclick="closeQuickSplitModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <div class="p-6 space-y-5 text-xs">
            <div id="splitModalListContainer" class="space-y-2 max-h-52 overflow-y-auto custom-scrollbar">
                <!-- JS Populates Selected Items Here -->
            </div>

            <div class="space-y-2 pt-2 border-t border-slate-800">
                <label class="block font-bold text-slate-300 uppercase tracking-wider">Ödeme Yöntemi Seçin</label>
                <div class="grid grid-cols-3 gap-2">
                    <button onclick="submitSplitPaymentModal('nakit')" class="p-3 rounded-xl bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-300 hover:text-white font-bold text-xs transition cursor-pointer">
                        NAKİT
                    </button>
                    <button onclick="submitSplitPaymentModal('kredi_karti')" class="p-3 rounded-xl bg-indigo-600/20 hover:bg-indigo-600 border border-indigo-500/30 text-indigo-300 hover:text-white font-bold text-xs transition cursor-pointer">
                        K. KARTI
                    </button>
                    <button onclick="submitSplitPaymentModal('yemek_karti')" class="p-3 rounded-xl bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-300 hover:text-white font-bold text-xs transition cursor-pointer">
                        YEMEK K.
                    </button>
                </div>
            </div>
        </div>

        <div class="p-4 bg-slate-900/60 border-t border-slate-800 flex justify-end">
            <button type="button" onclick="closeQuickSplitModal()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                Kapat
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let cart = [];
    let activeCategory = 'all';

    document.addEventListener('DOMContentLoaded', () => {
        updateClock();
        setInterval(updateClock, 1000);
    });

    function toggleKitchenSend() {
        const toggle = document.getElementById('sendToKitchenToggle');
        if (toggle) {
            toggle.checked = !toggle.checked;
            updateKitchenBtnState(toggle.checked);
        }
    }

    function updateKitchenBtnState(isON) {
        const pill = document.getElementById('kitchenTogglePill');
        const dot = document.getElementById('kitchenToggleDot');
        const label = document.getElementById('kitchenToggleLabel');
        const toggle = document.getElementById('sendToKitchenToggle');
        
        if (toggle && toggle.checked !== isON) toggle.checked = isON;

        if (label) {
            if (isON) {
                if (pill) pill.className = "w-9 h-5 rounded-full p-0.5 bg-orange-500 flex items-center transition-colors shadow-inner";
                if (dot) dot.className = "w-4 h-4 rounded-full bg-white shadow-md transform translate-x-4 transition-transform duration-200";
                label.innerHTML = "Mutfak<br>Açık";
                label.className = "text-[10px] font-bold text-orange-300 text-center leading-tight";
            } else {
                if (pill) pill.className = "w-9 h-5 rounded-full p-0.5 bg-slate-800 flex items-center transition-colors shadow-inner border border-slate-700";
                if (dot) dot.className = "w-4 h-4 rounded-full bg-slate-400 shadow-md transform translate-x-0 transition-transform duration-200";
                label.innerHTML = "Mutfak<br>Kapalı";
                label.className = "text-[10px] font-bold text-slate-500 text-center leading-tight";
            }
        }
    }

    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('tr-TR');
        const clockEl = document.getElementById('liveClock');
        if(clockEl) clockEl.textContent = timeStr;
    }

    function selectCategory(catId) {
        activeCategory = catId;
        document.querySelectorAll('.cat-btn').forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-lg', 'border-indigo-500', 'active');
            btn.classList.add('bg-slate-900/60', 'text-slate-400', 'border-slate-800/80');
        });

        const activeBtn = document.getElementById(`cat-btn-${catId}`);
        if(activeBtn) {
            activeBtn.classList.remove('bg-slate-900/60', 'text-slate-400', 'border-slate-800/80');
            activeBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-lg', 'border-indigo-500', 'active');
        }

        filterProducts();
    }

    function filterProducts() {
        const query = document.getElementById('searchInput').value.toLowerCase().trim();
        const cards = document.querySelectorAll('.product-card');

        cards.forEach(card => {
            const catMatch = activeCategory === 'all' || card.dataset.categoryId == activeCategory;
            const nameMatch = card.dataset.name.includes(query) || card.dataset.sku.includes(query);

            if(catMatch && nameMatch) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function addToCart(id, name, price, image, isOutOfStock) {
        if (isOutOfStock === 1 || isOutOfStock === '1') {
            showAlert('🚫 ' + name + ' ürününün stoğu tükenmiştir! Mutfakta stok kalmadı.', 'danger');
            return;
        }

        const existing = cart.find(item => item.product_id === id);
        if(existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: id,
                name: name,
                unit_price: price,
                original_price: price,
                quantity: 1,
                image: image,
                is_treat: false,
                is_selected: false
            });
        }
        renderCart();
    }

    function updateQuantity(id, delta) {
        const item = cart.find(item => item.product_id === id);
        if(item) {
            item.quantity += delta;
            if(item.quantity <= 0) {
                removeFromCart(id);
                return;
            }
        }
        renderCart();
    }

    function removeFromCart(id) {
        cart = cart.filter(item => item.product_id !== id);
        renderCart();
    }

    function clearCart() {
        cart = [];
        document.getElementById('discountInput').value = 0;
        renderCart();
    }

    function toggleItemSelect(id) {
        const item = cart.find(i => i.product_id === id);
        if (item) {
            item.is_selected = !item.is_selected;
        }
    }

    function toggleCartItemTreat() {
        const selectedItems = cart.filter(i => i.is_selected);
        if (selectedItems.length === 0) {
            showAlert('Lütfen ikram yapmak için sepetten ürün seçiniz (sol kutucuğu işaretleyin).', 'danger');
            return;
        }

        selectedItems.forEach(item => {
            item.is_treat = !item.is_treat;
            item.unit_price = item.is_treat ? 0 : item.original_price;
        });

        renderCart();
        showAlert('Seçili ürünlerin ikram durumu güncellendi.', 'success');
    }

    function applyPresetDiscount(percent) {
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const discountVal = (subtotal * (percent / 100));
        document.getElementById('discountInput').value = discountVal.toFixed(2);
        updateTotals();
        showAlert(`%${percent} indirim uygulandı (₺${discountVal.toFixed(2)})`, 'success');
    }

    /* ---------------- 🏷️ İSKONTO MODALI ---------------- */
    function openQuickDiscountModal() {
        if (cart.length === 0) {
            showAlert('İskonto uygulamak için sepetinize ürün ekleyiniz.', 'danger');
            return;
        }
        document.getElementById('modalDiscountValue').value = '';
        document.getElementById('quickDiscountModal').classList.remove('hidden');
    }

    function closeQuickDiscountModal() {
        document.getElementById('quickDiscountModal').classList.add('hidden');
    }

    function applyPresetDiscountModal(percent) {
        applyPresetDiscount(percent);
        closeQuickDiscountModal();
    }

    function applyCustomDiscountFromModal() {
        const val = parseFloat(document.getElementById('modalDiscountValue').value) || 0;
        if (val < 0) return;
        document.getElementById('discountInput').value = val.toFixed(2);
        updateTotals();
        closeQuickDiscountModal();
        showAlert(`₺${val.toFixed(2)} özel iskonto uygulandı.`, 'success');
    }

    function removeDiscount() {
        document.getElementById('discountInput').value = 0;
        updateTotals();
        showAlert('Uygulanan iskonto geri alındı.', 'info');
    }

    /* ---------------- 🎁 İKRAM MODALI ---------------- */
    function openQuickTreatModal() {
        if (cart.length === 0) {
            showAlert('İkram yapmak için sepetinizde ürün bulunmalıdır.', 'danger');
            return;
        }

        const container = document.getElementById('treatModalListContainer');
        container.innerHTML = '';

        cart.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-3 rounded-2xl bg-slate-900 border border-slate-800 text-xs';
            div.innerHTML = `
                <div class="flex items-center gap-2">
                    <img src="${item.image}" class="w-8 h-8 rounded-lg object-cover">
                    <div>
                        <span class="font-bold text-white block">${item.name}</span>
                        <span class="text-[10px] text-slate-400">₺${item.original_price.toFixed(2)} x ${item.quantity}</span>
                    </div>
                </div>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-amber-400">
                    <input type="checkbox" data-treat-id="${item.product_id}" ${item.is_treat ? 'checked' : ''} class="w-4 h-4 accent-amber-500 rounded cursor-pointer">
                    <span>İkram</span>
                </label>
            `;
            container.appendChild(div);
        });

        document.getElementById('quickTreatModal').classList.remove('hidden');
    }

    function closeQuickTreatModal() {
        document.getElementById('quickTreatModal').classList.add('hidden');
    }

    function applyTreatFromModal() {
        const checkboxes = document.querySelectorAll('#treatModalListContainer input[data-treat-id]');
        checkboxes.forEach(cb => {
            const prodId = parseInt(cb.getAttribute('data-treat-id'));
            const item = cart.find(i => i.product_id === prodId);
            if (item) {
                item.is_treat = cb.checked;
                item.unit_price = item.is_treat ? 0 : item.original_price;
            }
        });

        renderCart();
        closeQuickTreatModal();
        showAlert('İkram durumları başarıyla güncellendi.', 'success');
    }

    /* ---------------- ✂️ BÖL & ÖDE MODALI ---------------- */
    function openQuickSplitModal() {
        if (cart.length === 0) {
            showAlert('Bölüp ödemek için sepetinizde ürün bulunmalıdır.', 'danger');
            return;
        }

        const container = document.getElementById('splitModalListContainer');
        container.innerHTML = '';

        cart.forEach(item => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-3 rounded-2xl bg-slate-900 border border-slate-800 text-xs';
            div.innerHTML = `
                <div class="flex items-center gap-2">
                    <img src="${item.image}" class="w-8 h-8 rounded-lg object-cover">
                    <div>
                        <span class="font-bold text-white block">${item.name}</span>
                        <span class="text-[10px] text-slate-400">₺${item.unit_price.toFixed(2)} x ${item.quantity}</span>
                    </div>
                </div>
                <label class="flex items-center gap-1.5 cursor-pointer font-bold text-violet-400">
                    <input type="checkbox" data-split-id="${item.product_id}" ${item.is_selected ? 'checked' : ''} class="w-4 h-4 accent-violet-500 rounded cursor-pointer">
                    <span>Ayrı Öde</span>
                </label>
            `;
            container.appendChild(div);
        });

        document.getElementById('quickSplitModal').classList.remove('hidden');
    }

    function closeQuickSplitModal() {
        document.getElementById('quickSplitModal').classList.add('hidden');
    }

    async function submitSplitPaymentModal(paymentMethod) {
        const checkboxes = document.querySelectorAll('#splitModalListContainer input[data-split-id]:checked');
        if (checkboxes.length === 0) {
            showAlert('Lütfen ayrı ödemek istediğiniz en az bir ürünü seçiniz.', 'danger');
            return;
        }

        const selectedIds = Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-split-id')));
        const selectedItems = cart.filter(i => selectedIds.includes(i.product_id));

        closeQuickSplitModal();

        const sendToKitchen = document.getElementById('sendToKitchenToggle')?.checked ? 1 : 0;
        const payload = {
            items: selectedItems.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity
            })),
            payment_method: paymentMethod,
            discount_amount: 0,
            send_to_kitchen: sendToKitchen
        };

        try {
            const response = await fetch("{{ route('quicksale.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                showAlert(`✂️ Seçilen kalemlerin satışı tamamlandı (#${data.check_number} - ₺${data.total})`, 'success');
                cart = cart.filter(i => !selectedIds.includes(i.product_id));
                renderCart();
            } else {
                showAlert('Satış esnasında bir hata oluştu.', 'danger');
            }
        } catch (err) {
            showAlert('Sunucu bağlantı hatası oluştu.', 'danger');
        }
    }

    function renderCart() {
        const itemsList = document.getElementById('cartItemsList');
        const emptyState = document.getElementById('emptyCartState');
        const totalItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);

        document.getElementById('cartCountBadge').textContent = `${totalItemsCount} Kalem`;

        if(cart.length === 0) {
            itemsList.innerHTML = '';
            emptyState.style.display = 'block';
            togglePaymentButtons(false);
            updateTotals();
            return;
        }

        emptyState.style.display = 'none';
        itemsList.innerHTML = '';

        cart.forEach(item => {
            const itemTotal = item.unit_price * item.quantity;
            const el = document.createElement('div');
            el.className = `flex items-center gap-3 p-2.5 rounded-2xl border text-xs ${item.is_treat ? 'bg-amber-950/30 border-amber-500/40' : 'bg-slate-900/80 border-slate-800/80'}`;
            el.innerHTML = `
                <img src="${item.image}" class="w-10 h-10 rounded-xl object-cover border border-slate-800 shrink-0">
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-slate-200 truncate flex items-center gap-1">
                        ${item.name}
                        ${item.is_treat ? '<span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 text-[9px] font-black uppercase">İkram</span>' : ''}
                    </h4>
                    <p class="text-[10px] text-slate-400 font-mono">₺${item.unit_price.toFixed(2)} x ${item.quantity}</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <div class="flex items-center bg-slate-950 border border-slate-800 rounded-xl p-0.5">
                        <button onclick="updateQuantity(${item.product_id}, -1)" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center cursor-pointer">
                            <i class="fi fi-rr-minus text-[10px]"></i>
                        </button>
                        <span class="w-7 text-center font-bold font-mono text-slate-200">${item.quantity}</span>
                        <button onclick="updateQuantity(${item.product_id}, 1)" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center cursor-pointer">
                            <i class="fi fi-rr-plus text-[10px]"></i>
                        </button>
                    </div>
                    <span class="font-bold text-emerald-400 font-mono min-w-[50px] text-right">₺${itemTotal.toFixed(2)}</span>
                    <button onclick="removeFromCart(${item.product_id})" class="text-slate-500 hover:text-rose-400 p-1 transition-colors cursor-pointer">
                        <i class="fi fi-rr-cross-small text-base"></i>
                    </button>
                </div>
            `;
            itemsList.appendChild(el);
        });

        togglePaymentButtons(true);
        updateTotals();
    }

    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const discountInput = document.getElementById('discountInput');
        const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;
        const grandTotal = Math.max(0, subtotal - discount);

        const subDisplay = document.getElementById('subtotalDisplay');
        const grandDisplay = document.getElementById('grandTotalDisplay');
        const payBtnDisplay = document.getElementById('payBtnTotalDisplay');
        const discountRow = document.getElementById('discountRow');
        const discountDisplay = document.getElementById('discountDisplay');

        if (subDisplay) subDisplay.textContent = `₺${subtotal.toFixed(2)}`;
        if (grandDisplay) grandDisplay.textContent = `₺${grandTotal.toFixed(2)}`;
        if (payBtnDisplay) payBtnDisplay.textContent = `₺${grandTotal.toFixed(2)}`;

        if (discountRow && discountDisplay) {
            if (discount > 0) {
                discountRow.classList.remove('hidden');
                discountDisplay.textContent = `-₺${discount.toFixed(2)}`;
            } else {
                discountRow.classList.add('hidden');
            }
        }
    }

    function togglePaymentButtons(enable) {
        const btn = document.getElementById('btnOpenPaymentModal');
        if (btn) btn.disabled = !enable;
    }

    function openQuickPaymentModal() {
        if (cart.length === 0) return;
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const grandTotal = Math.max(0, subtotal - discount);
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

        document.getElementById('modalGrandTotalDisplay').textContent = `₺${grandTotal.toFixed(2)}`;
        document.getElementById('modalCartCountDisplay').textContent = `${totalItems} Kalem`;

        const cashInput = document.getElementById('givenCashInput');
        if (cashInput) cashInput.value = '';
        calculateCashChange();

        document.getElementById('quickSalePaymentModal').classList.remove('hidden');
    }

    function closeQuickPaymentModal() {
        document.getElementById('quickSalePaymentModal').classList.add('hidden');
    }

    function setPresetGivenCash(amount) {
        const input = document.getElementById('givenCashInput');
        if (input) {
            input.value = amount;
            calculateCashChange();
        }
    }

    function calculateCashChange() {
        const subtotal = cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const grandTotal = Math.max(0, subtotal - discount);

        const givenCash = parseFloat(document.getElementById('givenCashInput').value) || 0;
        const changeEl = document.getElementById('changeDisplay');

        if (givenCash > 0) {
            const change = Math.max(0, givenCash - grandTotal);
            changeEl.textContent = `Para Üstü: ₺${change.toFixed(2)}`;
            changeEl.className = change > 0 ? "font-mono font-extrabold text-emerald-400" : "font-mono font-bold text-slate-400";
        } else {
            changeEl.textContent = `Para Üstü: ₺0.00`;
            changeEl.className = "font-mono font-bold text-slate-400";
        }
    }

    async function submitQuickSalePayment(paymentMethod) {
        closeQuickPaymentModal();
        await completeSale(paymentMethod);
    }

    // Seçilen ürünleri böl ve tekil olarak hızlı öde
    async function splitSelectedCartItems() {
        const selectedItems = cart.filter(i => i.is_selected);
        if (selectedItems.length === 0) {
            showAlert('Lütfen sepetten ödemek istediğiniz ürünleri seçiniz (kutucukları işaretleyin).', 'danger');
            return;
        }

        const paymentMethod = prompt('Seçilen kalemler için ödeme yöntemi seçin (nakit / kredi_karti / yemek_karti):', 'nakit');
        if (!paymentMethod || !['nakit', 'kredi_karti', 'yemek_karti'].includes(paymentMethod)) return;

        const sendToKitchen = document.getElementById('sendToKitchenToggle')?.checked ? 1 : 0;
        const payload = {
            items: selectedItems.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity
            })),
            payment_method: paymentMethod,
            discount_amount: 0,
            send_to_kitchen: sendToKitchen
        };

        try {
            const response = await fetch("{{ route('quicksale.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                showAlert(`✂️ Seçilen kalemlerin satışı tamamlandı (#${data.check_number} - ₺${data.total})`, 'success');
                // Ödenen ürünleri sepetten kaldır
                const selectedIds = selectedItems.map(i => i.product_id);
                cart = cart.filter(i => !selectedIds.includes(i.product_id));
                renderCart();
            } else {
                showAlert('Satış esnasında bir hata oluştu.', 'danger');
            }
        } catch (err) {
            showAlert('Sunucu bağlantı hatası oluştu.', 'danger');
        }
    }

    function openTableTransferModal() {
        if (cart.length === 0) {
            showAlert('Masaya aktarmak için sepetinize ürün ekleyiniz.', 'danger');
            return;
        }
        document.getElementById('quickSaleTableModal').classList.remove('hidden');
    }

    function closeTableTransferModal() {
        document.getElementById('quickSaleTableModal').classList.add('hidden');
    }

    async function transferCartToSelectedTable(tableId, tableName) {
        if (cart.length === 0) return;
        const sendToKitchen = document.getElementById('sendToKitchenToggle')?.checked ? 1 : 0;

        const payload = {
            dining_table_id: tableId,
            items: cart.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity
            })),
            send_to_kitchen: sendToKitchen
        };

        try {
            const response = await fetch("{{ route('quicksale.transfer') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (data.success) {
                showAlert(`Sepet ${tableName} masasına başarıyla aktarıldı. Yönlendiriliyorsunuz...`, 'success');
                cart = [];
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 800);
            } else {
                showAlert('Masaya aktarma sırasında hata oluştu.', 'danger');
            }
        } catch (e) {
            showAlert('Sunucu hatası oluştu.', 'danger');
        }
    }

    async function completeSale(paymentMethod) {
        if(cart.length === 0) return;

        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const sendToKitchen = document.getElementById('sendToKitchenToggle')?.checked ? 1 : 0;
        const payload = {
            items: cart.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity
            })),
            payment_method: paymentMethod,
            discount_amount: discount,
            send_to_kitchen: sendToKitchen
        };

        try {
            const response = await fetch("{{ route('quicksale.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if(data.success) {
                showAlert(`⚡ Satış Başarıyla Tamamlandı! Adisyon: #${data.check_number} (₺${data.total})`, 'success');
                clearCart();
            } else {
                showAlert('Satış işlemi sırasında bir hata oluştu.', 'danger');
            }
        } catch (error) {
            showAlert('Sunucuyla iletişim kurulurken bir hata oluştu.', 'danger');
        }
    }

    function showAlert(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        }
    }
</script>
@endsection
