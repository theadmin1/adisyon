@extends('layouts.app')

@section('title', 'Tedarikçi & Satın Alma')

@section('content')
<div class="min-h-screen bg-[#07090e] text-slate-100">
    <header class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-[#0f131f]/95 px-4 py-3 sm:px-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white"><i class="fi fi-rr-arrow-left"></i></a>
            <div>
                <h1 class="flex items-center gap-2 text-lg font-black text-white"><i class="fi fi-rr-truck-loading text-orange-400"></i>Tedarikçi & Satın Alma</h1>
                <p class="text-xs text-slate-400">Sipariş, kısmi teslimat, mal kabul ve otomatik stok girişi</p>
            </div>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input name="search" value="{{ $search }}" placeholder="Sipariş veya tedarikçi ara..." class="w-60 rounded-xl border border-slate-700 bg-slate-900 px-4 py-2 text-xs text-white outline-none focus:border-orange-500">
            <button class="rounded-xl bg-orange-600 px-4 py-2 text-xs font-black text-white">Ara</button>
        </form>
    </header>

    <main class="space-y-6 p-4 sm:p-8">
        @if($errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach([
                ['Aktif Tedarikçi', $stats['active_suppliers'], 'text-orange-400'],
                ['Açık Sipariş', $stats['open_orders'], 'text-amber-400'],
                ['Bekleyen Teklif', $stats['pending_quotes'], 'text-violet-400'],
                ['Bekleyen Sipariş Tutarı', '₺'.number_format($stats['pending_value'], 2, ',', '.'), 'text-sky-400'],
                ['Tamamlanan Alım', '₺'.number_format($stats['received_value'], 2, ',', '.'), 'text-emerald-400'],
            ] as [$label, $value, $color])
                <div class="rounded-2xl border border-slate-800 bg-[#111524] p-4">
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-xl font-black {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="flex gap-2 border-b border-slate-800 pb-3">
            <a href="{{ route('purchasing.index', ['tab' => 'orders']) }}" class="rounded-xl border px-4 py-2 text-xs font-black {{ $tab === 'orders' ? 'border-orange-500 bg-orange-600 text-white' : 'border-slate-700 bg-slate-900 text-slate-400' }}">SATIN ALMA SİPARİŞLERİ</a>
            <a href="{{ route('purchasing.index', ['tab' => 'suppliers']) }}" class="rounded-xl border px-4 py-2 text-xs font-black {{ $tab === 'suppliers' ? 'border-orange-500 bg-orange-600 text-white' : 'border-slate-700 bg-slate-900 text-slate-400' }}">TEDARİKÇİLER</a>
            <a href="{{ route('purchasing.index', ['tab' => 'quotes']) }}" class="rounded-xl border px-4 py-2 text-xs font-black {{ $tab === 'quotes' ? 'border-orange-500 bg-orange-600 text-white' : 'border-slate-700 bg-slate-900 text-slate-400' }}">TEDARİKÇİ TEKLİFLERİ</a>
        </div>

        @if($tab === 'suppliers')
            <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
                <section class="h-fit rounded-3xl border border-slate-800 bg-[#111524] p-5">
                    <h2 class="text-base font-black text-white">Yeni Tedarikçi</h2>
                    <form method="POST" action="{{ route('purchasing.suppliers.store') }}" class="mt-4 space-y-3">
                        @csrf
                        <input name="name" required maxlength="255" placeholder="Tedarikçi unvanı *" class="field">
                        <div class="grid grid-cols-2 gap-3">
                            <input name="tax_number" maxlength="32" placeholder="Vergi no" class="field">
                            <input name="contact_person" maxlength="255" placeholder="Yetkili kişi" class="field">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input name="phone" maxlength="32" placeholder="Telefon" class="field">
                            <input name="email" type="email" maxlength="255" placeholder="E-posta" class="field">
                        </div>
                        <textarea name="address" rows="2" maxlength="1000" placeholder="Adres" class="field"></textarea>
                        <textarea name="notes" rows="2" maxlength="1000" placeholder="Notlar" class="field"></textarea>
                        <button class="w-full rounded-xl bg-orange-600 px-4 py-3 text-sm font-black text-white hover:bg-orange-500">Tedarikçiyi Kaydet</button>
                    </form>
                </section>
                <section class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524]">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-[#0c101b] text-[10px] uppercase text-slate-500"><tr><th class="p-4">Tedarikçi</th><th class="p-4">İletişim</th><th class="p-4">Vergi No</th><th class="p-4">Durum</th><th class="p-4 text-right">İşlem</th></tr></thead>
                        <tbody class="divide-y divide-slate-800">
                        @forelse($suppliers as $supplier)
                            <tr>
                                <td class="p-4"><div class="font-bold text-white">{{ $supplier->name }}</div><div class="text-xs text-slate-500">{{ $supplier->contact_person ?: '-' }}</div></td>
                                <td class="p-4 text-xs text-slate-400"><div>{{ $supplier->phone ?: '-' }}</div><div>{{ $supplier->email ?: '-' }}</div></td>
                                <td class="p-4 font-mono text-xs text-slate-400">{{ $supplier->tax_number ?: '-' }}</td>
                                <td class="p-4"><span class="rounded-full px-2 py-1 text-xs font-black {{ $supplier->is_active ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-700 text-slate-400' }}">{{ $supplier->is_active ? 'AKTİF' : 'PASİF' }}</span></td>
                                <td class="p-4 text-right">
                                    <details class="inline-block text-left">
                                        <summary class="cursor-pointer rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300">Düzenle</summary>
                                        <div class="absolute right-8 z-20 mt-2 w-80 rounded-2xl border border-slate-700 bg-[#111524] p-4 shadow-2xl">
                                            <form method="POST" action="{{ route('purchasing.suppliers.update', $supplier) }}" class="space-y-2">
                                                @csrf @method('PUT')
                                                <input name="name" required value="{{ $supplier->name }}" class="field">
                                                <input name="tax_number" value="{{ $supplier->tax_number }}" placeholder="Vergi no" class="field">
                                                <input name="contact_person" value="{{ $supplier->contact_person }}" placeholder="Yetkili" class="field">
                                                <input name="phone" value="{{ $supplier->phone }}" placeholder="Telefon" class="field">
                                                <input name="email" value="{{ $supplier->email }}" placeholder="E-posta" class="field">
                                                <textarea name="address" placeholder="Adres" class="field">{{ $supplier->address }}</textarea>
                                                <textarea name="notes" placeholder="Notlar" class="field">{{ $supplier->notes }}</textarea>
                                                <button class="w-full rounded-lg bg-orange-600 py-2 text-xs font-black">Kaydet</button>
                                            </form>
                                            <form method="POST" action="{{ route('purchasing.suppliers.toggle', $supplier) }}" class="mt-2">@csrf<button class="w-full rounded-lg border border-slate-700 py-2 text-xs font-bold text-slate-300">{{ $supplier->is_active ? 'Pasife Al' : 'Aktifleştir' }}</button></form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-10 text-center text-slate-500">Henüz tedarikçi yok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        @elseif($tab === 'quotes')
            @if(session('generated_quote_url'))
                <section class="rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-5">
                    <div class="flex items-start gap-3">
                        <i class="fi fi-rr-link-alt mt-1 text-xl text-emerald-400"></i>
                        <div class="min-w-0 flex-1">
                            <h2 class="font-black text-white">{{ session('generated_quote_supplier') }} için teklif linki hazır</h2>
                            <p class="mt-1 text-xs text-emerald-200/70">Bu bağlantıyı şimdi kopyalayıp tedarikçiye gönderin. Güvenlik nedeniyle daha sonra tekrar gösterilmez; gerekirse yeni link oluşturabilirsiniz.</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input id="generatedQuoteUrl" readonly value="{{ session('generated_quote_url') }}" class="field font-mono text-xs">
                                <button type="button" id="copyQuoteUrl" class="shrink-0 rounded-xl bg-emerald-600 px-5 py-3 text-xs font-black text-white">Linki Kopyala</button>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
                <section class="h-fit rounded-3xl border border-slate-800 bg-[#111524] p-5">
                    <h2 class="text-base font-black text-white">Tedarikçiden Teklif İste</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Tedarikçi link üzerinden ürün, miktar, fiyat, KDV ve teslim tarihini doldurur.</p>
                    @if($suppliers->where('is_active', true)->isEmpty() || $products->isEmpty())
                        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs text-amber-300">Link oluşturmak için en az bir aktif tedarikçi ve ürün bulunmalıdır.</div>
                    @else
                        <form method="POST" action="{{ route('purchasing.quotes.store') }}" class="mt-4 space-y-3">
                            @csrf
                            <select name="supplier_id" required class="field">
                                <option value="">Tedarikçi seçin *</option>
                                @foreach($suppliers->where('is_active', true) as $supplier)
                                    <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            <select name="expires_in_days" required class="field">
                                @foreach([1, 3, 7, 14, 30] as $day)
                                    <option value="{{ $day }}" @selected((int) old('expires_in_days', 7) === $day)>Link süresi: {{ $day }} gün</option>
                                @endforeach
                            </select>
                            <textarea name="message" rows="4" maxlength="2000" placeholder="Tedarikçiye not (teslimat şartı, marka, kalite vb.)" class="field">{{ old('message') }}</textarea>
                            <button class="w-full rounded-xl bg-orange-600 px-4 py-3 text-sm font-black text-white hover:bg-orange-500"><i class="fi fi-rr-link-alt mr-2"></i>Güvenli Link Oluştur</button>
                        </form>
                    @endif
                </section>

                <section class="space-y-4">
                    @php
                        $quoteLabels = ['open'=>'Yanıt Bekleniyor','submitted'=>'Onay Bekliyor','approved'=>'Onaylandı','rejected'=>'Reddedildi','revoked'=>'İptal Edildi','expired'=>'Süresi Doldu'];
                        $quoteColors = ['open'=>'border-sky-500/30 bg-sky-500/10 text-sky-300','submitted'=>'border-violet-500/30 bg-violet-500/10 text-violet-300','approved'=>'border-emerald-500/30 bg-emerald-500/10 text-emerald-300','rejected'=>'border-rose-500/30 bg-rose-500/10 text-rose-300','revoked'=>'border-slate-600 bg-slate-800 text-slate-400','expired'=>'border-amber-500/30 bg-amber-500/10 text-amber-300'];
                    @endphp
                    @forelse($quoteRequests as $quote)
                        @php($quoteTotal = $quote->items->sum('line_total'))
                        <article class="overflow-hidden rounded-3xl border {{ $quote->status === 'submitted' ? 'border-violet-500/30' : 'border-slate-800' }} bg-[#111524]">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-800 p-5">
                                <div>
                                    <div class="font-mono text-xs font-black text-orange-400">{{ $quote->request_number }}</div>
                                    <h3 class="mt-1 text-lg font-black text-white">{{ $quote->supplier->name }}</h3>
                                    <div class="mt-1 text-xs text-slate-500">
                                        Oluşturan: {{ $quote->requested_by_name }} · {{ $quote->created_at?->format('d.m.Y H:i') }}
                                        @if($quote->status === 'open') · Son kullanım: {{ $quote->expires_at?->format('d.m.Y H:i') }} @endif
                                    </div>
                                </div>
                                <span class="rounded-full border px-3 py-1.5 text-xs font-black {{ $quoteColors[$quote->status] ?? $quoteColors['revoked'] }}">{{ $quoteLabels[$quote->status] ?? $quote->status }}</span>
                            </div>

                            @if($quote->message)
                                <div class="border-b border-slate-800 bg-[#0c101b] px-5 py-3 text-xs text-slate-400"><span class="font-black text-slate-300">İstek notu:</span> {{ $quote->message }}</div>
                            @endif

                            @if($quote->status === 'submitted' || $quote->items->isNotEmpty())
                                <div class="grid gap-3 border-b border-slate-800 p-5 text-xs sm:grid-cols-4">
                                    <div><span class="block text-slate-500">Yetkili</span><strong class="text-white">{{ $quote->contact_name ?: '-' }}</strong></div>
                                    <div><span class="block text-slate-500">İletişim</span><strong class="text-white">{{ $quote->contact_phone ?: $quote->contact_email ?: '-' }}</strong></div>
                                    <div><span class="block text-slate-500">Teslim Tarihi</span><strong class="text-white">{{ $quote->expected_delivery_date?->format('d.m.Y') ?: '-' }}</strong></div>
                                    <div><span class="block text-slate-500">Teklif Toplamı</span><strong class="font-mono text-lg text-white">₺{{ number_format($quoteTotal, 2, ',', '.') }}</strong></div>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full min-w-[760px] text-left text-xs">
                                        <thead class="bg-[#0c101b] uppercase text-slate-500"><tr><th class="p-3">Ürün</th><th class="p-3">Miktar</th><th class="p-3">Birim Fiyat</th><th class="p-3">KDV</th><th class="p-3">Toplam</th><th class="p-3">Not</th></tr></thead>
                                        <tbody class="divide-y divide-slate-800">
                                            @foreach($quote->items as $item)
                                                <tr><td class="p-3 font-bold text-white">{{ $item->product_name }}<div class="font-mono text-[10px] text-slate-500">{{ $item->sku ?: '-' }}</div></td><td class="p-3">{{ number_format((float) $item->quantity, 3, ',', '.') }} {{ $item->unit }}</td><td class="p-3 font-mono">₺{{ number_format((float) $item->unit_price, 4, ',', '.') }}</td><td class="p-3">%{{ number_format((float) $item->tax_rate, 2, ',', '.') }}</td><td class="p-3 font-mono font-black text-white">₺{{ number_format((float) $item->line_total, 2, ',', '.') }}</td><td class="max-w-52 p-3 text-slate-400">{{ $item->notes ?: '-' }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($quote->supplier_notes)
                                    <div class="border-t border-slate-800 p-4 text-xs text-slate-400"><span class="font-black text-slate-300">Tedarikçi notu:</span> {{ $quote->supplier_notes }}</div>
                                @endif
                            @endif

                            <div class="flex flex-wrap items-start gap-3 p-5">
                                @if($quote->status === 'submitted')
                                    <form method="POST" action="{{ route('purchasing.quotes.approve', $quote) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <label class="text-[10px] font-black uppercase text-slate-500">Sipariş tarihi<input name="order_date" type="date" required value="{{ now()->format('Y-m-d') }}" class="field mt-1"></label>
                                        <label class="text-[10px] font-black uppercase text-slate-500">Beklenen teslim<input name="expected_delivery_date" type="date" value="{{ $quote->expected_delivery_date?->format('Y-m-d') }}" class="field mt-1"></label>
                                        <button class="rounded-xl bg-emerald-600 px-5 py-3 text-xs font-black text-white">Onayla ve Sipariş Oluştur</button>
                                    </form>
                                    <details class="ml-auto">
                                        <summary class="cursor-pointer rounded-xl border border-rose-500/40 px-5 py-3 text-xs font-black text-rose-300">Reddet</summary>
                                        <form method="POST" action="{{ route('purchasing.quotes.reject', $quote) }}" class="mt-2 flex min-w-80 gap-2">
                                            @csrf
                                            <input name="rejection_reason" required maxlength="1000" placeholder="Red nedeni *" class="field">
                                            <button class="rounded-xl bg-rose-600 px-4 text-xs font-black text-white">Reddet</button>
                                        </form>
                                    </details>
                                @elseif($quote->status === 'open')
                                    <form method="POST" action="{{ route('purchasing.quotes.revoke', $quote) }}">
                                        @csrf
                                        <button onclick="return confirm('Bu teklif linki iptal edilsin mi?')" class="rounded-xl border border-rose-500/40 px-4 py-2 text-xs font-black text-rose-300">Linki İptal Et</button>
                                    </form>
                                @elseif($quote->status === 'approved' && $quote->purchaseOrder)
                                    <a href="{{ route('purchasing.show', $quote->purchaseOrder) }}" class="rounded-xl bg-orange-600 px-5 py-3 text-xs font-black text-white">Oluşan Siparişi Aç</a>
                                @elseif($quote->status === 'rejected')
                                    <div class="text-xs text-rose-300"><span class="font-black">Red nedeni:</span> {{ $quote->rejection_reason }}</div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="rounded-3xl border border-dashed border-slate-700 bg-[#111524] p-12 text-center text-sm text-slate-500">Henüz tedarikçi teklif isteği yok.</div>
                    @endforelse
                    @if($quoteRequests->hasPages())<div>{{ $quoteRequests->links() }}</div>@endif
                </section>
            </div>
        @else
            <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5">
                <details @if($orders->isEmpty()) open @endif>
                    <summary class="cursor-pointer text-base font-black text-white"><i class="fi fi-rr-plus mr-2 text-orange-400"></i>Yeni Satın Alma Siparişi</summary>
                    @if($suppliers->where('is_active', true)->isEmpty() || $products->isEmpty())
                        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-300">Sipariş oluşturmak için en az bir aktif tedarikçi ve ürün bulunmalıdır.</div>
                    @else
                        <form method="POST" action="{{ route('purchasing.orders.store') }}" class="mt-5 space-y-4">
                            @csrf
                            <div class="grid gap-3 md:grid-cols-4">
                                <select name="supplier_id" required class="field"><option value="">Tedarikçi seçin *</option>@foreach($suppliers->where('is_active', true) as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
                                <input name="order_date" type="date" value="{{ now()->format('Y-m-d') }}" required class="field">
                                <input name="expected_delivery_date" type="date" class="field" title="Beklenen teslim tarihi">
                                <input name="notes" placeholder="Sipariş notu" class="field">
                            </div>
                            <div id="purchaseRows" class="space-y-2"></div>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" id="addPurchaseRow" class="rounded-xl border border-orange-500/40 px-4 py-2 text-xs font-black text-orange-300">+ Ürün Satırı Ekle</button>
                                <button class="ml-auto rounded-xl bg-orange-600 px-6 py-2.5 text-xs font-black text-white hover:bg-orange-500">Taslak Sipariş Oluştur</button>
                            </div>
                        </form>
                    @endif
                </details>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524]">
                @php($statusLabels = ['draft'=>'Taslak','ordered'=>'Sipariş Verildi','partial'=>'Kısmi Teslim','received'=>'Tamamlandı','cancelled'=>'İptal'])
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="bg-[#0c101b] text-[10px] uppercase text-slate-500"><tr><th class="p-4">Sipariş</th><th class="p-4">Tedarikçi</th><th class="p-4">Tarih</th><th class="p-4">Kalem</th><th class="p-4">Toplam</th><th class="p-4">Durum</th><th class="p-4"></th></tr></thead>
                        <tbody class="divide-y divide-slate-800">
                        @forelse($orders as $order)
                            <tr class="hover:bg-slate-800/20">
                                <td class="p-4 font-mono text-xs font-bold text-orange-400">{{ $order->order_number }}</td>
                                <td class="p-4 font-bold text-white">{{ $order->supplier->name }}</td>
                                <td class="p-4 text-xs text-slate-400">{{ $order->order_date?->format('d.m.Y') }}</td>
                                <td class="p-4 text-slate-300">{{ $order->items->count() }} ürün</td>
                                <td class="p-4 font-mono font-black text-white">₺{{ number_format((float)$order->total, 2, ',', '.') }}</td>
                                <td class="p-4"><span class="rounded-full bg-slate-800 px-2.5 py-1 text-xs font-black text-slate-300">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                                <td class="p-4 text-right"><a href="{{ route('purchasing.show', $order) }}" class="rounded-lg bg-orange-600 px-3 py-2 text-xs font-black text-white">Detay / Mal Kabul</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-10 text-center text-slate-500">Henüz satın alma siparişi yok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($orders->hasPages())<div class="border-t border-slate-800 p-4">{{ $orders->links() }}</div>@endif
            </section>
        @endif
    </main>
</div>
@endsection

@section('styles')
<style>.field{width:100%;border:1px solid rgb(51 65 85);background:#0c101b;border-radius:.75rem;padding:.7rem .9rem;font-size:.8rem;color:white;outline:none}.field:focus{border-color:rgb(249 115 22)}</style>
@endsection

@section('scripts')
@if(session('generated_quote_url'))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('copyQuoteUrl');
    const input = document.getElementById('generatedQuoteUrl');
    button?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = 'Kopyalandı';
        } catch (error) {
            input.select();
            document.execCommand('copy');
            button.textContent = 'Kopyalandı';
        }
    });
});
</script>
@endif
@if($tab === 'orders' && !$suppliers->where('is_active', true)->isEmpty() && !$products->isEmpty())
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('purchaseRows');
    const add = document.getElementById('addPurchaseRow');
    const options = @json($productOptions);
    let index = 0;
    const addRow = () => {
        const i = index++;
        const row = document.createElement('div');
        row.className = 'grid gap-2 rounded-xl border border-slate-800 bg-[#0c101b] p-3 md:grid-cols-[2fr_1fr_1fr_1fr_auto]';
        row.innerHTML = `<select name="items[${i}][product_id]" required class="field"><option value="">Ürün seçin *</option>${options.map(p=>`<option value="${p.id}">${window.escapeHtml(p.name)} (${window.escapeHtml(p.sku || '-')}, ${window.escapeHtml(p.unit || 'adet')})</option>`).join('')}</select><input name="items[${i}][quantity]" type="number" min="0.001" step="0.001" required placeholder="Miktar" class="field"><input name="items[${i}][unit_price]" type="number" min="0" step="0.0001" required placeholder="Birim maliyet" class="field"><input name="items[${i}][tax_rate]" type="number" min="0" max="100" step="0.01" value="20" placeholder="KDV %" class="field"><button type="button" class="remove-row rounded-lg border border-rose-500/30 px-3 text-rose-300">Sil</button>`;
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
        rows.appendChild(row);
    };
    add.addEventListener('click', addRow);
    addRow();
});
</script>
@endif
@endsection
