@extends('chain.layout')

@section('title', 'Genel Bakış')

@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div><h1 class="text-2xl font-semibold tracking-tight">Genel bakış</h1><p class="mt-1 text-sm text-slate-500">{{ now()->translatedFormat('d F Y, l') }} · Tüm şubelerin bugünkü durumu</p></div>
    <a href="{{ route('chain.reports.index') }}" class="text-sm font-medium text-slate-400 hover:text-cyan-400">Detaylı raporu aç →</a>
</div>

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <div class="flex items-center justify-between"><p class="text-xs font-medium text-slate-500">Bugünkü ciro</p><svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M4 7h16v12H4zM8 7V5h8v2m-7 6h6"/></svg></div>
        <p class="mt-4 text-2xl font-semibold tracking-tight">₺{{ number_format($todaySales, 2, ',', '.') }}</p>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <div class="flex items-center justify-between"><p class="text-xs font-medium text-slate-500">Tamamlanan adisyon</p><svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6"/></svg></div>
        <p class="mt-4 text-2xl font-semibold tracking-tight">{{ $todayChecks }}</p>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <div class="flex items-center justify-between"><p class="text-xs font-medium text-slate-500">Açık adisyon</p><span class="h-2 w-2 rounded-full bg-amber-400"></span></div>
        <p class="mt-4 text-2xl font-semibold tracking-tight">{{ $openChecks }}</p>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
        <div class="flex items-center justify-between"><p class="text-xs font-medium text-slate-500">Çevrimiçi cihaz</p><span class="h-2 w-2 rounded-full bg-emerald-500"></span></div>
        <p class="mt-4 text-2xl font-semibold tracking-tight">{{ $onlineDevices }}</p>
    </div>
</div>

<div class="mt-5 overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
    <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold">Şube performansı</h2>
            <p class="mt-0.5 text-xs text-slate-500">Satış ve bağlantı durumu</p>
        </div>
        <span class="text-xs text-slate-500">{{ $branches->count() }} şube</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-950/50 text-[11px] font-medium text-slate-500">
                <tr><th class="px-5 py-3">Şube</th><th class="px-5 py-3">Durum</th><th class="px-5 py-3">Adisyon</th><th class="px-5 py-3">Ciro</th><th class="px-5 py-3">Cihazlar</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($branches as $branch)
                    @php($sales = $salesByBranch->get($branch->id))
                    <tr class="hover:bg-slate-800/30">
                        <td class="px-5 py-4"><p class="font-medium">{{ $branch->name }}</p><p class="text-xs text-slate-500">{{ $branch->code }}</p></td>
                        <td class="px-5 py-4"><span class="inline-flex items-center gap-1.5 text-xs {{ $branch->is_active ? 'text-emerald-500' : 'text-rose-400' }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $branch->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                        <td class="px-5 py-4 font-semibold">{{ $sales?->check_count ?? 0 }}</td>
                        <td class="px-5 py-4 font-medium">₺{{ number_format((float) ($sales?->sales_total ?? 0), 2, ',', '.') }}</td>
                        <td class="px-5 py-4"><span class="text-emerald-500">{{ $branch->online_devices_count }}</span><span class="text-slate-600"> / {{ $branch->devices_count }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Bu kullanıcıya atanmış şube bulunmuyor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
