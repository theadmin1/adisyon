@extends('chain.layout')
@section('title', 'Diji Menü')
@section('content')
@php($canManage = auth()->user()->chain_role !== 'analyst')
<div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div><p class="institutional-page-kicker mb-1">Dijital Menü Entegrasyonu</p><h1>Diji Menü Yönetimi</h1><p class="mt-1 text-sm text-slate-400">QR menü uygulamasını adisyon verisine karıştırmadan, organizasyon ve şube bağlantılarıyla yönetin.</p></div>
    @if($integration?->is_active)
        <a href="{{ $integration->adminUrl() }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-5 py-3 text-sm font-black text-slate-950 hover:bg-cyan-400">Menü Ürünlerini Yönet <span>→</span></a>
    @endif
</div>

@if(session('success'))<div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-400">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-400">{{ $errors->first() }}</div>@endif

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 pb-5">
            <div><h2 class="font-black">QR Menü Ayarları</h2><p class="mt-1 text-xs text-slate-500">QR menü bu Adisyon uygulaması içinde yayınlanır ve mevcut şube ürünlerini kullanır.</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $integration?->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-amber-500/10 text-amber-300' }}">{{ $integration?->is_active ? 'Bağlı' : 'Yapılandırılmamış' }}</span>
        </div>

        <form method="POST" action="{{ route('chain.diji-menu.update') }}" class="mt-5 space-y-5">
            @csrf @method('PUT')
            <div><label class="mb-1.5 block text-xs font-bold text-slate-400">Firma URL Adı</label><input name="company_slug" required value="{{ old('company_slug', $integration?->company_slug) }}" placeholder="firma-adi" @disabled(!$canManage) class="w-full rounded-xl border border-slate-700 bg-slate-950 p-3 font-mono text-sm"><p class="mt-1 text-[11px] text-slate-500">Örnek: /qr-menu/<strong>firma-adi</strong>/merkez</p></div>

            <div>
                <div class="mb-3"><h3 class="text-sm font-black">Şube URL Eşlemeleri</h3><p class="mt-1 text-xs text-slate-500">Her şubenin Diji Menü’deki slug değerini girin.</p></div>
                <div class="space-y-2">
                    @forelse($branches as $branch)
                    <label class="grid items-center gap-2 rounded-xl border border-slate-800 bg-slate-950 p-3 sm:grid-cols-[minmax(0,1fr)_220px]">
                        <span><strong class="block text-sm">{{ $branch->name }}</strong><small class="font-mono text-slate-500">{{ $branch->code }}</small></span>
                        <input name="branch_slugs[{{ $branch->id }}]" value="{{ old("branch_slugs.{$branch->id}", data_get($integration?->branch_slugs, (string) $branch->id, strtolower($branch->code))) }}" placeholder="sube-slug" @disabled(!$canManage) class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 font-mono text-xs">
                    </label>
                    @empty<p class="rounded-xl border border-dashed border-slate-700 p-6 text-center text-sm text-slate-500">Erişilebilir şube bulunmuyor.</p>@endforelse
                </div>
            </div>

            @if($canManage)
            <div class="flex flex-col gap-3 border-t border-slate-800 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <label class="inline-flex items-center gap-3 text-sm font-bold"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $integration?->is_active ?? true)) class="h-5 w-5 rounded border-slate-700 bg-slate-950 text-cyan-500">Entegrasyon aktif</label>
                <button class="rounded-xl bg-cyan-500 px-6 py-3 text-sm font-black text-slate-950 hover:bg-cyan-400">Bağlantıyı Kaydet</button>
            </div>
            @endif
        </form>
    </section>

    <aside class="space-y-5">
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="font-black">Canlı QR Menü Bağlantıları</h2><p class="mt-1 text-xs text-slate-500">Yapılandırılan şubelerin müşteri menülerini açın.</p>
            <div class="mt-4 space-y-2">
                @if($integration?->is_active)
                    @foreach($branches as $branch)
                    <a href="{{ $integration->publicMenuUrl($branch) }}" target="_blank" rel="noopener" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950 p-3 text-sm hover:border-cyan-500/40"><span><strong class="block">{{ $branch->name }}</strong><small class="text-slate-500">Canlı menüyü görüntüle</small></span><span class="text-cyan-400">↗</span></a>
                    @endforeach
                @else
                    <p class="rounded-xl border border-dashed border-slate-700 p-5 text-center text-xs text-slate-500">Bağlantı ayarları kaydedildiğinde şube menüleri burada görünür.</p>
                @endif
            </div>
        </section>
        <section class="rounded-2xl border border-violet-500/20 bg-violet-500/5 p-5 text-sm"><strong class="text-violet-200">Tek uygulama, güvenli okuma</strong><p class="mt-2 text-xs leading-5 text-slate-400">QR menü aynı Adisyon uygulamasında çalışır. Ürünleri chain merkezi menüden yönetir; müşteri ekranı aktif ürünleri yalnızca okur ve POS/adisyon kayıtlarını değiştirmez.</p></section>
    </aside>
</div>
@endsection
