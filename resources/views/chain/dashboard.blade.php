@extends('chain.layout')

@section('title', 'Genel Bakış')

@section('content')
<div class="mb-7">
    <h1 class="text-3xl font-black">Günlük Genel Bakış</h1>
    <p class="mt-1 text-sm text-slate-400">{{ now()->translatedFormat('d F Y, l') }} · Yetkili olduğunuz şubelerin canlı özeti</p>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Bugünkü Ciro</p>
        <p class="mt-3 text-3xl font-black text-emerald-400">₺{{ number_format($todaySales, 2, ',', '.') }}</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Tamamlanan Adisyon</p>
        <p class="mt-3 text-3xl font-black">{{ $todayChecks }}</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Açık Adisyon</p>
        <p class="mt-3 text-3xl font-black text-amber-400">{{ $openChecks }}</p>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Online Cihaz</p>
        <p class="mt-3 text-3xl font-black text-cyan-400">{{ $onlineDevices }}</p>
    </div>
</div>

<div class="mt-7 overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
        <div>
            <h2 class="font-black">Şube Performansı</h2>
            <p class="text-xs text-slate-500">Bugünkü satış ve cihaz durumu</p>
        </div>
        <span class="rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-300">{{ $branches->count() }} şube</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-950/60 text-xs uppercase tracking-wider text-slate-500">
                <tr><th class="px-5 py-3">Şube</th><th class="px-5 py-3">Durum</th><th class="px-5 py-3">Adisyon</th><th class="px-5 py-3">Ciro</th><th class="px-5 py-3">Cihazlar</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($branches as $branch)
                    @php($sales = $salesByBranch->get($branch->id))
                    <tr class="hover:bg-slate-800/30">
                        <td class="px-5 py-4"><p class="font-bold">{{ $branch->name }}</p><p class="text-xs text-slate-500">{{ $branch->code }}</p></td>
                        <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $branch->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' }}">{{ $branch->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                        <td class="px-5 py-4 font-semibold">{{ $sales?->check_count ?? 0 }}</td>
                        <td class="px-5 py-4 font-bold text-emerald-400">₺{{ number_format((float) ($sales?->sales_total ?? 0), 2, ',', '.') }}</td>
                        <td class="px-5 py-4"><span class="text-cyan-400">{{ $branch->online_devices_count }}</span><span class="text-slate-600"> / {{ $branch->devices_count }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Bu kullanıcıya atanmış şube bulunmuyor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
