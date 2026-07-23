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
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(99, 102, 241, 0.6);
    }
    .product-card {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .product-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -10px rgba(99, 102, 241, 0.3);
    }
    .product-card:active {
        transform: scale(0.97);
    }
    .pay-btn {
        transition: all 0.2s ease;
    }
    .pay-btn:hover {
        transform: translateY(-2px);
    }
    .pay-btn:active {
        transform: translateY(0);
    }
</style>
@endsection

@section('content')
<div class="flex flex-col h-screen bg-[#0b0d14] text-slate-100 overflow-hidden font-sans">
    
    <!-- Top Header -->
    <header class="h-16 bg-[#121624]/90 border-b border-slate-800/80 px-4 sm:px-6 flex items-center justify-between z-20 shrink-0 backdrop-blur-md">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white transition-all border border-slate-700/50">
                <i class="fi fi-rr-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-white flex items-center gap-2">
                    <span class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <i class="fi fi-rr-bolt"></i>
                    </span>
                    Hızlı Satış (Express POS)
                </h1>
                <p class="text-xs text-slate-400">Tezgah Üstü Anlık Satış Portalı</p>
            </div>
        </div>

        <!-- System Stats / Time & User -->
        <div class="flex items-center gap-4">
            <div class="hidden md:flex items-center gap-3 px-3 py-1.5 rounded-xl bg-slate-900/60 border border-slate-800 text-xs text-slate-300">
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span>Kasiyer: <strong class="text-indigo-300">{{ auth()->user()->name ?? 'Kullanıcı' }}</strong></span>
            </div>
            
            <div id="liveClock" class="hidden sm:block text-sm font-semibold text-slate-400 bg-slate-900/80 px-3 py-1.5 rounded-xl border border-slate-800 font-mono">
                00:00:00
            </div>

            <button onclick="clearCart()" class="px-3 py-2 text-xs font-semibold text-rose-400 hover:text-white bg-rose-500/10 hover:bg-rose-600 rounded-xl border border-rose-500/20 transition-all flex items-center gap-1.5">
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
                        @endphp
                        <div class="product-card group relative bg-slate-900/60 hover:bg-slate-800/80 border border-slate-800/80 hover:border-indigo-500/50 rounded-2xl p-3 flex flex-col justify-between cursor-pointer select-none overflow-hidden"
                            data-product-id="{{ $product->id }}"
                            data-product-name="{{ e($product->name) }}"
                            data-product-price="{{ $effectivePrice }}"
                            data-product-image="{{ e($image) }}"
                            data-category-id="{{ $product->category_id }}"
                            data-name="{{ mb_strtolower($product->name) }}"
                            data-sku="{{ mb_strtolower($product->sku ?? '') }}">
                            
                            <!-- Product Image / Badge -->
                            <div class="relative w-full aspect-[4/3] rounded-xl overflow-hidden mb-2 bg-slate-950">
                                <img src="{{ $image }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent opacity-60"></div>
                                @if($hasDiscount)
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
        <div class="w-full md:w-[380px] lg:w-[420px] bg-[#111523] border-t md:border-t-0 md:border-l border-slate-800/80 flex flex-col shrink-0">
            
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
                    <p class="text-[11px] text-slate-500 mt-1">Sol taraftaki katalogdan ürün seçebilirsiniz</p>
                </div>
                <div id="cartItemsList" class="flex flex-col gap-2.5"></div>
            </div>

            <!-- Cart Summary & Payment Panel -->
            <div class="p-4 bg-slate-900/90 border-t border-slate-800/80 flex flex-col gap-3">
                
                <!-- Calculation Breakdown -->
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Ara Toplam</span>
                        <span id="subtotalDisplay" class="font-mono text-slate-200 font-semibold">₺0.00</span>
                    </div>

                    <!-- Optional Discount Input -->
                    <div class="flex items-center justify-between text-slate-400">
                        <span>İndirim (₺)</span>
                        <input type="number" id="discountInput" min="0" step="0.5" value="0" oninput="updateTotals()"
                            class="w-20 px-2 py-1 bg-slate-950 border border-slate-800 rounded-lg text-right font-mono text-xs text-rose-400 focus:outline-none focus:border-rose-500">
                    </div>

                    <div class="flex justify-between text-base font-bold text-white pt-2 border-t border-slate-800">
                        <span>Ödenecek Tutar</span>
                        <span id="grandTotalDisplay" class="font-mono text-emerald-400 text-lg">₺0.00</span>
                    </div>
                </div>

                <!-- Fast Payment Options Buttons -->
                <div class="grid grid-cols-3 gap-2 pt-1">
                    <button onclick="completeSale('nakit')" id="btnNakit" disabled
                        class="pay-btn flex flex-col items-center justify-center p-3 rounded-2xl bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-300 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-all">
                        <i class="fi fi-rr-money-bill-wave text-lg mb-1"></i>
                        <span class="text-[11px] font-bold">NAKİT</span>
                    </button>

                    <button onclick="completeSale('kredi_karti')" id="btnKrediKarti" disabled
                        class="pay-btn flex flex-col items-center justify-center p-3 rounded-2xl bg-indigo-600/20 hover:bg-indigo-600 border border-indigo-500/30 text-indigo-300 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-all">
                        <i class="fi fi-rr-credit-card text-lg mb-1"></i>
                        <span class="text-[11px] font-bold">K. KARTI</span>
                    </button>

                    <button onclick="completeSale('yemek_karti')" id="btnYemekKarti" disabled
                        class="pay-btn flex flex-col items-center justify-center p-3 rounded-2xl bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-300 hover:text-white disabled:opacity-40 disabled:pointer-events-none transition-all">
                        <i class="fi fi-rr-ticket text-lg mb-1"></i>
                        <span class="text-[11px] font-bold">YEMEK K.</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Receipt Simulation Modal -->
