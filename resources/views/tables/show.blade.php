@extends('layouts.app')

@section('title', $table->name . ' - Adisyon POS Detay')

@section('styles')
<style>
    /* Custom scrollbars & smooth POS layout */
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Dark POS Theme Custom Scrollbars */
    ::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }
    ::-webkit-scrollbar-track {
        background: #0d0f18;
    }
    ::-webkit-scrollbar-thumb {
        background: #252c48;
        border-radius: 9999px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #4f46e5;
    }
</style>
@endsection

@section('content')
<div class="flex flex-1 w-full h-screen bg-[#0b0c12] text-slate-100 font-sans antialiased overflow-hidden">

    <!-- 1. FAR LEFT SIDEBAR (POS ACTIONS) -->
    <div class="w-24 shrink-0 bg-[#121522] border-r border-slate-800/80 flex flex-col items-center py-4 px-2 gap-3 z-30 shadow-2xl">
        
        <button id="btnActionYeni" type="button"
            class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-3 rounded-2xl bg-slate-800/40 hover:bg-indigo-600/30 border border-slate-700/50 group">
            <i class="fi fi-rr-plus text-xl text-indigo-400 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] font-bold">Yeni</span>
        </button>

        <button id="btnActionIkram" type="button"
            class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-3 rounded-2xl bg-slate-800/40 hover:bg-amber-600/30 border border-slate-700/50 group">
            <i class="fi fi-rr-gift text-xl text-amber-400 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] font-bold">İkram</span>
        </button>

        <button id="btnActionIade" type="button"
            class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-3 rounded-2xl bg-slate-800/40 hover:bg-rose-600/30 border border-slate-700/50 group">
            <i class="fi fi-rr-refresh text-xl text-rose-400 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] font-bold">İade</span>
        </button>

        <button id="btnActionBol" type="button"
            class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-3 rounded-2xl bg-slate-800/40 hover:bg-violet-600/30 border border-slate-700/50 group">
            <i class="fi fi-rr-code-branch text-xl text-violet-400 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] font-bold">Böl</span>
        </button>

        <button id="btnActionTasi" type="button"
            class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-3 rounded-2xl bg-slate-800/40 hover:bg-sky-600/30 border border-slate-700/50 group">
            <i class="fi fi-rr-shuffle text-xl text-sky-400 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] font-bold">Taşı</span>
        </button>

        <button id="btnActionIskonto" type="button"
            class="flex flex-col items-center justify-center gap-1 text-slate-300 hover:text-white transition-all w-full py-3 rounded-2xl bg-slate-800/40 hover:bg-emerald-600/30 border border-slate-700/50 group">
            <i class="fi fi-rr-tags text-xl text-emerald-400 group-hover:scale-110 transition-transform"></i>
            <span class="text-[10px] font-bold">İskonto</span>
        </button>

        <a href="{{ route('tables.index') }}"
            class="flex flex-col items-center justify-center gap-1 text-slate-400 hover:text-rose-400 transition-all mt-auto w-full py-3 rounded-2xl hover:bg-rose-500/10 border border-transparent group">
            <i class="fi fi-rr-cross text-xl group-hover:rotate-90 transition-transform"></i>
            <span class="text-[10px] font-bold">Çıkış</span>
        </a>
    </div>

    <!-- 2. LEFT PANEL: ADİSYON / CHECK DETAILS -->
    <div id="adisyonPanel" class="w-96 shrink-0 bg-[#141724] border-r border-slate-800/80 flex flex-col z-20 relative transition-opacity duration-200">
        <!-- Header -->
        <div class="p-5 border-b border-slate-800/80 flex items-center justify-between bg-[#191d2d]/60">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white font-extrabold flex items-center justify-center text-lg shadow-lg shadow-indigo-600/30">
                    {{ preg_replace('/[^0-9]/', '', $table->name) ?: 'T' }}
                </div>
                <div>
                    <h2 class="text-lg font-black text-white leading-tight">{{ $table->name }}</h2>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $table->hall?->name ?: 'Salonsuz' }}</span>
                </div>
            </div>
            <div class="px-3 py-1.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold flex items-center gap-1.5">
                <i class="fi fi-rr-user text-xs"></i>
                <span>{{ session('active_staff_name') ?? $activeCheck?->waiter?->name ?? 'Garson' }}</span>
            </div>
        </div>

        @if ($siblingChecks->isNotEmpty())
            <div class="px-4 py-2.5 bg-indigo-950/40 border-b border-indigo-500/20">
                <p class="text-[10px] font-bold text-indigo-300 uppercase tracking-wider mb-1.5">
                    Bu masada {{ $siblingChecks->count() + 1 }} açık adisyon var:
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <span class="px-2.5 py-1 rounded-lg bg-indigo-600 text-white text-[11px] font-bold">
                        {{ $activeCheck->check_number }}
                    </span>
                    @foreach ($siblingChecks as $siblingCheck)
                        <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-[11px] font-bold border border-slate-700">
                            {{ $siblingCheck->check_number }} · ₺{{ number_format($siblingCheck->total, 2) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Check Items List -->
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @if (!$activeCheck)
                <div class="h-full flex flex-col items-center justify-center text-center p-6 space-y-4">
                    <div class="w-16 h-16 rounded-3xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500">
                        <i class="fi fi-rr-receipt text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Masada Açık Adisyon Yok</h3>
                        <p class="text-xs text-slate-400 mt-1">Sipariş eklemek için lütfen yeni bir adisyon açın.</p>
                    </div>

                    <form method="POST" action="{{ route('checks.store') }}" class="w-full ajax-form">
                        @csrf
                        <input type="hidden" name="dining_table_id" value="{{ $table->id }}">
                        <input type="hidden" name="redirect_to_table" value="1">
                        <div class="flex gap-2">
                            <input type="number" min="1" name="guest_count"
                                value="{{ max(1, $table->occupant_count ?: 1) }}"
                                class="w-20 rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-center text-xs font-bold text-white outline-none focus:border-indigo-500" placeholder="Kişi">
                            <button type="submit" class="flex-1 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 font-extrabold text-xs text-white shadow-lg shadow-indigo-600/30 transition">
                                ADİSYON AÇ
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="divide-y divide-slate-800/60">
                    @forelse ($activeCheck->items as $item)
                        <div class="py-3.5 px-2 flex items-center justify-between group hover:bg-slate-900/40 rounded-xl transition">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md bg-indigo-500/20 text-indigo-400 text-xs font-black">
                                        {{ number_format($item->quantity, 0) }}x
                                    </span>
                                    <span class="font-bold text-sm text-slate-100 {{ $item->is_cancelled ? 'line-through text-slate-500' : '' }}">
                                        {{ $item->product_name }}
                                    </span>
                                    @if($item->is_complimentary)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 text-[9px] font-black uppercase">İkram</span>
                                    @endif
                                </div>
                                @if ($item->notes)
                                    <p class="text-[11px] text-slate-400 mt-0.5 pl-7">{{ $item->notes }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="font-black text-sm text-white">₺{{ number_format($item->total_price, 2) }}</span>
                                <form method="POST" action="{{ route('checks.items.destroy', [$activeCheck, $item]) }}" class="ajax-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-7 h-7 rounded-lg text-slate-500 opacity-0 group-hover:opacity-100 hover:bg-rose-500/20 hover:text-rose-400 transition flex items-center justify-center">
                                        <i class="fi fi-rr-trash text-xs"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="py-16 text-center text-slate-500 space-y-2">
                            <i class="fi fi-rr-shopping-cart text-3xl opacity-40"></i>
                            <p class="text-xs font-bold">Sepetiniz şu an boş.</p>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>

        <!-- Adisyon Totals & Checkout -->
        @if ($activeCheck)
            <div class="p-5 border-t border-slate-800/80 bg-[#191d2d]/80 space-y-3">
                <div class="space-y-1 text-xs text-slate-400 border-b border-slate-800/60 pb-3">
                    <div class="flex justify-between">
                        <span>Ara Toplam:</span>
                        <span class="font-bold text-slate-200">₺{{ number_format($activeCheck->subtotal, 2) }}</span>
                    </div>
                    @if($activeCheck->discount_total > 0)
                        <div class="flex justify-between text-emerald-400">
                            <span>İskonto:</span>
                            <span class="font-bold">-₺{{ number_format($activeCheck->discount_total, 2) }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Genel Toplam</span>
                        <span class="text-2xl font-black text-white">₺{{ number_format($activeCheck->total, 2) }}</span>
                    </div>

                    <form method="POST" action="{{ route('checks.close', $activeCheck) }}">
                        @csrf
                        <input type="hidden" name="redirect_to_tables" value="1">
                        <button type="submit" class="px-6 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-500 font-extrabold text-xs text-white shadow-lg shadow-emerald-600/30 transition tracking-wider uppercase">
                            ÖDEME AL
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <!-- 3. MIDDLE PANEL: PRODUCTS GRID -->
    <div class="flex-1 flex flex-col bg-[#0b0c12] relative overflow-hidden">
        @if (!$activeCheck)
            <div class="flex-1 flex items-center justify-center p-8 text-center text-slate-500">
                <div>
                    <i class="fi fi-rr-hand-pointer text-4xl block mb-3 opacity-30"></i>
                    <p class="text-base font-bold text-slate-300">Sipariş Eklemek İçin Adisyon Açın</p>
                    <p class="text-xs text-slate-500 mt-1">Sol taraftaki "ADİSYON AÇ" butonuna tıklayarak başlayabilirsiniz.</p>
                </div>
            </div>
        @else
            <!-- Search & Breadcrumb -->
            <div class="p-6 flex items-center justify-between border-b border-slate-800/80 bg-[#121522]/40">
                <div class="text-xs font-bold text-slate-400">
                    Kategori: <span class="text-indigo-400 font-black text-sm ml-1" id="currentCategoryText">Tümü</span>
                </div>
                <div class="relative w-72">
                    <i class="fi fi-rr-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="productSearch"
                        class="w-full bg-[#141724] border border-slate-800 text-white placeholder-slate-500 text-xs font-bold py-3 pl-10 pr-4 rounded-2xl outline-none focus:border-indigo-500 transition"
                        placeholder="Ürün ara...">
                </div>
            </div>

            <!-- Products Cards Grid -->
            <div class="flex-1 overflow-y-auto p-6 lg:p-8">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5 sm:gap-6 lg:gap-6" id="productsGrid">
                    @foreach ($categories as $category)
                        @foreach ($category->products as $product)
                            <form method="POST" action="{{ route('checks.items.store', $activeCheck) }}"
                                class="product-item ajax-form group relative aspect-square rounded-3xl bg-[#141724] border border-slate-800/80 hover:border-indigo-500/50 hover:bg-[#191d2d] transition-all shadow-lg hover:shadow-2xl cursor-pointer flex flex-col justify-center items-center p-5 text-center"
                                data-category="{{ $category->id }}" data-name="{{ mb_strtolower($product->name) }}">
                                @csrf
                                <input type="hidden" name="items[0][product_id]" value="{{ $product->id }}">
                                <input type="hidden" name="items[0][quantity]" value="1">

                                <button type="submit" class="w-full h-full flex flex-col items-center justify-center focus:outline-none">
                                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mb-3 group-hover:bg-indigo-600 group-hover:text-white group-hover:scale-110 transition-all shadow-sm">
                                        <i class="fi fi-rr-box-open text-xl"></i>
                                    </div>

                                    <h4 class="font-extrabold text-xs sm:text-sm text-slate-200 group-hover:text-white line-clamp-2 leading-snug mb-1">
                                        {{ $product->name }}
                                    </h4>
                                    <span class="font-black text-sm sm:text-base text-indigo-400">
                                        ₺{{ number_format($product->discounted_price ?: $product->price, 2) }}
                                    </span>
                                </button>
                            </form>
                        @endforeach
                    @endforeach
                </div>

                <div id="noProductsFound" class="hidden h-full flex-col items-center justify-center py-20 text-slate-500">
                    <i class="fi fi-rr-search text-4xl mb-2 opacity-40"></i>
                    <p class="text-sm font-bold">Aradığınız kriterde ürün bulunamadı.</p>
                </div>
            </div>
        @endif
    </div>

    <!-- 4. RIGHT SIDEBAR: CATEGORIES -->
    @if ($activeCheck)
        <div class="w-56 shrink-0 bg-[#121522] border-l border-slate-800/80 flex flex-col py-6 px-3 gap-2 overflow-y-auto z-20">
            <h3 class="text-[10px] font-black tracking-[0.2em] text-slate-500 uppercase px-3 mb-2">KATEGORİLER</h3>

            <div class="flex flex-col gap-1.5" id="categoryTabs">
                <button type="button"
                    class="category-tab active w-full flex items-center py-3 px-4 rounded-xl text-xs font-extrabold bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 transition"
                    data-category="all" data-name="Tümü">
                    <i class="fi fi-rr-apps mr-3 text-sm"></i>
                    <span>TÜMÜ</span>
                </button>
                @foreach ($categories as $category)
                    <button type="button"
                        class="category-tab w-full flex items-center py-3 px-4 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800/60 transition"
                        data-category="{{ $category->id }}" data-name="{{ $category->name }}">
                        <i class="fi fi-rr-restaurant mr-3 text-sm opacity-60"></i>
                        <span class="truncate">{{ mb_strtoupper($category->name) }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

</div>

<!-- ACTION MODALS -->
@if ($activeCheck)

    <!-- 1. İKRAM MODAL -->
    <div id="treatModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-amber-500/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <i class="fi fi-rr-gift text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Ürün İkram Et</h3>
                        <p class="text-xs text-slate-400">Ürün seçerek ücretsiz ikram ekleyin</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('treatModal')" class="text-slate-400 hover:text-white"><i class="fi fi-rr-cross"></i></button>
            </div>
            <form action="{{ route('checks.actions.treat', $activeCheck) }}" method="POST" class="ajax-form p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Ürün Seçiniz</label>
                    <select name="product_id" required class="w-full rounded-xl border border-slate-800 bg-[#0b0c12] p-3 text-xs font-bold text-white outline-none focus:border-indigo-500">
                        @foreach($categories as $cat)
                            @foreach($cat->products as $prod)
                                <option value="{{ $prod->id }}">{{ $prod->name }} (₺{{ number_format($prod->price, 2) }})</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Adet</label>
                        <input type="number" name="quantity" value="1" min="1" required class="w-full rounded-xl border border-slate-800 bg-[#0b0c12] p-3 text-xs font-bold text-white text-center outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Açıklama / Sebep</label>
                        <input type="text" name="reason" placeholder="Müşteri İkramı" class="w-full rounded-xl border border-slate-800 bg-[#0b0c12] p-3 text-xs font-medium text-white outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('treatModal')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-800">İptal</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-xs font-bold text-white shadow-lg shadow-amber-600/30">İkram Ekile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. İADE MODAL -->
    <div id="voidModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-rose-500/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-500/20 text-rose-400 flex items-center justify-center">
                        <i class="fi fi-rr-refresh text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">İade / İptal Et</h3>
                        <p class="text-xs text-slate-400">İptal etmek istediğiniz kalemleri seçin</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('voidModal')" class="text-slate-400 hover:text-white"><i class="fi fi-rr-cross"></i></button>
            </div>
            <form action="{{ route('checks.actions.void', $activeCheck) }}" method="POST" class="ajax-form p-6 space-y-4">
                @csrf
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    @foreach($activeCheck->items as $item)
                        @if(!$item->is_cancelled)
                            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-indigo-500/40">
                                <span class="text-xs font-bold text-slate-200">{{ $item->quantity }}x {{ $item->product_name }}</span>
                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="w-4 h-4 accent-rose-500 rounded">
                            </label>
                        @endif
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('voidModal')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-800">İptal</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-xs font-bold text-white shadow-lg shadow-rose-600/30">İptal Et</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. İSKONTO MODAL -->
    <div id="discountModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-emerald-500/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                        <i class="fi fi-rr-tags text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">İskonto Uygula</h3>
                        <p class="text-xs text-slate-400">Yüzde veya tutar indirimi tanımlayın</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('discountModal')" class="text-slate-400 hover:text-white"><i class="fi fi-rr-cross"></i></button>
            </div>
            <form action="{{ route('checks.actions.discount', $activeCheck) }}" method="POST" class="ajax-form p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">İskonto Tipi</label>
                        <select name="type" class="w-full rounded-xl border border-slate-800 bg-[#0b0c12] p-3 text-xs font-bold text-white outline-none">
                            <option value="amount">Tutar (TL)</option>
                            <option value="percentage">Yüzde (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Değer</label>
                        <input type="number" step="0.01" min="0" name="value" required placeholder="Örn: 10" class="w-full rounded-xl border border-slate-800 bg-[#0b0c12] p-3 text-xs font-bold text-white outline-none">
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('discountModal')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-800">İptal</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-white shadow-lg shadow-emerald-600/30">Uygula</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. TAŞI MODAL -->
    <div id="moveModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-sky-500/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center">
                        <i class="fi fi-rr-shuffle text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Masa Taşı</h3>
                        <p class="text-xs text-slate-400">Adisyonu başka bir masaya aktarın</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('moveModal')" class="text-slate-400 hover:text-white"><i class="fi fi-rr-cross"></i></button>
            </div>
            <form action="{{ route('checks.actions.move', $activeCheck) }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Hedef Masa</label>
                    <select name="dining_table_id" required class="w-full rounded-xl border border-slate-800 bg-[#0b0c12] p-3 text-xs font-bold text-white outline-none">
                        @foreach($moveTargets as $target)
                            <option value="{{ $target->id }}">{{ $target->name }} ({{ $target->hall?->name ?: 'Salonsuz' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('moveModal')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-800">İptal</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-xs font-bold text-white shadow-lg shadow-sky-600/30">Taşı</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. BÖL MODAL -->
    <div id="splitModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md">
        <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-violet-500/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-violet-500/20 text-violet-400 flex items-center justify-center">
                        <i class="fi fi-rr-code-branch text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Adisyon Böl</h3>
                        <p class="text-xs text-slate-400">Yeni adisyona taşınacak kalemleri seçin</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('splitModal')" class="text-slate-400 hover:text-white"><i class="fi fi-rr-cross"></i></button>
            </div>
            <form action="{{ route('checks.actions.split', $activeCheck) }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    @foreach($activeCheck->items as $item)
                        @if(!$item->is_cancelled)
                            <label class="flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer hover:border-indigo-500/40">
                                <span class="text-xs font-bold text-slate-200">{{ $item->quantity }}x {{ $item->product_name }}</span>
                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="w-4 h-4 accent-violet-500 rounded">
                            </label>
                        @endif
                    @endforeach
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('splitModal')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:bg-slate-800">İptal</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-xs font-bold text-white shadow-lg shadow-violet-600/30">Böl</button>
                </div>
            </form>
        </div>
    </div>

@endif

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Form Interception for smooth AJAX updates
        document.addEventListener('submit', async function (e) {
            if (!e.target.classList.contains('ajax-form')) return;

            e.preventDefault();
            const form = e.target;
            const adisyonPanel = document.getElementById('adisyonPanel');

            if (adisyonPanel) {
                adisyonPanel.style.opacity = '0.5';
                adisyonPanel.style.pointerEvents = 'none';
            }

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const html = await response.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const newPanel = doc.getElementById('adisyonPanel');
                    if (newPanel && adisyonPanel) {
                        adisyonPanel.innerHTML = newPanel.innerHTML;
                    }
                    
                    // Close open modals
                    document.querySelectorAll('.fixed').forEach(modal => {
                        if (modal.id && !modal.classList.contains('hidden')) {
                            closeModal(modal.id);
                        }
                    });
                } else {
                    window.location.reload();
                }
            } catch (err) {
                window.location.reload();
            } finally {
                if (adisyonPanel) {
                    adisyonPanel.style.opacity = '1';
                    adisyonPanel.style.pointerEvents = 'auto';
                }
            }
        });

        // Tabs & Live Search
        const searchInput = document.getElementById('productSearch');
        const tabs = document.querySelectorAll('.category-tab');
        const products = document.querySelectorAll('.product-item');
        const noResults = document.getElementById('noProductsFound');
        const currentCatText = document.getElementById('currentCategoryText');

        if (searchInput && tabs.length && products.length) {
            let activeCat = 'all';
            let searchTerm = '';

            function filterProducts() {
                let visibleCount = 0;
                products.forEach(p => {
                    const matchCat = activeCat === 'all' || p.dataset.category === activeCat;
                    const matchSearch = p.dataset.name.includes(searchTerm);
                    if (matchCat && matchSearch) {
                        p.style.display = 'flex';
                        visibleCount++;
                    } else {
                        p.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                    noResults.classList.add('flex');
                } else {
                    noResults.classList.add('hidden');
                    noResults.classList.remove('flex');
                }
            }

            searchInput.addEventListener('input', function (e) {
                searchTerm = e.target.value.toLowerCase();
                filterProducts();
            });

            tabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    tabs.forEach(t => {
                        t.className = 'category-tab w-full flex items-center py-3 px-4 rounded-xl text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-800/60 transition';
                    });

                    this.className = 'category-tab active w-full flex items-center py-3 px-4 rounded-xl text-xs font-extrabold bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 transition';

                    activeCat = this.dataset.category;
                    if (currentCatText) currentCatText.textContent = this.dataset.name;

                    filterProducts();
                });
            });
        }

        // Action Buttons modal triggers
        const btnIkram = document.getElementById('btnActionIkram');
        const btnIade = document.getElementById('btnActionIade');
        const btnIskonto = document.getElementById('btnActionIskonto');
        const btnBol = document.getElementById('btnActionBol');
        const btnTasi = document.getElementById('btnActionTasi');
        const btnYeni = document.getElementById('btnActionYeni');

        if (btnYeni) {
            btnYeni.addEventListener('click', () => {
                const search = document.getElementById('productSearch');
                if (search) search.focus();
            });
        }

        if (btnIkram) btnIkram.addEventListener('click', () => openModal('treatModal'));
        if (btnIade) btnIade.addEventListener('click', () => openModal('voidModal'));
        if (btnIskonto) btnIskonto.addEventListener('click', () => openModal('discountModal'));
        if (btnBol) btnBol.addEventListener('click', () => openModal('splitModal'));
        if (btnTasi) btnTasi.addEventListener('click', () => openModal('moveModal'));
    });

    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('hidden');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('hidden');
    }
</script>
@endsection
