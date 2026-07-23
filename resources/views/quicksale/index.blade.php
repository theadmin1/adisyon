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
<div class="flex flex-col min-h-screen bg-[#07090e] text-slate-100 font-sans">
    
    <!-- Top Header Bar -->
    <header class="h-16 bg-[#0f131f]/95 border-b border-slate-800/80 px-4 sm:px-8 flex items-center justify-between z-30 shrink-0 backdrop-blur-md">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-all border border-slate-700/50">
                <i class="fi fi-rr-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-base sm:text-lg font-extrabold tracking-tight text-white flex items-center gap-2">
                    <span class="p-1 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <i class="fi fi-rr-bolt"></i>
                    </span>
                    Hızlı Satış (Express POS)
                </h1>
                <p class="text-[11px] text-slate-400 hidden sm:block">Tezgahüstü Hızlı Satış, Sepet Bölme, İkram ve Masaya Aktarma Portalı</p>
            </div>
        </div>

        <!-- Cashier & Quick Action Tools -->
        <div class="flex items-center gap-4">
            <div class="hidden md:flex items-center gap-2 text-xs font-semibold text-slate-400 bg-slate-900/80 px-3.5 py-1.5 rounded-xl border border-slate-800">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Şube: <strong class="text-slate-200">Ana Şube</strong></span>
                <span class="text-slate-600">|</span>
                <span>Kasiyer: <strong class="text-indigo-300">{{ auth()->user()->name ?? 'Kullanıcı' }}</strong></span>
            </div>
            
            <div id="liveClock" class="hidden sm:block text-sm font-semibold text-slate-400 bg-slate-900/80 px-3 py-1.5 rounded-xl border border-slate-800 font-mono">
                00:00:00
            </div>

            <button onclick="clearCart()" class="px-3 py-2 text-xs font-semibold text-rose-400 hover:text-white bg-rose-500/10 hover:bg-rose-600 rounded-xl border border-rose-500/20 transition-all flex items-center gap-1.5 cursor-pointer">
                <i class="fi fi-rr-trash"></i>
                <span class="hidden sm:inline">Sepeti Sıfırla</span>
            </button>
        </div>
    </header>

    <!-- Status Alert Container -->
    <div id="alertContainer" class="fixed top-20 right-6 z-50 flex flex-col gap-2 max-w-sm"></div>

    <!-- Main Content Area: Left Catalog & Right Cart -->
    <div class="flex-1 flex flex-col md:flex-row overflow-hidden relative">
        
        <!-- LEFT: Product Search, Category Filter & Products Grid -->
        <div class="flex-1 flex flex-col p-4 sm:p-6 overflow-hidden border-r border-slate-800/60 bg-[#0d101a]/50">
            
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
                                <img src="{{ $image }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
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

        <!-- RIGHT: Shopping Cart & Quick Checkout Panel -->
        <div class="w-full md:w-[380px] lg:w-[440px] bg-[#111523] border-t md:border-t-0 md:border-l border-slate-800/80 flex flex-col shrink-0">
            
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

            <!-- SEPET EYLEM VE MASAYA AKTARMA ARAÇ ÇUBUĞU (MASALAR KISMI GİBİ) -->
            <div class="px-4 py-2 bg-[#0c0e17] border-b border-slate-800/80 flex items-center justify-between gap-1 overflow-x-auto custom-scrollbar">
                <button type="button" onclick="splitSelectedCartItems()" title="Seçilen ürünleri böl ve ayrı öde" class="px-3 py-1.5 rounded-xl bg-violet-600/20 hover:bg-violet-600 border border-violet-500/30 text-violet-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fi fi-rr-scissors text-xs"></i>
                    <span>Böl & Öde</span>
                </button>

                <button type="button" onclick="toggleCartItemTreat()" title="Seçilen ürünleri ikram yap" class="px-3 py-1.5 rounded-xl bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fi fi-rr-gift text-xs"></i>
                    <span>İkram Yap</span>
                </button>

                <button type="button" onclick="openTableTransferModal()" title="Hızlı Satış sepetini masaya aktar" class="px-3 py-1.5 rounded-xl bg-sky-600/20 hover:bg-sky-600 border border-sky-500/30 text-sky-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                    <i class="fi fi-rr-apps text-xs"></i>
                    <span>🪑 Masaya Aktar</span>
                </button>
            </div>

            <!-- Cart Items List Container -->
            <div id="cartContainer" class="flex-1 overflow-y-auto custom-scrollbar p-4 flex flex-col gap-2.5">
                <div id="emptyCartState" class="my-auto py-12 text-center text-slate-500">
                    <div class="w-16 h-16 rounded-3xl bg-slate-900/60 border border-slate-800/80 flex items-center justify-center mx-auto mb-3 text-slate-600">
                        <i class="fi fi-rr-shopping-bag text-2xl"></i>
                    </div>
                    <p class="text-xs font-semibold text-slate-400">Sepetiniz boş</p>
                    <p class="text-[11px] text-slate-500 mt-1">Sol taraftaki katalogdan ürün seçebilirsiniz</p>
                </div>
                <div id="cartItemsList" class="flex flex-col gap-2.5"></div>
            </div>

            <!-- Cart Summary & Payment Panel -->
            <div class="p-4 bg-slate-900/90 border-t border-slate-800/80 flex flex-col gap-3">
                
                <!-- HIZLI İSKONTO / İNDİRİM DÜĞMELERİ -->
                <div class="space-y-1 text-xs">
                    <div class="flex items-center justify-between text-slate-400 mb-1">
                        <span class="font-bold">Hızlı İskonto Uygula:</span>
                        <div class="flex gap-1">
                            <button type="button" onclick="applyPresetDiscount(5)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white font-bold text-[10px] transition cursor-pointer">%5</button>
                            <button type="button" onclick="applyPresetDiscount(10)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white font-bold text-[10px] transition cursor-pointer">%10</button>
                            <button type="button" onclick="applyPresetDiscount(15)" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white font-bold text-[10px] transition cursor-pointer">%15</button>
                        </div>
                    </div>

                    <div class="flex justify-between text-slate-400">
                        <span>Ara Toplam</span>
                        <span id="subtotalDisplay" class="font-mono text-slate-200 font-semibold">₺0.00</span>
                    </div>

                    <!-- Optional Discount Input -->
                    <div class="flex items-center justify-between text-slate-400">
                        <span>İndirim (₺)</span>
                        <input type="number" id="discountInput" min="0" step="0.5" value="0" oninput="updateTotals()"
                            class="w-24 px-2 py-1 bg-slate-950 border border-slate-800 rounded-lg text-right font-mono text-xs text-rose-400 focus:outline-none focus:border-rose-500">
                    </div>

                    <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-slate-800">
                        <span>Ödenecek Tutar</span>
                        <span id="grandTotalDisplay" class="font-mono text-emerald-400 text-lg">₺0.00</span>
                    </div>
                </div>

                <!-- Mutfak'a Gönder Toggle -->
                <div class="flex items-center justify-between p-2.5 rounded-2xl bg-slate-950/80 border border-slate-800 text-xs my-1">
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 rounded-lg bg-orange-500/10 text-orange-400 border border-orange-500/20">
                            <i class="fi fi-rr-restaurant text-sm"></i>
                        </span>
                        <div>
                            <span class="font-bold text-slate-200 block text-xs">Mutfağa Gönder (KDS)</span>
                            <span class="text-[10px] text-slate-500 block">Siparişi mutfak ekranına düşürür</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="sendToKitchenToggle" checked class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-orange-500"></div>
                    </label>
                </div>

                <!-- Fast Payment Options Buttons -->
                <div class="grid grid-cols-3 gap-2 pt-1">
                    <button onclick="completeSale('nakit')" id="btnNakit" disabled
                        class="pay-btn flex flex-col items-center justify-center p-3 rounded-2xl bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-300 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-all cursor-pointer">
                        <i class="fi fi-rr-money-bill-wave text-lg mb-1"></i>
                        <span class="text-[11px] font-bold">NAKİT</span>
                    </button>

                    <button onclick="completeSale('kredi_karti')" id="btnKrediKarti" disabled
                        class="pay-btn flex flex-col items-center justify-center p-3 rounded-2xl bg-indigo-600/20 hover:bg-indigo-600 border border-indigo-500/30 text-indigo-300 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-all cursor-pointer">
                        <i class="fi fi-rr-credit-card text-lg mb-1"></i>
                        <span class="text-[11px] font-bold">K. KARTI</span>
                    </button>

                    <button onclick="completeSale('yemek_karti')" id="btnYemekKarti" disabled
                        class="pay-btn flex flex-col items-center justify-center p-3 rounded-2xl bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-300 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-all cursor-pointer">
                        <i class="fi fi-rr-ticket text-lg mb-1"></i>
                        <span class="text-[11px] font-bold">YEMEK K.</span>
                    </button>
                </div>
            </div>

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
@endsection