<div id="receiptModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 w-full max-w-sm shadow-2xl text-center">
        <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto mb-3 border border-emerald-500/30 text-xl">
            <i class="fi fi-rr-check"></i>
        </div>
        <h3 class="text-base font-bold text-white">Satış Tamamlandı!</h3>
        <p id="receiptCheckNo" class="text-xs text-slate-400 font-mono mt-1">#QCK-000000</p>

        <div class="my-4 py-3 px-4 bg-slate-950 rounded-2xl border border-slate-800 text-left space-y-1 text-xs">
            <div class="flex justify-between text-slate-400">
                <span>Ödeme Yöntemi:</span>
                <span id="receiptPaymentMethod" class="font-bold text-slate-200 uppercase">NAKİT</span>
            </div>
            <div class="flex justify-between text-slate-400">
                <span>Toplam Tutar:</span>
                <span id="receiptTotalAmount" class="font-bold text-emerald-400 font-mono">₺0.00</span>
            </div>
        </div>

        <button onclick="closeReceiptModal()" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all">
            Yeni Satışa Geç
        </button>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let cart = [];
    let activeCategory = 'all';

    // Live Clock
    function updateClock() {
        const now = new Date();
        document.getElementById('liveClock').textContent = now.toLocaleTimeString('tr-TR');
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Event delegation on Product Grid
    document.addEventListener('DOMContentLoaded', function() {
        const grid = document.getElementById('productGrid');
        if (grid) {
            grid.addEventListener('click', function(e) {
                const card = e.target.closest('.product-card');
                if (!card) return;

                const id = parseInt(card.dataset.productId);
                const name = card.dataset.productName;
                const price = parseFloat(card.dataset.productPrice);
                const image = card.dataset.productImage;

                if (id && name && !isNaN(price)) {
                    addToCart(id, name, price, image);
                }
            });
        }
    });

    // Category Filter
    function selectCategory(catId) {
        activeCategory = catId;
        document.querySelectorAll('.cat-btn').forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-lg', 'border-indigo-500');
            btn.classList.add('text-slate-400', 'bg-slate-900/60', 'border-slate-800/80');
        });

        const activeBtn = document.getElementById(`cat-btn-${catId}`);
        if(activeBtn) {
            activeBtn.classList.remove('text-slate-400', 'bg-slate-900/60', 'border-slate-800/80');
            activeBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-lg', 'border-indigo-500');
        }

        filterProducts();
    }

    // Live Search & Category Filtering
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

    // Add Product to Cart
    function addToCart(id, name, price, image) {
        const existing = cart.find(item => item.product_id === id);
        if(existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                product_id: id,
                name: name,
                unit_price: price,
                quantity: 1,
                image: image
            });
        }
        renderCart();
    }

    // Change Cart Item Quantity
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

    // Remove Item from Cart
    function removeFromCart(id) {
        cart = cart.filter(item => item.product_id !== id);
        renderCart();
    }

    // Clear Cart
    function clearCart() {
        cart = [];
        document.getElementById('discountInput').value = 0;
        renderCart();
    }

    // Render Cart HTML State
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
            el.className = 'flex items-center gap-3 p-2.5 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-xs';
            el.innerHTML = `
                <img src="${item.image}" class="w-10 h-10 rounded-xl object-cover border border-slate-800 shrink-0">
                <div class="flex-1 min-w-0">
                    <h4 class="font-bold text-slate-200 truncate">${item.name}</h4>
                    <p class="text-[10px] text-slate-400 font-mono">₺${item.unit_price.toFixed(2)} x ${item.quantity}</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    <div class="flex items-center bg-slate-950 border border-slate-800 rounded-xl p-0.5">
                        <button onclick="updateQuantity(${item.product_id}, -1)" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center">
                            <i class="fi fi-rr-minus text-[10px]"></i>
                        </button>
                        <span class="w-7 text-center font-bold font-mono text-slate-200">${item.quantity}</span>
                        <button onclick="updateQuantity(${item.product_id}, 1)" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center">
                            <i class="fi fi-rr-plus text-[10px]"></i>
                        </button>
                    </div>
                    <span class="font-bold text-emerald-400 font-mono min-w-[50px] text-right">₺${itemTotal.toFixed(2)}</span>
                    <button onclick="removeFromCart(${item.product_id})" class="text-slate-500 hover:text-rose-400 p-1 transition-colors">
                        <i class="fi fi-rr-cross-small text-base"></i>
                    </button>
                </div>
            `;
            itemsList.appendChild(el);
        });

        togglePaymentButtons(true);
        updateTotals();
    }

    // Calculate Subtotal and Grand Total
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

    // Complete Sale via AJAX Request
    async function completeSale(paymentMethod) {
        if(cart.length === 0) return;

        const discount = parseFloat(document.getElementById('discountInput').value) || 0;
        const payload = {
            items: cart.map(i => ({
                product_id: i.product_id,
                quantity: i.quantity
            })),
            payment_method: paymentMethod,
            discount_amount: discount
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

            if(response.ok && data.success) {
                // Show modal receipt summary
                document.getElementById('receiptCheckNo').textContent = `#${data.check_number}`;
                document.getElementById('receiptPaymentMethod').textContent = paymentMethod.replace('_', ' ');
                document.getElementById('receiptTotalAmount').textContent = `₺${data.total}`;
                document.getElementById('receiptModal').classList.remove('hidden');

                // Clear cart state
                clearCart();
            } else {
                showAlert(data.message || 'Satış gerçekleştirilirken bir hata oluştu.', 'error');
            }
        } catch (error) {
            console.error('Sale error:', error);
            showAlert('Sunucu ile iletişim kurulamadı.', 'error');
        }
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
    }

    // Alert toast helper
    function showAlert(msg, type = 'success') {
        const container = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        const bg = type === 'success' ? 'bg-emerald-600/90' : 'bg-rose-600/90';
        alert.className = `${bg} text-white px-4 py-3 rounded-2xl shadow-xl backdrop-blur-md text-xs font-semibold flex items-center gap-2 transition-all duration-300 translate-y-2 opacity-0`;
        alert.innerHTML = `<i class="fi fi-rr-${type === 'success' ? 'check-circle' : 'exclamation'} text-base"></i> ${msg}`;
        container.appendChild(alert);

        setTimeout(() => {
            alert.classList.remove('translate-y-2', 'opacity-0');
        }, 10);

        setTimeout(() => {
            alert.classList.add('opacity-0');
            setTimeout(() => alert.remove(), 300);
        }, 3000);
    }
</script>
@endsection
