@extends('layouts.app')

@section('title', $purchaseOrder->order_number.' - Satın Alma')

@section('content')
@php
    $labels = ['draft'=>'Taslak','ordered'=>'Sipariş Verildi','partial'=>'Kısmi Teslim','received'=>'Tamamlandı','cancelled'=>'İptal'];
@endphp
<div class="min-h-screen bg-[#07090e] text-slate-100">
    <header class="flex min-h-16 items-center justify-between border-b border-slate-800 bg-[#0f131f] px-4 py-3 sm:px-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('purchasing.index') }}" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-800"><i class="fi fi-rr-arrow-left"></i></a>
            <div><div class="font-mono text-xs font-bold text-orange-400">{{ $purchaseOrder->order_number }}</div><h1 class="text-lg font-black text-white">{{ $purchaseOrder->supplier->name }}</h1></div>
        </div>
        <span class="rounded-full border border-orange-500/30 bg-orange-500/10 px-3 py-1.5 text-xs font-black text-orange-300">{{ $labels[$purchaseOrder->status] ?? $purchaseOrder->status }}</span>
    </header>
    <main class="space-y-6 p-4 sm:p-8">
        @if(false && $errors->any())<div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
        <section class="grid grid-cols-2 gap-3 rounded-3xl border border-slate-800 bg-[#111524] p-5 lg:grid-cols-6">
            @foreach([
                ['Sipariş Tarihi',$purchaseOrder->order_date?->format('d.m.Y')],
                ['Beklenen Teslim',$purchaseOrder->expected_delivery_date?->format('d.m.Y') ?: '-'],
                ['Ara Toplam','₺'.number_format((float)$purchaseOrder->subtotal,2,',','.')],
                ['KDV','₺'.number_format((float)$purchaseOrder->tax_total,2,',','.')],
                ['Genel Toplam','₺'.number_format((float)$purchaseOrder->total,2,',','.')],
                ['Oluşturan',$purchaseOrder->created_by_name],
            ] as [$label,$value])
                <div class="rounded-xl border border-slate-800 bg-[#0c101b] p-3"><div class="text-[10px] font-black uppercase text-slate-500">{{ $label }}</div><div class="mt-1 font-bold text-white">{{ $value }}</div></div>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524]">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-[#0c101b] text-[10px] uppercase text-slate-500"><tr><th class="p-4">Ürün</th><th class="p-4">Sipariş</th><th class="p-4">Teslim</th><th class="p-4">Kalan</th><th class="p-4">Birim Maliyet</th><th class="p-4">KDV</th><th class="p-4">Toplam</th></tr></thead>
                <tbody class="divide-y divide-slate-800">
                @foreach($purchaseOrder->items as $item)
                    @php($remaining = max(0, (float)$item->quantity - (float)$item->received_quantity))
                    <tr><td class="p-4"><div class="font-bold text-white">{{ $item->product_name }}</div><div class="font-mono text-xs text-slate-500">{{ $item->product?->sku }}</div></td><td class="p-4">{{ number_format((float)$item->quantity,3,',','.') }} {{ $item->unit }}</td><td class="p-4 text-emerald-400">{{ number_format((float)$item->received_quantity,3,',','.') }}</td><td class="p-4 font-bold text-amber-300">{{ number_format($remaining,3,',','.') }}</td><td class="p-4 font-mono">₺{{ number_format((float)$item->unit_price,4,',','.') }}</td><td class="p-4">%{{ number_format((float)$item->tax_rate,2,',','.') }}</td><td class="p-4 font-mono font-black text-white">₺{{ number_format((float)$item->line_total,2,',','.') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>

        <div class="flex flex-wrap gap-3">
            @if($purchaseOrder->status === 'draft')
                <form method="POST" action="{{ route('purchasing.orders.place', $purchaseOrder) }}">@csrf<button class="rounded-xl bg-orange-600 px-5 py-3 text-sm font-black text-white">Siparişi Onayla</button></form>
            @endif
            @if(in_array($purchaseOrder->status, ['draft','ordered'], true))
                <form method="POST" action="{{ route('purchasing.orders.cancel', $purchaseOrder) }}">@csrf<button onclick="return confirm('Sipariş iptal edilsin mi?')" class="rounded-xl border border-rose-500/40 px-5 py-3 text-sm font-black text-rose-300">Siparişi İptal Et</button></form>
            @endif
        </div>

        @if(in_array($purchaseOrder->status, ['ordered','partial'], true))
            <section class="rounded-3xl border border-emerald-500/20 bg-[#111524] p-5">
                <h2 class="text-lg font-black text-white"><i class="fi fi-rr-box-open mr-2 text-emerald-400"></i>Mal Kabul</h2>
                <p class="mt-1 text-xs text-slate-500">Yalnızca bu teslimatta gelen miktarları girin. Onaylandığında stok otomatik artar.</p>
                <form method="POST" action="{{ route('purchasing.orders.receive', $purchaseOrder) }}" class="mt-5 space-y-4">@csrf
                    <div class="grid gap-3 md:grid-cols-3">
                        <input name="supplier_invoice_number" maxlength="100" placeholder="Tedarikçi fatura/irsaliye no" class="field">
                        <input name="supplier_invoice_date" type="date" class="field">
                        <input name="notes" maxlength="1000" placeholder="Teslimat notu" class="field">
                    </div>
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach($purchaseOrder->items as $item)
                            @php($remaining = max(0, (float)$item->quantity - (float)$item->received_quantity))
                            @if($remaining > 0)
                                <label class="rounded-xl border border-slate-800 bg-[#0c101b] p-3">
                                    <span class="block font-bold text-white">{{ $item->product_name }}</span>
                                    <span class="mb-2 block text-xs text-slate-500">Kalan: {{ number_format($remaining,3,',','.') }} {{ $item->unit }}</span>
                                    <input name="quantities[{{ $item->id }}]" type="number" min="0" max="{{ $remaining }}" step="0.001" value="0" class="field">
                                </label>
                            @endif
                        @endforeach
                    </div>
                    <button class="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-black text-white hover:bg-emerald-500">Teslimatı Onayla ve Stoğa Ekle</button>
                </form>
            </section>
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-[#111524]">
            <div class="border-b border-slate-800 p-5"><h2 class="font-black text-white">Teslimat Geçmişi</h2></div>
            <table class="w-full min-w-[800px] text-left text-sm"><thead class="bg-[#0c101b] text-[10px] uppercase text-slate-500"><tr><th class="p-4">Mal Kabul</th><th class="p-4">Tarih</th><th class="p-4">Fatura/İrsaliye</th><th class="p-4">Personel</th><th class="p-4">Değer</th><th class="p-4">Kalemler</th></tr></thead><tbody class="divide-y divide-slate-800">
            @forelse($purchaseOrder->receipts->sortByDesc('received_at') as $receipt)
                <tr><td class="p-4 font-mono text-xs font-bold text-emerald-400">{{ $receipt->receipt_number }}</td><td class="p-4 text-xs text-slate-400">{{ $receipt->received_at?->format('d.m.Y H:i') }}</td><td class="p-4 text-slate-300">{{ $receipt->supplier_invoice_number ?: '-' }}</td><td class="p-4 text-white">{{ $receipt->received_by_name }}</td><td class="p-4 font-mono font-black">₺{{ number_format((float)$receipt->received_value,2,',','.') }}</td><td class="p-4 text-xs text-slate-400">@foreach($receipt->items as $ri)<div>{{ $ri->product?->name }}: {{ number_format((float)$ri->quantity,3,',','.') }}</div>@endforeach</td></tr>
            @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">Henüz teslimat yok.</td></tr>@endforelse
            </tbody></table>
        </section>
    </main>
</div>
@endsection

@section('styles')
<style>
.field{width:100%;border:1px solid rgb(51 65 85);background:#0c101b;border-radius:.75rem;padding:.7rem .9rem;font-size:.8rem;color:white;outline:none}
.field:focus{border-color:rgb(16 185 129)}
html.light-mode .field{border-color:#cbd5e1;background:#ffffff;color:#0f172a}
html.light-mode .field::placeholder{color:#94a3b8}
html.light-mode .field:focus{border-color:rgb(16 185 129);box-shadow:0 0 0 3px rgb(16 185 129 / .12)}
</style>
@endsection