@section('scripts')
<script>
    let cart = [];
    let activeCategory = 'all';

    document.addEventListener('DOMContentLoaded', () => {
        updateClock();
        setInterval(updateClock, 1000);
    });

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
                <input type="checkbox" ${item.is_selected ? 'checked' : ''} onchange="toggleItemSelect(${item.product_id})" class="accent-violet-500 rounded cursor-pointer w-4 h-4">
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
        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const grandTotal = Math.max(0, subtotal - discount);

        document.getElementById('subtotalDisplay').textContent = `₺${subtotal.toFixed(2)}`;
        document.getElementById('grandTotalDisplay').textContent = `₺${grandTotal.toFixed(2)}`;
    }

    function togglePaymentButtons(enable) {
        document.getElementById('btnNakit').disabled = !enable;
        document.getElementById('btnKrediKarti').disabled = !enable;
        document.getElementById('btnYemekKarti').disabled = !enable;
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
        const container = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        const bgClass = type === 'success' ? 'bg-emerald-900/90 border-emerald-500 text-emerald-100' : 'bg-rose-900/90 border-rose-500 text-rose-100';

        alert.className = `p-4 rounded-2xl border shadow-xl backdrop-blur-md text-xs font-bold transition-all transform translate-y-0 ${bgClass}`;
        alert.textContent = message;

        container.appendChild(alert);
        setTimeout(() => {
            alert.remove();
        }, 4000);
    }
</script>
@endsection
