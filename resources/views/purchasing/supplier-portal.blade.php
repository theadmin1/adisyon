<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <title>{{ $supplier->name }} - Ürün Portalı</title>
    <script src="{{ asset('assets/js/tailwindcss.3.4.1.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/uicons-regular-rounded.css') }}">
    <style>
        body{font-family:Inter,system-ui,sans-serif;background:#07090e;color:#f8fafc}
        .field{width:100%;min-height:46px;border:1px solid rgb(51 65 85);background:#0c101b;border-radius:.8rem;padding:.75rem .85rem;font-size:16px;color:white;outline:none}
        .field:focus{border-color:rgb(249 115 22);box-shadow:0 0 0 3px rgb(249 115 22 / .12)}
        @media(min-width:768px){.field{font-size:.875rem}}
    </style>
</head>
<body class="min-h-full">
    <header class="sticky top-0 z-20 border-b border-slate-800 bg-[#0f131f]/95 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-8">
            <div class="min-w-0">
                <div class="text-[10px] font-black uppercase tracking-[.2em] text-orange-400">Tedarikçi Ürün Portalı</div>
                <h1 class="truncate text-lg font-black text-white">{{ $supplier->branch->name }}</h1>
            </div>
            <div class="shrink-0 text-right">
                <div class="max-w-40 truncate text-xs font-black text-white sm:max-w-none">{{ $supplier->name }}</div>
                <div class="mt-1 flex items-center justify-end gap-1 text-[10px] {{ $supplier->portal_enabled && $supplier->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $supplier->portal_enabled && $supplier->is_active ? 'PORTAL AKTİF' : 'PORTAL KAPALI' }}
                </div>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl space-y-5 px-4 py-6 pb-24 sm:px-8">
        @if($errors->any())
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if(! $supplier->portal_enabled || ! $supplier->is_active)
            <section class="mx-auto mt-10 max-w-xl rounded-3xl border border-rose-500/30 bg-rose-500/10 p-8 text-center sm:p-12">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-rose-500/15 text-2xl text-rose-300"><i class="fi fi-rr-lock"></i></div>
                <h2 class="mt-5 text-2xl font-black text-white">Portal kullanıma kapalı</h2>
                <p class="mt-3 text-sm leading-6 text-rose-100/70">Bu bağlantı işletme tarafından geçici olarak kapatılmış. Erişim için işletmeyle iletişime geçin.</p>
            </section>
        @elseif(! $verified)
            <section class="mx-auto mt-8 max-w-md rounded-3xl border border-slate-800 bg-[#111524] p-6 shadow-2xl sm:p-8">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-500/15 text-2xl text-orange-400"><i class="fi fi-rr-key"></i></div>
                <h2 class="mt-5 text-center text-2xl font-black text-white">Erişim kodunu girin</h2>
                <p class="mt-2 text-center text-sm leading-6 text-slate-400">Ürün sayfasını görüntülemek için işletmenin verdiği 4 haneli tedarikçi kodunu kullanın.</p>
                <form method="POST" action="{{ route('supplier-portal.verify', $token) }}" class="mt-6 space-y-4">
                    @csrf
                    <input name="code" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="one-time-code" required autofocus placeholder="••••" class="field text-center font-mono text-3xl font-black tracking-[.6em]">
                    <button class="w-full rounded-xl bg-orange-600 px-5 py-4 text-sm font-black text-white hover:bg-orange-500">Portala Gir</button>
                </form>
            </section>
        @else
            @if(session('portal_success'))
                <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm font-bold text-emerald-300"><i class="fi fi-rr-check mr-2"></i>{{ session('portal_success') }}</div>
            @endif

            <section class="rounded-3xl border border-orange-500/20 bg-gradient-to-br from-orange-500/10 to-transparent p-5 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-xl font-black text-white sm:text-2xl">Ürün bilgisi ekleyin</h2><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">Sattığınız ürünlerin güncel bilgilerini gönderin. Yönetim bilgileri kontrol edip onaylayacak veya düzeltme notuyla reddedecek.</p></div>
                    <form method="POST" action="{{ route('supplier-portal.logout', $token) }}">@csrf<button class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-bold text-slate-400">Çıkış</button></form>
                </div>
            </section>

            <form method="POST" action="{{ route('supplier-portal.products.store', $token) }}" class="space-y-5">
                @csrf
                <section class="rounded-3xl border border-slate-800 bg-[#111524] p-5 sm:p-6">
                    <h3 class="font-black text-white">İletişim Bilgileri</h3>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <label class="text-xs font-bold text-slate-400">Yetkili kişi *<input name="contact_name" required maxlength="255" value="{{ old('contact_name', $supplier->contact_person) }}" class="field mt-1"></label>
                        <label class="text-xs font-bold text-slate-400">E-posta<input name="contact_email" type="email" maxlength="255" value="{{ old('contact_email', $supplier->email) }}" class="field mt-1"></label>
                        <label class="text-xs font-bold text-slate-400">Telefon<input name="contact_phone" maxlength="32" value="{{ old('contact_phone', $supplier->phone) }}" class="field mt-1"></label>
                    </div>
                    <label class="mt-3 block text-xs font-bold text-slate-400">Genel not<textarea name="supplier_notes" rows="3" maxlength="2000" placeholder="Teslimat bölgesi, çalışma saatleri veya genel açıklama..." class="field mt-1">{{ old('supplier_notes') }}</textarea></label>
                </section>

                <section class="rounded-3xl border border-slate-800 bg-[#111524] p-4 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div><h3 class="font-black text-white">Ürünler</h3><p class="mt-1 text-xs text-slate-500">Tek gönderimde en fazla 50 ürün eklenebilir.</p></div>
                        <button type="button" id="addProductRow" class="shrink-0 rounded-xl border border-orange-500/40 px-3 py-2.5 text-xs font-black text-orange-300 sm:px-4">+ Ürün Ekle</button>
                    </div>
                    <div id="productRows" class="mt-4 space-y-4"></div>
                </section>

                <div class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-800 bg-[#0f131f]/95 p-3 backdrop-blur sm:static sm:rounded-3xl sm:border sm:p-5">
                    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
                        <p class="hidden text-xs text-slate-500 sm:block">Gönderilen ürünler yönetim onayından geçecektir.</p>
                        <button class="w-full rounded-xl bg-emerald-600 px-8 py-4 text-sm font-black text-white hover:bg-emerald-500 sm:ml-auto sm:w-auto">Ürünleri Yönetime Gönder</button>
                    </div>
                </div>
            </form>

            <section class="rounded-3xl border border-slate-800 bg-[#111524]">
                <div class="border-b border-slate-800 p-5"><h3 class="font-black text-white">Son Gönderimleriniz</h3></div>
                <div class="divide-y divide-slate-800">
                    @php($portalStatuses = ['pending'=>['Kontrol Bekliyor','text-amber-300 bg-amber-500/10'],'approved'=>['Onaylandı','text-emerald-300 bg-emerald-500/10'],'rejected'=>['Düzeltme Gerekli','text-rose-300 bg-rose-500/10']])
                    @forelse($submissions as $submission)
                        <div class="p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div><div class="font-mono text-xs font-black text-orange-400">{{ $submission->submission_number }}</div><div class="mt-1 text-xs text-slate-500">{{ $submission->submitted_at?->format('d.m.Y H:i') }} · {{ $submission->items->count() }} ürün</div></div>
                                <span class="rounded-full px-3 py-1.5 text-xs font-black {{ $portalStatuses[$submission->status][1] ?? 'bg-slate-800 text-slate-400' }}">{{ $portalStatuses[$submission->status][0] ?? $submission->status }}</span>
                            </div>
                            @if($submission->review_notes)<div class="mt-3 rounded-xl border border-slate-800 bg-[#0c101b] p-3 text-xs text-slate-300"><span class="font-black">Yönetim notu:</span> {{ $submission->review_notes }}</div>@endif
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-slate-500">Henüz ürün gönderimi yapılmadı.</div>
                    @endforelse
                </div>
            </section>
        @endif
    </main>

    @if($verified)
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const initialItems = @json($initialItems);
            const rows = document.getElementById('productRows');
            const addButton = document.getElementById('addProductRow');
            let index = 0;
            const addRow = (values = {}) => {
                if (rows.children.length >= 50) return;
                const i = index++;
                const row = document.createElement('article');
                row.className = 'rounded-2xl border border-slate-800 bg-[#0c101b] p-4';
                row.innerHTML = `<div class="mb-3 flex items-center justify-between"><div class="text-xs font-black uppercase tracking-wider text-orange-400">Ürün <span data-row-number></span></div><button type="button" class="remove-row rounded-lg border border-rose-500/30 px-3 py-1.5 text-xs font-black text-rose-300">Sil</button></div><div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"><label class="text-xs font-bold text-slate-400 md:col-span-2">Ürün adı *<input name="items[${i}][product_name]" required maxlength="255" class="field mt-1" placeholder="Örn. Osmancık pirinç"></label><label class="text-xs font-bold text-slate-400">Marka<input name="items[${i}][brand]" maxlength="255" class="field mt-1"></label><label class="text-xs font-bold text-slate-400">Tedarikçi ürün kodu<input name="items[${i}][supplier_sku]" maxlength="100" class="field mt-1"></label><label class="text-xs font-bold text-slate-400">Barkod<input name="items[${i}][barcode]" maxlength="64" inputmode="numeric" class="field mt-1"></label><label class="text-xs font-bold text-slate-400">Birim *<select name="items[${i}][unit]" required class="field mt-1"><option value="adet">Adet</option><option value="kg">Kilogram</option><option value="gr">Gram</option><option value="lt">Litre</option><option value="ml">Mililitre</option><option value="koli">Koli</option><option value="paket">Paket</option></select></label><label class="text-xs font-bold text-slate-400">Paket / koli içeriği<input name="items[${i}][package_description]" maxlength="255" class="field mt-1" placeholder="Örn. 12 x 1 kg"></label><label class="text-xs font-bold text-slate-400">Birim fiyat (₺) *<input name="items[${i}][unit_price]" type="number" min="0" step="0.0001" required class="field mt-1"></label><label class="text-xs font-bold text-slate-400">KDV %<input name="items[${i}][tax_rate]" type="number" min="0" max="100" step="0.01" value="20" class="field mt-1"></label><label class="text-xs font-bold text-slate-400">Minimum sipariş *<input name="items[${i}][minimum_order_quantity]" type="number" min="0.001" step="0.001" value="1" required class="field mt-1"></label><label class="text-xs font-bold text-slate-400">Teslim süresi (gün)<input name="items[${i}][delivery_days]" type="number" min="0" max="365" class="field mt-1"></label><label class="text-xs font-bold text-slate-400 md:col-span-2 xl:col-span-4">Ürün açıklaması<textarea name="items[${i}][notes]" rows="2" maxlength="1000" class="field mt-1" placeholder="Kalite, menşei, saklama veya diğer ürün bilgileri..."></textarea></label></div>`;
                const set = (name, value) => { const field = row.querySelector(`[name="items[${i}][${name}]"]`); if (field && value !== undefined && value !== null) field.value = value; };
                ['product_name','brand','supplier_sku','barcode','unit','package_description','unit_price','tax_rate','minimum_order_quantity','delivery_days','notes'].forEach(name => set(name, values[name]));
                row.querySelector('.remove-row').addEventListener('click', () => {
                    row.remove();
                    if (!rows.children.length) addRow();
                    renumber();
                });
                rows.appendChild(row);
                renumber();
            };
            const renumber = () => rows.querySelectorAll('[data-row-number]').forEach((element, rowIndex) => element.textContent = rowIndex + 1);
            addButton.addEventListener('click', () => addRow());
            (initialItems.length ? initialItems : [{}]).forEach(addRow);
        });
        </script>
    @endif
</body>
</html>
