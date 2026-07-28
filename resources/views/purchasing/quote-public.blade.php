<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $quoteRequest->request_number }} - Tedarikçi Teklifi</title>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/uicons-regular-rounded.css') }}">
    <style>
        body{font-family:Inter,system-ui,sans-serif;background:#07090e;color:#f8fafc}
        .field{width:100%;border:1px solid rgb(51 65 85);background:#0c101b;border-radius:.8rem;padding:.8rem .9rem;font-size:.875rem;color:white;outline:none}
        .field:focus{border-color:rgb(249 115 22);box-shadow:0 0 0 3px rgb(249 115 22 / .12)}
    </style>
</head>
<body class="min-h-full">
    <header class="border-b border-slate-800 bg-[#0f131f]">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-5 sm:px-8">
            <div>
                <div class="text-xs font-black uppercase tracking-[.2em] text-orange-400">Tedarikçi Teklif Portalı</div>
                <h1 class="mt-1 text-xl font-black text-white">{{ $quoteRequest->branch->name }}</h1>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-900 px-4 py-2 text-right">
                <div class="font-mono text-xs font-black text-orange-300">{{ $quoteRequest->request_number }}</div>
                <div class="mt-0.5 text-[10px] text-slate-500">{{ $quoteRequest->supplier->name }}</div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-8">
        @if($errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if($quoteRequest->status === 'open' && !$quoteRequest->expires_at->isPast())
            <section class="rounded-3xl border border-orange-500/20 bg-gradient-to-br from-orange-500/10 to-transparent p-6">
                <div class="flex flex-col justify-between gap-4 sm:flex-row">
                    <div>
                        <h2 class="text-2xl font-black text-white">Ürün teklifinizi iletin</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Sunduğunuz ürünleri seçerek miktar, birim fiyat ve KDV bilgilerini girin. Teklifiniz işletme tarafından incelendikten sonra satın alma siparişine dönüştürülebilir.</p>
                    </div>
                    <div class="shrink-0 rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-xs text-amber-200">
                        <div class="font-black">Son gönderim</div>
                        <div class="mt-1">{{ $quoteRequest->expires_at->format('d.m.Y H:i') }}</div>
                    </div>
                </div>
                @if($quoteRequest->message)
                    <div class="mt-5 rounded-2xl border border-slate-700 bg-[#0c101b] p-4 text-sm text-slate-300">
                        <span class="font-black text-white">İşletme notu:</span> {{ $quoteRequest->message }}
                    </div>
                @endif
            </section>

            @if($products->isEmpty())
                <section class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-8 text-center text-amber-200">Teklif verilebilecek aktif ürün bulunmuyor. Lütfen işletmeyle iletişime geçin.</section>
            @else
                <form method="POST" action="{{ route('supplier-quotes.public.submit', $token) }}" class="space-y-6">
                    @csrf
                    <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5 sm:p-6">
                        <h3 class="text-lg font-black text-white">İletişim ve Teslimat</h3>
                        <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                            <label class="text-xs font-bold text-slate-400">Yetkili kişi *<input name="contact_name" required maxlength="255" value="{{ old('contact_name', $quoteRequest->supplier->contact_person) }}" class="field mt-1"></label>
                            <label class="text-xs font-bold text-slate-400">E-posta<input name="contact_email" type="email" maxlength="255" value="{{ old('contact_email', $quoteRequest->supplier->email) }}" class="field mt-1"></label>
                            <label class="text-xs font-bold text-slate-400">Telefon<input name="contact_phone" maxlength="32" value="{{ old('contact_phone', $quoteRequest->supplier->phone) }}" class="field mt-1"></label>
                            <label class="text-xs font-bold text-slate-400">Tahmini teslim tarihi<input name="expected_delivery_date" type="date" min="{{ now()->format('Y-m-d') }}" value="{{ old('expected_delivery_date') }}" class="field mt-1"></label>
                        </div>
                        <label class="mt-3 block text-xs font-bold text-slate-400">Genel teklif notu<textarea name="supplier_notes" rows="3" maxlength="2000" placeholder="Ödeme, teslimat, marka veya diğer koşullar..." class="field mt-1">{{ old('supplier_notes') }}</textarea></label>
                    </section>

                    <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div><h3 class="text-lg font-black text-white">Teklif Kalemleri</h3><p class="mt-1 text-xs text-slate-500">Aynı ürünü yalnızca bir kez ekleyin.</p></div>
                            <button type="button" id="addQuoteRow" class="rounded-xl border border-orange-500/40 px-4 py-2.5 text-xs font-black text-orange-300">+ Ürün Ekle</button>
                        </div>
                        <div id="quoteRows" class="mt-5 space-y-3"></div>
                        <div class="mt-5 flex justify-end border-t border-slate-800 pt-5">
                            <div class="text-right">
                                <div class="text-xs font-black uppercase tracking-wider text-slate-500">Tahmini Genel Toplam</div>
                                <div id="quoteTotal" class="mt-1 font-mono text-3xl font-black text-white">₺0,00</div>
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col items-center justify-between gap-4 rounded-3xl border border-emerald-500/20 bg-emerald-500/10 p-5 sm:flex-row">
                        <p class="max-w-2xl text-xs leading-5 text-emerald-100/70">“Teklifi Gönder” düğmesine bastığınızda bağlantı kapanır ve teklifiniz işletmenin onay ekranına iletilir.</p>
                        <button class="w-full rounded-xl bg-emerald-600 px-8 py-4 text-sm font-black text-white hover:bg-emerald-500 sm:w-auto">Teklifi Gönder</button>
                    </div>
                </form>
            @endif
        @elseif($quoteRequest->status === 'submitted')
            <section class="mx-auto max-w-2xl rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-10 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20 text-3xl text-emerald-300"><i class="fi fi-rr-check"></i></div>
                <h2 class="mt-5 text-2xl font-black text-white">Teklifiniz alındı</h2>
                <p class="mt-3 text-sm leading-6 text-emerald-100/70">Teklifiniz işletmenin onay ekranına iletildi. Bu bağlantı üzerinden yeniden gönderim yapılamaz.</p>
                <div class="mt-5 font-mono text-xs font-bold text-emerald-300">{{ $quoteRequest->request_number }}</div>
            </section>
        @elseif($quoteRequest->status === 'approved')
            <section class="mx-auto max-w-2xl rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-10 text-center"><h2 class="text-2xl font-black text-white">Teklif onaylandı</h2><p class="mt-3 text-sm text-emerald-100/70">İşletme teklifinizi satın alma siparişine dönüştürdü.</p></section>
        @elseif($quoteRequest->status === 'rejected')
            <section class="mx-auto max-w-2xl rounded-3xl border border-rose-500/30 bg-rose-500/10 p-10 text-center"><h2 class="text-2xl font-black text-white">Teklif sonuçlandırıldı</h2><p class="mt-3 text-sm text-rose-100/70">Teklif işletme tarafından uygun bulunmadı.</p>@if($quoteRequest->rejection_reason)<div class="mt-5 rounded-xl bg-black/20 p-4 text-sm text-rose-200">{{ $quoteRequest->rejection_reason }}</div>@endif</section>
        @else
            <section class="mx-auto max-w-2xl rounded-3xl border border-amber-500/30 bg-amber-500/10 p-10 text-center"><h2 class="text-2xl font-black text-white">Bağlantı kullanılamıyor</h2><p class="mt-3 text-sm text-amber-100/70">Bu teklif bağlantısının süresi dolmuş veya işletme tarafından iptal edilmiş. Yeni bağlantı için işletmeyle iletişime geçin.</p></section>
        @endif
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-8 text-center text-[10px] uppercase tracking-[.2em] text-slate-600">Güvenli Tedarikçi Teklif Portalı</footer>

    @if($quoteRequest->status === 'open' && !$products->isEmpty())
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const products = @json($productOptions);
            const initialItems = @json($initialItems);
            const rows = document.getElementById('quoteRows');
            const addButton = document.getElementById('addQuoteRow');
            const total = document.getElementById('quoteTotal');
            let index = 0;
            const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const updateTotal = () => {
                let amount = 0;
                rows.querySelectorAll('[data-quote-row]').forEach(row => {
                    const quantity = Number(row.querySelector('[data-quantity]').value || 0);
                    const price = Number(row.querySelector('[data-price]').value || 0);
                    const tax = Number(row.querySelector('[data-tax]').value || 0);
                    amount += quantity * price * (1 + tax / 100);
                });
                total.textContent = new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'}).format(amount);
            };
            const addRow = (values = {}) => {
                if (rows.children.length >= 50) return;
                const rowIndex = index++;
                const row = document.createElement('div');
                row.dataset.quoteRow = '';
                row.className = 'grid gap-3 rounded-2xl border border-slate-800 bg-[#0c101b] p-4 lg:grid-cols-[2fr_1fr_1fr_1fr_2fr_auto]';
                row.innerHTML = `<select name="items[${rowIndex}][product_id]" required class="field"><option value="">Ürün seçin *</option>${products.map(product => `<option value="${product.id}">${escapeHtml(product.name)} (${escapeHtml(product.sku || '-')}, ${escapeHtml(product.unit)})</option>`).join('')}</select><input data-quantity name="items[${rowIndex}][quantity]" type="number" min="0.001" step="0.001" required placeholder="Miktar *" class="field"><input data-price name="items[${rowIndex}][unit_price]" type="number" min="0" step="0.0001" required placeholder="Birim fiyat *" class="field"><input data-tax name="items[${rowIndex}][tax_rate]" type="number" min="0" max="100" step="0.01" value="20" placeholder="KDV %" class="field"><input name="items[${rowIndex}][notes]" maxlength="500" placeholder="Marka / ürün notu" class="field"><button type="button" class="remove-row rounded-xl border border-rose-500/30 px-3 text-xs font-black text-rose-300">Sil</button>`;
                row.querySelector('select').value = values.product_id ?? '';
                row.querySelector('[data-quantity]').value = values.quantity ?? '';
                row.querySelector('[data-price]').value = values.unit_price ?? '';
                row.querySelector('[data-tax]').value = values.tax_rate ?? 20;
                row.querySelector(`input[name="items[${rowIndex}][notes]"]`).value = values.notes ?? '';
                row.querySelectorAll('input, select').forEach(input => input.addEventListener('input', updateTotal));
                row.querySelector('.remove-row').addEventListener('click', () => {
                    row.remove();
                    if (rows.children.length === 0) addRow();
                    updateTotal();
                });
                rows.appendChild(row);
                updateTotal();
            };
            addButton.addEventListener('click', () => addRow());
            (initialItems.length ? initialItems : [{}]).forEach(addRow);
        });
        </script>
    @endif
</body>
</html>
