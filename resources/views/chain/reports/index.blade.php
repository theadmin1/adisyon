@extends('chain.layout')
@section('title', 'Raporlar')
@section('content')
<div class="mb-6"><h1 class="text-3xl font-black">Zincir Raporları</h1><p class="mt-1 text-sm text-slate-400">Şubeleri ve tarih aralıklarını karşılaştırın.</p></div>

<form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-4 md:grid-cols-4">
    <div><label class="mb-1 block text-xs text-slate-500">Başlangıç</label><input type="date" name="start_date" value="{{ $startDate->toDateString() }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 text-sm"></div>
    <div><label class="mb-1 block text-xs text-slate-500">Bitiş</label><input type="date" name="end_date" value="{{ $endDate->toDateString() }}" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 text-sm"></div>
    <div><label class="mb-1 block text-xs text-slate-500">Şube</label><select name="branch_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2.5 text-sm"><option value="">Tüm erişilebilir şubeler</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($selectedBranchId === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
    <button class="self-end rounded-lg bg-cyan-500 p-2.5 text-sm font-black text-slate-950">Raporu Getir</button>
</form>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([['Ciro','₺'.number_format((float)$summary->revenue,2,',','.'),'text-emerald-400'],['Adisyon',$summary->check_count,'text-white'],['Ortalama Sepet','₺'.number_format((float)$summary->average,2,',','.'),'text-cyan-400'],['İndirim','₺'.number_format((float)$summary->discounts,2,',','.'),'text-amber-400']] as [$label,$value,$color])
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><p class="text-xs uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black {{ $color }}">{{ $value }}</p></div>
    @endforeach
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900"><h2 class="border-b border-slate-800 p-4 font-black">Şube Karşılaştırması</h2><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-950/50 text-left text-xs uppercase text-slate-500"><tr><th class="p-4">Şube</th><th class="p-4">Adisyon</th><th class="p-4">Ciro</th></tr></thead><tbody class="divide-y divide-slate-800">@foreach($branches->whereIn('id', $selectedBranchId ? [$selectedBranchId] : $branches->pluck('id')) as $branch) @php($sale=$salesByBranch->get($branch->id)) <tr><td class="p-4 font-bold">{{ $branch->name }}</td><td class="p-4">{{ $sale?->check_count ?? 0 }}</td><td class="p-4 font-bold text-emerald-400">₺{{ number_format((float)($sale?->revenue ?? 0),2,',','.') }}</td></tr>@endforeach</tbody></table></div></section>
    <section class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900"><h2 class="border-b border-slate-800 p-4 font-black">Ödeme Dağılımı</h2><div class="space-y-3 p-4">@forelse($paymentBreakdown as $payment)<div class="flex justify-between rounded-xl bg-slate-950 p-3"><span class="capitalize text-slate-400">{{ str_replace('_',' ',$payment->payment_method) }}</span><strong>₺{{ number_format((float)$payment->total,2,',','.') }}</strong></div>@empty<p class="text-sm text-slate-500">Bu aralıkta ödeme yok.</p>@endforelse</div></section>
</div>

<section class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900"><h2 class="border-b border-slate-800 p-4 font-black">Günlük Satış</h2><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-950/50 text-left text-xs uppercase text-slate-500"><tr><th class="p-4">Tarih</th><th class="p-4">Adisyon</th><th class="p-4">Ciro</th></tr></thead><tbody class="divide-y divide-slate-800">@forelse($dailySales as $day)<tr><td class="p-4">{{ \Carbon\Carbon::parse($day->sale_date)->format('d.m.Y') }}</td><td class="p-4">{{ $day->check_count }}</td><td class="p-4 font-bold text-emerald-400">₺{{ number_format((float)$day->revenue,2,',','.') }}</td></tr>@empty<tr><td colspan="3" class="p-8 text-center text-slate-500">Kayıt bulunamadı.</td></tr>@endforelse</tbody></table></div></section>
@endsection
