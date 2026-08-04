@extends('layouts.app')

@section('title', 'Garson Operasyon Ekranı')

@section('content')
<div class="min-h-screen bg-[#090b11] text-slate-100">
    <header class="sticky top-0 z-30 border-b border-slate-800/80 bg-[#090b11]/95 backdrop-blur">
        <div class="mx-auto flex max-w-[1600px] items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('dashboard') }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-slate-300 hover:text-white">
                    <i class="fi fi-rr-arrow-left"></i>
                </a>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-black sm:text-xl">Garson</h1>
                    <p class="truncate text-xs text-slate-400">{{ $staff?->name ?? session('active_staff_name', 'Aktif personel') }} · Sipariş yönetimi</p>
                </div>
            </div>
            <a href="{{ route('waiter.index', ['scope' => $scope]) }}" class="flex h-10 items-center gap-2 rounded-xl border border-blue-500/25 bg-blue-500/10 px-3 text-xs font-bold text-blue-300">
                <i class="fi fi-rr-refresh"></i><span class="hidden sm:inline">Yenile</span>
            </a>
        </div>
    </header>

    <main class="mx-auto max-w-[1600px] space-y-4 px-3 py-4 sm:px-5">
        @if(false && session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-300">
                <i class="fi fi-rr-check-circle mr-2"></i>{{ session('status') }}
            </div>
        @endif
        @if(false && $errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                <div class="font-bold"><i class="fi fi-rr-exclamation mr-2"></i>İşlem tamamlanamadı</div>
                <ul class="mt-1 list-inside list-disc text-xs">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/65 p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Açık adisyon</div>
                <div class="mt-1 text-2xl font-black">{{ $stats['open'] }}</div>
            </div>
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-500/80">Hesap isteyen</div>
                <div class="mt-1 text-2xl font-black text-amber-300">{{ $stats['awaiting_payment'] }}</div>
            </div>
            <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-blue-400/80">Benim masalarım</div>
                <div class="mt-1 text-2xl font-black text-blue-300">{{ $stats['mine'] }}</div>
            </div>
            <div class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-violet-400/80">Mutfağa gitmemiş</div>
                <div class="mt-1 text-2xl font-black text-violet-300">{{ $stats['unsent'] }}</div>
            </div>
        </section>

        <div class="grid items-start gap-4 xl:grid-cols-[360px_minmax(0,1fr)]">
            <aside class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/55 xl:sticky xl:top-20">
                <div class="border-b border-slate-800 p-3">
                    <form method="GET" action="{{ route('waiter.index') }}" class="flex gap-2">
                        <input type="hidden" name="scope" value="{{ $scope }}">
                        <div class="relative min-w-0 flex-1">
                            <i class="fi fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                            <input name="search" value="{{ $search }}" placeholder="Masa, garson veya adisyon..." class="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 pl-9 pr-3 text-base text-white outline-none focus:border-blue-500 sm:text-sm">
                        </div>
                        <button class="h-11 rounded-xl bg-blue-600 px-3 text-sm font-bold hover:bg-blue-500">Ara</button>
                    </form>
                    <div class="mt-2 grid grid-cols-2 rounded-xl bg-slate-950 p-1 text-xs font-bold">
                        <a href="{{ route('waiter.index', ['scope' => 'all', 'search' => $search]) }}" class="rounded-lg px-3 py-2 text-center {{ $scope === 'all' ? 'bg-slate-700 text-white' : 'text-slate-500' }}">Tüm adisyonlar</a>
                        <a href="{{ route('waiter.index', ['scope' => 'mine', 'search' => $search]) }}" class="rounded-lg px-3 py-2 text-center {{ $scope === 'mine' ? 'bg-blue-600 text-white' : 'text-slate-500' }}">Masalarım</a>
                    </div>
                </div>
                <div class="max-h-[620px] space-y-2 overflow-y-auto p-2">
                    @forelse($checks as $check)
                        @php($isSelected = $selectedCheck?->id === $check->id)
                        <a href="{{ route('waiter.checks.show', ['check' => $check, 'scope' => $scope, 'search' => $search]) }}" class="block rounded-2xl border p-3 transition {{ $isSelected ? 'border-blue-500 bg-blue-500/10' : 'border-slate-800 bg-slate-950/55 hover:border-slate-600' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-black text-white">{{ $check->diningTable?->name ?? 'Tezgâh' }}</div>
                                    <div class="mt-0.5 truncate text-[11px] text-slate-500">{{ $check->diningTable?->hall?->name ?? 'Salon belirtilmedi' }} · {{ $check->check_number }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-black text-emerald-300">{{ number_format((float) $check->total, 2, ',', '.') }} ₺</div>
                                    <div class="text-[10px] text-slate-500">{{ $check->active_items_count }} kalem</div>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <span class="truncate text-[11px] text-slate-400"><i class="fi fi-rr-user mr-1 text-blue-400"></i>{{ $check->waiter_name ?: 'Garson atanmamış' }}</span>
                                @if($check->status === \App\Enums\CheckStatus::AwaitingPayment)
                                    <span class="shrink-0 rounded-full bg-amber-500/15 px-2 py-1 text-[9px] font-black uppercase text-amber-300">Hesap bekliyor</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-emerald-500/15 px-2 py-1 text-[9px] font-black uppercase text-emerald-300">Açık</span>
                                @endif
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <i class="fi fi-rr-room-service text-3xl text-slate-700"></i>
                            <p class="mt-3 text-sm font-bold text-slate-400">Adisyon bulunamadı</p>
                            <p class="mt-1 text-xs text-slate-600">Filtreyi veya arama metnini değiştirin.</p>
                        </div>
                    @endforelse
                </div>
            </aside>

            @if($selectedCheck)
                <section class="min-w-0 space-y-4">
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/55 p-4 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-2xl font-black">{{ $selectedCheck->diningTable?->name ?? 'Tezgâh' }}</h2>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $selectedCheck->status === \App\Enums\CheckStatus::AwaitingPayment ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300' }}">
                                        {{ $selectedCheck->status->label() }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $selectedCheck->check_number }} · {{ $selectedCheck->guest_count }} kişi · {{ $selectedCheck->waiter_name ?: 'Garson atanmamış' }}</p>
                            </div>
                            <div class="text-left sm:text-right">
                                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Adisyon toplamı</div>
                                <div class="text-3xl font-black text-emerald-300">{{ number_format((float) $selectedCheck->total, 2, ',', '.') }} ₺</div>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                            <form action="{{ route('waiter.checks.notes.update', $selectedCheck) }}" method="POST" class="flex min-w-0 gap-2">
                                @csrf @method('PUT')
                                <input name="customer_notes" value="{{ old('customer_notes', $selectedCheck->customer_notes) }}" maxlength="1000" placeholder="Müşteri notu: doğum günü, alerji, servis isteği..." class="h-12 min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 text-base outline-none focus:border-blue-500 sm:text-sm">
                                <button class="h-12 shrink-0 rounded-xl border border-slate-700 bg-slate-800 px-4 text-xs font-bold hover:bg-slate-700">Notu kaydet</button>
                            </form>
                            <div class="flex gap-2">
                                @if($stats['unsent'] > 0)
                                    <form action="{{ route('waiter.checks.send-kitchen', $selectedCheck) }}" method="POST" class="flex-1">
                                        @csrf
                                        <button class="h-12 w-full rounded-xl bg-violet-600 px-4 text-xs font-black hover:bg-violet-500">
                                            <i class="fi fi-rr-paper-plane mr-1"></i>Mutfağa gönder ({{ $stats['unsent'] }})
                                        </button>
                                    </form>
                                @endif
                                @if($selectedCheck->status === \App\Enums\CheckStatus::Open)
                                    <form action="{{ route('waiter.checks.request-payment', $selectedCheck) }}" method="POST" class="flex-1">
                                        @csrf
                                        <button class="h-12 w-full rounded-xl bg-amber-500 px-4 text-xs font-black text-slate-950 hover:bg-amber-400" onclick="return confirm('Hesap isteği kasaya gönderilsin mi?')">
                                            <i class="fi fi-rr-receipt mr-1"></i>Hesap istendi
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-800 bg-slate-900/55 p-4 sm:p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-black">Mevcut sipariş</h3>
                            <span class="text-xs font-bold text-slate-500">{{ $selectedCheck->items->count() }} kalem</span>
                        </div>
                        <div class="space-y-2">
                            @forelse($selectedCheck->items as $item)
                                <div class="flex items-start justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-950/55 p-3">
                                    <div class="min-w-0">
                                        <div class="font-bold"><span class="mr-2 text-blue-300">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}×</span>{{ $item->product_name }}</div>
                                        @if($item->notes)<div class="mt-1 text-xs text-amber-300"><i class="fi fi-rr-comment mr-1"></i>{{ $item->notes }}</div>@endif
                                        <div class="mt-1 text-[10px] text-slate-600">
                                            {{ $item->added_by_name ?: 'Önceki sipariş' }} ·
                                            {{ $item->sent_to_kitchen_at ? 'Mutfağa gönderildi' : 'Gönderilmedi' }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-sm font-black">{{ number_format((float) $item->total_price, 2, ',', '.') }} ₺</div>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-700 py-8 text-center text-sm text-slate-500">Henüz ürün eklenmedi.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-800 bg-slate-900/55 p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-black">Ürün ekle</h3>
                                <p class="text-xs text-slate-500">Ürüne dokunun, adet ve müşteri isteğini sepette düzenleyin.</p>
                            </div>
                            <div class="relative w-full sm:w-72">
                                <i class="fi fi-rr-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-500"></i>
                                <input id="productSearch" placeholder="Ürün ara..." class="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 pl-9 pr-3 text-base outline-none focus:border-blue-500 sm:text-sm">
                            </div>
                        </div>

                        <div class="mt-3 flex gap-2 overflow-x-auto pb-1" id="categoryFilters">
                            <button type="button" data-category="" class="category-filter shrink-0 rounded-full bg-blue-600 px-3 py-2 text-xs font-bold">Tümü</button>
                            @foreach($categories as $category)
                                <button type="button" data-category="{{ $category->name }}" class="category-filter shrink-0 rounded-full border border-slate-700 bg-slate-950 px-3 py-2 text-xs font-bold text-slate-400">{{ $category->name }}</button>
                            @endforeach
                        </div>

                        <div id="productGrid" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                            @foreach($productOptions as $product)
                                <button type="button" data-product-id="{{ $product['id'] }}" data-name="{{ \Illuminate\Support\Str::lower($product['name']) }}" data-category="{{ $product['category'] }}" class="product-card min-h-24 rounded-2xl border border-slate-800 bg-slate-950/60 p-3 text-left transition hover:border-blue-500 hover:bg-blue-500/10 active:scale-[.98]">
                                    <div class="line-clamp-2 text-sm font-black">{{ $product['name'] }}</div>
                                    <div class="mt-1 text-[10px] text-slate-500">{{ $product['category'] }}</div>
                                    <div class="mt-2 font-black text-emerald-300">{{ number_format((float) $product['price'], 2, ',', '.') }} ₺</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <form id="cartForm" action="{{ route('waiter.checks.items.store', $selectedCheck) }}" method="POST" class="hidden rounded-3xl border border-blue-500/30 bg-slate-900 p-4 shadow-2xl shadow-blue-950/30 sm:p-5">
                        @csrf
                        <input type="hidden" name="scope" value="{{ $scope }}">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-black">Eklenecek ürünler</h3>
                                <p id="cartSummary" class="text-xs text-slate-500"></p>
                            </div>
                            <button id="clearCart" type="button" class="text-xs font-bold text-rose-400">Temizle</button>
                        </div>
                        <div id="cartLines" class="mt-3 space-y-3"></div>
                        <button class="mt-4 h-14 w-full rounded-2xl bg-blue-600 text-sm font-black shadow-lg shadow-blue-950/50 hover:bg-blue-500">
                            <i class="fi fi-rr-add-document mr-2"></i>Siparişe ekle
                        </button>
                    </form>
                </section>
            @else
                <section class="flex min-h-[420px] items-center justify-center rounded-3xl border border-dashed border-slate-800 bg-slate-900/30 p-8 text-center">
                    <div>
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-500/10 text-2xl text-blue-400"><i class="fi fi-rr-room-service"></i></div>
                        <h2 class="mt-4 text-lg font-black">Adisyon seçin</h2>
                        <p class="mt-1 max-w-sm text-sm text-slate-500">Mevcut siparişleri görmek ve müşterinin istediği ürünleri eklemek için listeden bir masa seçin.</p>
                    </div>
                </section>
            @endif
        </div>
    </main>
</div>

@if($selectedCheck)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const products = @json($productOptions);
    const byId = new Map(products.map(product => [Number(product.id), product]));
    const cart = new Map();
    const form = document.getElementById('cartForm');
    const lines = document.getElementById('cartLines');
    const summary = document.getElementById('cartSummary');
    const search = document.getElementById('productSearch');
    let activeCategory = '';

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[character]);

    function renderCart() {
        const entries = [...cart.values()];
        form.classList.toggle('hidden', entries.length === 0);
        lines.innerHTML = entries.map((item, index) => `
            <div class="rounded-2xl border border-slate-700 bg-slate-950/70 p-3" data-cart-id="${item.id}">
                <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-black">${escapeHtml(item.name)}</div>
                        <div class="text-xs text-emerald-300">${Number(item.price).toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺</div>
                    </div>
                    <div class="flex shrink-0 items-center rounded-xl border border-slate-700 bg-slate-900">
                        <button type="button" data-action="minus" class="h-10 w-10 text-lg font-black">−</button>
                        <input name="items[${index}][quantity]" value="${item.quantity}" inputmode="decimal" class="h-10 w-12 border-x border-slate-700 bg-transparent text-center text-base font-black outline-none">
                        <button type="button" data-action="plus" class="h-10 w-10 text-lg font-black">+</button>
                    </div>
                </div>
                <div class="mt-2 flex gap-2">
                    <input name="items[${index}][notes]" value="${escapeHtml(item.notes)}" maxlength="255" placeholder="Ürün notu: soğansız, az pişmiş..." class="h-11 min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-900 px-3 text-base outline-none focus:border-amber-500 sm:text-sm">
                    <button type="button" data-action="remove" class="h-11 w-11 shrink-0 rounded-xl bg-rose-500/10 text-rose-400"><i class="fi fi-rr-trash"></i></button>
                </div>
            </div>
        `).join('');
        const quantity = entries.reduce((total, item) => total + Number(item.quantity), 0);
        const total = entries.reduce((sum, item) => sum + Number(item.quantity) * Number(item.price), 0);
        summary.textContent = `${quantity} ürün · ${total.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`;
        if (entries.length) form.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function filterProducts() {
        const term = search.value.trim().toLocaleLowerCase('tr-TR');
        document.querySelectorAll('.product-card').forEach(card => {
            const categoryMatches = !activeCategory || card.dataset.category === activeCategory;
            const nameMatches = !term || card.dataset.name.toLocaleLowerCase('tr-TR').includes(term);
            card.classList.toggle('hidden', !(categoryMatches && nameMatches));
        });
    }

    document.getElementById('productGrid').addEventListener('click', event => {
        const card = event.target.closest('.product-card');
        if (!card) return;
        const product = byId.get(Number(card.dataset.productId));
        if (!product) return;
        const existing = cart.get(Number(product.id));
        cart.set(Number(product.id), existing ? {...existing, quantity: Number(existing.quantity) + 1} : {...product, quantity: 1, notes: ''});
        renderCart();
    });

    lines.addEventListener('click', event => {
        const action = event.target.closest('[data-action]');
        const row = event.target.closest('[data-cart-id]');
        if (!action || !row) return;
        const id = Number(row.dataset.cartId);
        const item = cart.get(id);
        if (!item) return;
        if (action.dataset.action === 'plus') item.quantity = Number(item.quantity) + 1;
        if (action.dataset.action === 'minus') item.quantity = Math.max(1, Number(item.quantity) - 1);
        if (action.dataset.action === 'remove') cart.delete(id);
        renderCart();
    });

    lines.addEventListener('input', event => {
        const row = event.target.closest('[data-cart-id]');
        if (!row) return;
        const item = cart.get(Number(row.dataset.cartId));
        if (!item) return;
        if (event.target.name.endsWith('[quantity]')) item.quantity = Math.max(0.01, Number(event.target.value) || 1);
        if (event.target.name.endsWith('[notes]')) item.notes = event.target.value;
        const entries = [...cart.values()];
        const total = entries.reduce((sum, entry) => sum + Number(entry.quantity) * Number(entry.price), 0);
        summary.textContent = `${entries.reduce((sum, entry) => sum + Number(entry.quantity), 0)} ürün · ${total.toLocaleString('tr-TR', {minimumFractionDigits: 2})} ₺`;
    });

    document.getElementById('clearCart').addEventListener('click', () => {
        cart.clear();
        renderCart();
    });
    search.addEventListener('input', filterProducts);
    document.getElementById('categoryFilters').addEventListener('click', event => {
        const button = event.target.closest('.category-filter');
        if (!button) return;
        activeCategory = button.dataset.category;
        document.querySelectorAll('.category-filter').forEach(item => {
            const active = item === button;
            item.classList.toggle('bg-blue-600', active);
            item.classList.toggle('text-white', active);
            item.classList.toggle('border-slate-700', !active);
            item.classList.toggle('bg-slate-950', !active);
            item.classList.toggle('text-slate-400', !active);
        });
        filterProducts();
    });
});
</script>
@endif
@endsection
