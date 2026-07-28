@extends('layouts.app')

@section('title', 'Tedarikçi & Satın Alma')

@section('content')
<div class="min-h-screen bg-[#07090e] text-slate-100 selection:bg-orange-500 selection:text-white">
    <!-- HEADER -->
    <header class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-[#0f131f]/95 px-4 py-3 sm:px-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition"><i class="fi fi-rr-arrow-left"></i></a>
            <div>
                <h1 class="flex items-center gap-2 text-lg font-black text-white"><i class="fi fi-rr-truck-loading text-orange-400"></i>Tedarikçi & Satın Alma</h1>
                <p class="text-xs text-slate-400">Tedarikçi ürün portalı, satın alma, mal kabul ve stok girişi</p>
            </div>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input name="search" value="{{ $search }}" placeholder="Sipariş, ürün veya tedarikçi ara..." class="w-60 rounded-xl border border-slate-700 bg-slate-900 px-4 py-2 text-xs text-white outline-none focus:border-orange-500">
            <button class="rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-2 text-xs font-black text-white transition">Ara</button>
        </form>
    </header>

    <main class="space-y-6 p-4 sm:p-8">
        @if($errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <!-- STATS CARDS -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach([
                ['Aktif Tedarikçi', $stats['active_suppliers'], 'text-orange-400'],
                ['Açık Sipariş', $stats['open_orders'], 'text-amber-400'],
                ['Bekleyen Ürün Onayı', $stats['pending_submissions'], 'text-violet-400'],
                ['Bekleyen Sipariş Tutarı', '₺'.number_format($stats['pending_value'], 2, ',', '.'), 'text-sky-400'],
                ['Tamamlanan Alım', '₺'.number_format($stats['received_value'], 2, ',', '.'), 'text-emerald-400'],
            ] as [$label, $value, $color])
                <div class="rounded-2xl border border-slate-800 bg-[#111524] p-4">
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-xl font-black {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <!-- TABS BAR -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-3">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('purchasing.index', ['tab' => 'orders']) }}" class="tab {{ $tab === 'orders' ? 'tab-active' : '' }}">SATIN ALMA SİPARİŞLERİ</a>
                <a href="{{ route('purchasing.index', ['tab' => 'suppliers']) }}" class="tab {{ $tab === 'suppliers' ? 'tab-active' : '' }}">TEDARİKÇİLER</a>
                <a href="{{ route('purchasing.index', ['tab' => 'supplier-products']) }}" class="tab {{ $tab === 'supplier-products' ? 'tab-active' : '' }}">TEDARİKÇİ ÜRÜNLERİ</a>
            </div>

            <!-- EKLENEN HIZLI MODAL BUTONLARI -->
            @if($tab === 'suppliers')
                <button type="button" onclick="openModal('createSupplierModal')" class="rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-2 text-xs font-black text-white shadow-lg shadow-orange-600/20 transition flex items-center gap-2 cursor-pointer">
                    <i class="fi fi-rr-plus text-xs"></i> Yeni Tedarikçi Ekle
                </button>
            @elseif($tab === 'orders')
                <button type="button" onclick="openModal('createOrderModal')" class="rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-2 text-xs font-black text-white shadow-lg shadow-orange-600/20 transition flex items-center gap-2 cursor-pointer">
                    <i class="fi fi-rr-plus text-xs"></i> Yeni Sipariş Oluştur
                </button>
            @endif
        </div>

        <!-- TAB: TEDARİKÇİLER -->
        @if($tab === 'suppliers')
            <section class="overflow-x-auto rounded-3xl border border-slate-800 bg-[#111524]">
                <div class="p-5 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-white">Tedarikçi Listesi</h2>
                        <p class="text-xs text-slate-500">Sistemde kayıtlı tedarikçiler ve iletişim bilgileri</p>
                    </div>
                    <button type="button" onclick="openModal('createSupplierModal')" class="rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-2 text-xs font-black text-white shadow-lg shadow-orange-600/20 transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fi fi-rr-plus text-xs"></i> Yeni Tedarikçi Ekle
                    </button>
                </div>
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-[#0c101b] text-[10px] uppercase text-slate-500">
                        <tr>
                            <th class="p-4">Tedarikçi</th>
                            <th class="p-4">İletişim</th>
                            <th class="p-4">Vergi No</th>
                            <th class="p-4">Durum</th>
                            <th class="p-4 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                    @forelse($suppliers as $supplier)
                        <tr class="hover:bg-slate-900/40 transition">
                            <td class="p-4">
                                <div class="font-bold text-white">{{ $supplier->name }}</div>
                                <div class="text-xs text-slate-500">{{ $supplier->contact_person ?: '-' }}</div>
                            </td>
                            <td class="p-4 text-xs text-slate-400">
                                <div>{{ $supplier->phone ?: '-' }}</div>
                                <div>{{ $supplier->email ?: '-' }}</div>
                            </td>
                            <td class="p-4 font-mono text-xs text-slate-400">{{ $supplier->tax_number ?: '-' }}</td>
                            <td class="p-4">
                                <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $supplier->is_active ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20' : 'bg-slate-800 text-slate-400' }}">
                                    {{ $supplier->is_active ? 'AKTİF' : 'PASİF' }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <button type="button" onclick='openEditSupplierModal(@json($supplier))' class="rounded-xl border border-slate-700 bg-slate-800 hover:bg-slate-700 hover:border-orange-500/50 px-3.5 py-1.5 text-xs font-bold text-slate-200 hover:text-white transition inline-flex items-center gap-1.5 cursor-pointer">
                                    <i class="fi fi-rr-edit text-xs text-orange-400"></i> Düzenle
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-slate-500">Henüz tedarikçi yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

        <!-- TAB: TEDARİKÇİ ÜRÜNLERİ -->
        @elseif($tab === 'supplier-products')
            <div class="grid gap-6 xl:grid-cols-[430px_1fr]">
                <section class="space-y-4">
                    <div class="rounded-3xl border border-slate-800 bg-[#111524] p-5">
                        <h2 class="font-black text-white">Tedarikçi Portal Erişimleri</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Her tedarikçinin kalıcı linki ve 4 haneli kodu vardır. Aktif/pasif düğmesi erişimi anında açar veya kapatır.</p>
                    </div>
                    @forelse($suppliers as $supplier)
                        <article class="rounded-3xl border border-slate-800 bg-[#111524] p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-black text-white">{{ $supplier->name }}</h3>
                                    <div class="mt-1 text-xs text-slate-500">{{ $supplier->contact_person ?: 'Yetkili tanımlı değil' }}</div>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black {{ $supplier->portal_enabled && $supplier->is_active ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                                    {{ $supplier->portal_enabled && $supplier->is_active ? 'PORTAL AKTİF' : 'PORTAL PASİF' }}
                                </span>
                            </div>
                            @if(isset($portalUrls[$supplier->id]) && $portalUrls[$supplier->id])
                                <div class="mt-4 space-y-2">
                                    <label class="block text-[10px] font-black uppercase text-slate-500">
                                        Portal linki
                                        <div class="mt-1 flex gap-2">
                                            <input readonly value="{{ $portalUrls[$supplier->id] }}" class="field font-mono text-[10px]">
                                            <button type="button" data-copy="{{ $portalUrls[$supplier->id] }}" class="copy-button rounded-xl border border-orange-500/40 px-3 text-xs font-black text-orange-300 hover:bg-orange-500/10 transition">Kopyala</button>
                                        </div>
                                    </label>
                                    <label class="block text-[10px] font-black uppercase text-slate-500">
                                        4 haneli kod
                                        <div class="mt-1 flex gap-2">
                                            <input readonly value="{{ $supplier->portal_code }}" class="field text-center font-mono text-xl font-black tracking-[.35em]">
                                            <button type="button" data-copy="{{ $supplier->portal_code }}" class="copy-button rounded-xl border border-orange-500/40 px-3 text-xs font-black text-orange-300 hover:bg-orange-500/10 transition">Kopyala</button>
                                        </div>
                                    </label>
                                </div>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('purchasing.supplier-portal.toggle', $supplier) }}">
                                        @csrf
                                        <button class="rounded-xl px-4 py-2 text-xs font-black {{ $supplier->portal_enabled ? 'border border-rose-500/40 text-rose-300 hover:bg-rose-500/10' : 'bg-emerald-600 hover:bg-emerald-500 text-white' }} transition">
                                            {{ $supplier->portal_enabled ? 'Erişimi Kapat' : 'Erişimi Aç' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('purchasing.supplier-portal.regenerate', $supplier) }}">
                                        @csrf
                                        <button onclick="return confirm('Eski link ve kod geçersiz olacak. Yenilensin mi?')" class="rounded-xl border border-slate-700 px-4 py-2 text-xs font-black text-slate-300 hover:bg-slate-800 transition">
                                            Link ve Kodu Yenile
                                        </button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('purchasing.supplier-portal.setup', $supplier) }}" class="mt-4">
                                    @csrf
                                    <button @disabled(! $supplier->is_active) class="w-full rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-3 text-xs font-black text-white transition disabled:cursor-not-allowed disabled:opacity-40">
                                        Portal Linki ve Kod Oluştur
                                    </button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-700 p-8 text-center text-sm text-slate-500">Önce tedarikçi oluşturun.</div>
                    @endforelse
                </section>

                <section class="space-y-4">
                    <div class="rounded-3xl border border-slate-800 bg-[#111524] p-5">
                        <h2 class="font-black text-white">Gönderilen Ürün Bilgileri</h2>
                        <p class="mt-1 text-xs text-slate-500">Tedarikçinin eklediği ürünleri doğrulayın; doğruysa onaylayın, düzeltme gerekiyorsa nedeni yazarak reddedin.</p>
                    </div>
                    @php
                        $submissionLabels = ['pending'=>'Onay Bekliyor','approved'=>'Onaylandı','rejected'=>'Reddedildi'];
                        $submissionColors = ['pending'=>'bg-amber-500/10 text-amber-300','approved'=>'bg-emerald-500/10 text-emerald-300','rejected'=>'bg-rose-500/10 text-rose-300'];
                    @endphp
                    @forelse($productSubmissions as $submission)
                        <article class="overflow-hidden rounded-3xl border {{ $submission->status === 'pending' ? 'border-amber-500/30' : 'border-slate-800' }} bg-[#111524]">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-800 p-5">
                                <div>
                                    <div class="font-mono text-xs font-black text-orange-400">{{ $submission->submission_number }}</div>
                                    <h3 class="mt-1 text-lg font-black text-white">{{ $submission->supplier->name }}</h3>
                                    <div class="mt-1 text-xs text-slate-500">{{ $submission->contact_name }} · {{ $submission->submitted_at?->format('d.m.Y H:i') }} · {{ $submission->submitted_ip ?: '-' }}</div>
                                </div>
                                <span class="rounded-full px-3 py-1.5 text-xs font-black {{ $submissionColors[$submission->status] ?? 'bg-slate-800 text-slate-400' }}">
                                    {{ $submissionLabels[$submission->status] ?? $submission->status }}
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[960px] text-left text-xs">
                                    <thead class="bg-[#0c101b] uppercase text-slate-500">
                                        <tr>
                                            <th class="p-3">Ürün</th>
                                            <th class="p-3">Kod / Barkod</th>
                                            <th class="p-3">Birim / Paket</th>
                                            <th class="p-3">Fiyat</th>
                                            <th class="p-3">KDV</th>
                                            <th class="p-3">Min. Sipariş</th>
                                            <th class="p-3">Teslim</th>
                                            <th class="p-3">Açıklama</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800">
                                    @foreach($submission->items as $item)
                                        <tr>
                                            <td class="p-3"><div class="font-bold text-white">{{ $item->product_name }}</div><div class="text-slate-500">{{ $item->brand ?: '-' }}</div></td>
                                            <td class="p-3 font-mono text-slate-400"><div>{{ $item->supplier_sku ?: '-' }}</div><div>{{ $item->barcode ?: '-' }}</div></td>
                                            <td class="p-3">{{ $item->unit }}<div class="text-slate-500">{{ $item->package_description ?: '-' }}</div></td>
                                            <td class="p-3 font-mono font-black text-white">₺{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td>
                                            <td class="p-3">%{{ number_format((float) $item->tax_rate, 2, ',', '.') }}</td>
                                            <td class="p-3">{{ number_format((float) $item->minimum_order_quantity, 3, ',', '.') }}</td>
                                            <td class="p-3">{{ $item->delivery_days === null ? '-' : $item->delivery_days.' gün' }}</td>
                                            <td class="max-w-56 p-3 text-slate-400">{{ $item->notes ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($submission->supplier_notes)
                                <div class="border-t border-slate-800 p-4 text-xs text-slate-400">
                                    <span class="font-black text-slate-300">Tedarikçi notu:</span> {{ $submission->supplier_notes }}
                                </div>
                            @endif
                            <div class="flex flex-wrap gap-3 border-t border-slate-800 p-5">
                                @if($submission->status === 'pending')
                                    <form method="POST" action="{{ route('purchasing.supplier-portal.approve', $submission) }}" class="flex flex-1 gap-2">
                                        @csrf
                                        <input name="review_notes" maxlength="1000" placeholder="Onay notu (isteğe bağlı)" class="field">
                                        <button class="shrink-0 rounded-xl bg-emerald-600 hover:bg-emerald-500 px-5 text-xs font-black text-white transition">Doğrula ve Onayla</button>
                                    </form>
                                    <details>
                                        <summary class="cursor-pointer rounded-xl border border-rose-500/40 px-5 py-3 text-xs font-black text-rose-300 hover:bg-rose-500/10 transition">Reddet</summary>
                                        <form method="POST" action="{{ route('purchasing.supplier-portal.reject', $submission) }}" class="mt-2 flex min-w-80 gap-2">
                                            @csrf
                                            <input name="review_notes" required maxlength="1000" placeholder="Düzeltme / red nedeni *" class="field">
                                            <button class="rounded-xl bg-rose-600 hover:bg-rose-500 px-4 text-xs font-black text-white transition">Reddet</button>
                                        </form>
                                    </details>
                                @else
                                    <div class="text-xs text-slate-400">
                                        <span class="font-black text-slate-300">Yönetim:</span> {{ $submission->reviewed_by_name ?: '-' }} · {{ $submission->reviewed_at?->format('d.m.Y H:i') }}
                                        @if($submission->review_notes)<div class="mt-1">{{ $submission->review_notes }}</div>@endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-700 bg-[#111524] p-12 text-center text-sm text-slate-500">Henüz tedarikçiden ürün bilgisi gelmedi.</div>
                    @endforelse
                    @if($productSubmissions->hasPages())<div>{{ $productSubmissions->links() }}</div>@endif
                </section>
            </div>

        <!-- TAB: SATIN ALMA SİPARİŞLERİ -->
        @else
            <section class="overflow-x-auto rounded-3xl border border-slate-800 bg-[#111524]">
                <div class="p-5 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-black text-white">Satın Alma Siparişleri</h2>
                        <p class="text-xs text-slate-500">Tedarikçilere verilen ve teslim alınan tüm satın alma siparişleri</p>
                    </div>
                    <button type="button" onclick="openModal('createOrderModal')" class="rounded-xl bg-orange-600 hover:bg-orange-500 px-4 py-2 text-xs font-black text-white shadow-lg shadow-orange-600/20 transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fi fi-rr-plus text-xs"></i> Yeni Sipariş Oluştur
                    </button>
                </div>
                @php($statusLabels = ['draft'=>'Taslak','ordered'=>'Sipariş Verildi','partial'=>'Kısmi Teslim','received'=>'Tamamlandı','cancelled'=>'İptal'])
                <table class="w-full min-w-[1000px] text-left text-sm">
                    <thead class="bg-[#0c101b] text-[10px] uppercase text-slate-500">
                        <tr>
                            <th class="p-4">Sipariş</th>
                            <th class="p-4">Tedarikçi</th>
                            <th class="p-4">Tarih</th>
                            <th class="p-4">Kalem</th>
                            <th class="p-4">Toplam</th>
                            <th class="p-4">Durum</th>
                            <th class="p-4 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-900/40 transition">
                            <td class="p-4 font-mono text-xs font-bold text-orange-400">{{ $order->order_number }}</td>
                            <td class="p-4 font-bold text-white">{{ $order->supplier->name }}</td>
                            <td class="p-4 text-xs text-slate-400">{{ $order->order_date?->format('d.m.Y') }}</td>
                            <td class="p-4">{{ $order->items->count() }} ürün</td>
                            <td class="p-4 font-mono font-black text-white">₺{{ number_format((float)$order->total, 2, ',', '.') }}</td>
                            <td class="p-4">
                                <span class="rounded-full bg-slate-800 px-2.5 py-1 text-xs font-black text-slate-300">
                                    {{ $statusLabels[$order->status] ?? $order->status }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <a href="{{ route('purchasing.show', $order) }}" class="rounded-xl bg-orange-600 hover:bg-orange-500 px-3.5 py-2 text-xs font-black text-white transition inline-flex items-center gap-1.5">
                                    Detay / Mal Kabul
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-slate-500">Henüz satın alma siparişi yok.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if($orders->hasPages())<div class="border-t border-slate-800 p-4">{{ $orders->links() }}</div>@endif
            </section>
        @endif
    </main>
</div>

<!-- ========================================================================== -->
<!-- 📦 MODALLAR / POPUP PENCERELERİ                                            -->
<!-- ========================================================================== -->

<!-- 1. YENİ TEDARİKÇİ EKLE MODALI -->
<div id="createSupplierModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md transition-all">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col transform transition-all">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-orange-500/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-orange-500/20 text-orange-400 border border-orange-500/30 flex items-center justify-center">
                    <i class="fi fi-rr-truck-loading text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Yeni Tedarikçi Ekle</h3>
                    <p class="text-xs text-slate-400">Sisteme yeni tedarikçi tanımı ekleyin</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('createSupplierModal')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('purchasing.suppliers.store') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Tedarikçi Unvanı *</label>
                <input name="name" required maxlength="255" placeholder="Örn: ABC Gıda San. Tic. Ltd. Şti." class="field">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Vergi Numarası</label>
                    <input name="tax_number" maxlength="32" placeholder="Vergi No" class="field">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Yetkili Kişi</label>
                    <input name="contact_person" maxlength="255" placeholder="Ad Soyad" class="field">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Telefon</label>
                    <input name="phone" maxlength="32" placeholder="05xx xxx xx xx" class="field">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">E-Posta</label>
                    <input name="email" type="email" maxlength="255" placeholder="ornek@firma.com" class="field">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Adres</label>
                <textarea name="address" rows="2" maxlength="1000" placeholder="Firma açık adresi" class="field"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Notlar</label>
                <textarea name="notes" rows="2" maxlength="1000" placeholder="Ek açıklama veya notlar" class="field"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('createSupplierModal')" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                    İptal
                </button>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-extrabold transition cursor-pointer shadow-lg shadow-orange-600/30">
                    Tedarikçiyi Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. TEDARİKÇİ DÜZENLEME MODALI -->
<div id="editSupplierModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md transition-all">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col transform transition-all">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-orange-500/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-orange-500/20 text-orange-400 border border-orange-500/30 flex items-center justify-center">
                    <i class="fi fi-rr-edit text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Tedarikçi Düzenle</h3>
                    <p class="text-xs text-slate-400" id="editSupplierSubTitle">Tedarikçi bilgilerini güncelleyin</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('editSupplierModal')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        <form id="editSupplierForm" method="POST" action="" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Tedarikçi Unvanı *</label>
                <input id="edit_name" name="name" required maxlength="255" class="field">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Vergi Numarası</label>
                    <input id="edit_tax_number" name="tax_number" maxlength="32" class="field">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Yetkili Kişi</label>
                    <input id="edit_contact_person" name="contact_person" maxlength="255" class="field">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Telefon</label>
                    <input id="edit_phone" name="phone" maxlength="32" class="field">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">E-Posta</label>
                    <input id="edit_email" name="email" type="email" maxlength="255" class="field">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Adres</label>
                <textarea id="edit_address" name="address" rows="2" maxlength="1000" class="field"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Notlar</label>
                <textarea id="edit_notes" name="notes" rows="2" maxlength="1000" class="field"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-between border-t border-slate-800">
                <button id="toggleSupplierBtn" type="button" onclick="submitToggleSupplier()" class="px-4 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-xs font-bold transition cursor-pointer">
                    Pasife Al
                </button>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeModal('editSupplierModal')" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                        İptal
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-extrabold transition cursor-pointer shadow-lg shadow-orange-600/30">
                        Değişiklikleri Kaydet
                    </button>
                </div>
            </div>
        </form>
        <!-- Ayrı Pasif/Aktif Formu -->
        <form id="toggleSupplierForm" method="POST" action="" class="hidden">
            @csrf
        </form>
    </div>
</div>

<!-- 3. YENİ SATIN ALMA SİPARİŞİ MODALI -->
<div id="createOrderModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md transition-all">
    <div class="bg-[#141724] border border-slate-800 rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[90vh] transform transition-all">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-orange-500/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-orange-500/20 text-orange-400 border border-orange-500/30 flex items-center justify-center">
                    <i class="fi fi-rr-add text-xl"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Yeni Satın Alma Sipariş Yap</h3>
                    <p class="text-xs text-slate-400">Tedarikçiye verilmek üzere taslak sipariş oluşturun</p>
                </div>
            </div>
            <button type="button" onclick="closeModal('createOrderModal')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 flex items-center justify-center transition cursor-pointer">
                <i class="fi fi-rr-cross text-xs"></i>
            </button>
        </div>

        @if($suppliers->where('is_active', true)->isEmpty() || $products->isEmpty())
            <div class="p-6">
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-300">
                    Sipariş oluşturmak için en az bir aktif tedarikçi ve ürün bulunmalıdır.
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('purchasing.orders.store') }}" class="p-6 overflow-y-auto space-y-4 flex-1">
                @csrf
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-bold text-slate-300 mb-1">Tedarikçi Seçin *</label>
                        <select name="supplier_id" required class="field">
                            <option value="">Tedarikçi seçin *</option>
                            @foreach($suppliers->where('is_active', true) as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Sipariş Tarihi *</label>
                        <input name="order_date" type="date" value="{{ now()->format('Y-m-d') }}" required class="field">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Beklenen Teslim</label>
                        <input name="expected_delivery_date" type="date" class="field" title="Beklenen teslim tarihi">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">Sipariş Notu</label>
                    <input name="notes" placeholder="Örn: Hafta içi teslim edilsin" class="field">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-bold text-slate-300">Sipariş Edilecek Ürünler *</label>
                        <button type="button" id="addPurchaseRow" class="rounded-xl border border-orange-500/40 px-3 py-1.5 text-xs font-bold text-orange-300 hover:bg-orange-500/10 transition cursor-pointer">
                            + Ürün Satırı Ekle
                        </button>
                    </div>
                    <div id="purchaseRows" class="space-y-2 max-h-60 overflow-y-auto pr-1"></div>
                </div>

                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('createOrderModal')" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer">
                        İptal
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-extrabold transition cursor-pointer shadow-lg shadow-orange-600/30">
                        Taslak Sipariş Oluştur
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection

@section('styles')
<style>
.field{width:100%;border:1px solid rgb(51 65 85);background:#0c101b;border-radius:.75rem;padding:.7rem .9rem;font-size:.8rem;color:white;outline:none}.field:focus{border-color:rgb(249 115 22)}
.tab{border:1px solid rgb(51 65 85);background:rgb(15 23 42);border-radius:.75rem;padding:.5rem 1rem;font-size:.75rem;font-weight:900;color:rgb(148 163 184)}
.tab-active{border-color:rgb(249 115 22);background:rgb(234 88 12);color:white}
</style>
@endsection

@section('scripts')
<script>
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('hidden');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('hidden');
}

let currentToggleUrl = '';
function openEditSupplierModal(supplier) {
    document.getElementById('edit_name').value = supplier.name || '';
    document.getElementById('edit_tax_number').value = supplier.tax_number || '';
    document.getElementById('edit_contact_person').value = supplier.contact_person || '';
    document.getElementById('edit_phone').value = supplier.phone || '';
    document.getElementById('edit_email').value = supplier.email || '';
    document.getElementById('edit_address').value = supplier.address || '';
    document.getElementById('edit_notes').value = supplier.notes || '';

    const updateUrl = `{{ url('purchasing/suppliers') }}/${supplier.id}`;
    document.getElementById('editSupplierForm').action = updateUrl;

    currentToggleUrl = `{{ url('purchasing/suppliers') }}/${supplier.id}/toggle`;
    const toggleBtn = document.getElementById('toggleSupplierBtn');
    if (toggleBtn) {
        toggleBtn.innerText = supplier.is_active ? 'Pasife Al' : 'Aktifleştir';
    }

    const subTitle = document.getElementById('editSupplierSubTitle');
    if (subTitle) {
        subTitle.innerText = `${supplier.name} bilgilerini güncelleyin`;
    }

    openModal('editSupplierModal');
}

function submitToggleSupplier() {
    if (currentToggleUrl) {
        const toggleForm = document.getElementById('toggleSupplierForm');
        toggleForm.action = currentToggleUrl;
        toggleForm.submit();
    }
}
</script>

@if($tab === 'supplier-products')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.copy-button').forEach(button => button.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(button.dataset.copy); button.textContent = 'Kopyalandı'; }
        catch (error) { window.prompt('Kopyalayın:', button.dataset.copy); }
    }));
});
</script>
@endif

@if(!$suppliers->where('is_active', true)->isEmpty() && !$products->isEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('purchaseRows');
    const add = document.getElementById('addPurchaseRow');
    if (!rows || !add) return;

    const options = @json($productOptions);
    let index = 0;
    const addRow = () => {
        const i = index++;
        const row = document.createElement('div');
        row.className = 'grid gap-2 rounded-xl border border-slate-800 bg-[#0c101b] p-3 md:grid-cols-[2fr_1fr_1fr_1fr_auto]';
        row.innerHTML = `<select name="items[${i}][product_id]" required class="field"><option value="">Ürün seçin *</option>${options.map(p=>`<option value="${p.id}">${window.escapeHtml(p.name)} (${window.escapeHtml(p.sku || '-')}, ${window.escapeHtml(p.unit || 'adet')})</option>`).join('')}</select><input name="items[${i}][quantity]" type="number" min="0.001" step="0.001" required placeholder="Miktar" class="field"><input name="items[${i}][unit_price]" type="number" min="0" step="0.0001" required placeholder="Birim maliyet" class="field"><input name="items[${i}][tax_rate]" type="number" min="0" max="100" step="0.01" value="20" placeholder="KDV %" class="field"><button type="button" class="remove-row rounded-lg border border-rose-500/30 px-3 text-rose-300 hover:bg-rose-500/10 transition">Sil</button>`;
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
        rows.appendChild(row);
    };
    add.addEventListener('click', addRow);
    addRow();
});
</script>
@endif
@endsection
