@extends('chain.layout')
@section('title', 'Şubeler')
@section('content')
<div class="mb-7"><p class="institutional-page-kicker mb-1">Organizasyon Yönetimi</p><h1>Şube Durumları</h1><p class="mt-1 text-sm text-slate-400">Operasyon, stok, personel ve cihaz durumlarının kurumsal özeti</p></div>
<div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
@forelse($branches as $branch)
    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <div class="flex items-start justify-between">
            <div><h2 class="text-lg font-black">{{ $branch->name }}</h2><p class="font-mono text-xs text-cyan-400">{{ $branch->code }}</p></div>
            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $branch->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">{{ $branch->is_active ? 'Aktif' : 'Pasif' }}</span>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Bugünkü Ciro</p><p class="mt-1 font-black text-emerald-400">₺{{ number_format((float) ($todaySales[$branch->id] ?? 0), 2, ',', '.') }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Açık Adisyon</p><p class="mt-1 font-black text-amber-400">{{ $openChecks[$branch->id] ?? 0 }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Online Cihaz</p><p class="mt-1 font-black text-cyan-400">{{ $onlineDevices[$branch->id] ?? 0 }} / {{ $branch->devices_count }}</p></div>
            <div class="rounded-xl bg-slate-950 p-3"><p class="text-[10px] uppercase text-slate-500">Kritik Stok</p><p class="mt-1 font-black {{ ($lowStocks[$branch->id] ?? 0) > 0 ? 'text-rose-400' : 'text-slate-300' }}">{{ $lowStocks[$branch->id] ?? 0 }}</p></div>
        </div>
        <div class="mt-4 flex justify-between border-t border-slate-800 pt-4 text-xs text-slate-400"><span>{{ $branch->products_count }} ürün</span><span>{{ $branch->staff_profiles_count }} personel</span><span>{{ ($openShifts[$branch->id] ?? 0) ? 'Kasa açık' : 'Kasa kapalı' }}</span></div>
    </article>
@empty
    <div class="col-span-full rounded-2xl border border-dashed border-slate-700 p-12 text-center text-slate-500">Erişebileceğiniz şube bulunmuyor.</div>
@endforelse
</div>
@endsection
