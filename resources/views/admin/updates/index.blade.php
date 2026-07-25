@extends('admin.layout')

@section('title', 'Sistem Güncelleme & Offline Sync Merkezi')

@section('content')
<div class="space-y-6">

    <!-- HEADER -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-[#141620] p-6 rounded-xl border border-gray-800">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center space-x-3">
                <span>🚀 Sistem Güncelleme & Offline Sync Merkezi</span>
            </h1>
            <p class="text-sm text-gray-400 mt-1">Canlı sunucudaki (adisyon.synaptropic.com) en yeni yazılım güncellemelerini ve verileri çevrimdışı kasanıza indirin.</p>
        </div>
        <div class="flex items-center space-x-3">
            <span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-xs px-3 py-1.5 rounded-full font-mono font-bold">
                ✓ Sürüm: v1.2.5 GÜNCEL
            </span>
        </div>
    </div>

    <!-- MAIN UPDATE CARD -->
    <div class="bg-gradient-to-r from-indigo-950/80 via-[#161b2e] to-slate-900 p-8 rounded-2xl border border-indigo-500/40 shadow-2xl space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 border-b border-indigo-900/50 pb-6">
            <div class="flex items-start space-x-5">
                <div class="w-14 h-14 rounded-2xl bg-indigo-600/30 border border-indigo-500/50 text-indigo-400 flex items-center justify-center text-3xl font-bold shadow-lg shadow-indigo-600/30">
                    ☁️
                </div>
                <div class="space-y-1">
                    <h2 class="text-xl font-extrabold text-white">Canlı Sunucudan Offline Modu Güncelle</h2>
                    <p class="text-sm text-indigo-200/80">
                        Bu işlem <strong>adisyon.synaptropic.com</strong> adresindeki güncel yazılım kodlarını ve veritabanı şemasını yerel makinenize indirir.
                    </p>
                </div>
            </div>

            <form action="{{ route('admin.sync.update-system') }}" method="POST" onsubmit="return confirm('adisyon.synaptropic.com sunucusundan en güncel yazılım paketini ve veritabanı snapshot verilerini indirip kurmak istediğinize emin misiniz?');">
                @csrf
                <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-indigo-600 to-sky-600 hover:from-indigo-500 hover:to-sky-500 active:scale-95 text-white font-extrabold text-sm px-8 py-4 rounded-xl transition duration-200 flex items-center justify-center space-x-3 shadow-xl shadow-indigo-600/40 cursor-pointer border border-indigo-300/30">
                    <i class="fa-solid fa-cloud-arrow-down text-lg"></i>
                    <span>SİSTEMİ ŞİMDİ GÜNCELLE</span>
                </button>
            </form>
        </div>

        <!-- ÖZELLİKLER & GÜVENLİK KURALLARI -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-black/40 p-4 rounded-xl border border-indigo-950/80 space-y-1.5">
                <div class="flex items-center space-x-2 text-emerald-400 font-bold text-sm">
                    <span>🛡️</span>
                    <span>Dinamik .env Dosyası Korunur</span>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Yerel bilgisayarınızdaki Cihaz API Key, yerel makine IP adresi ve özel çevre yapılandırmanız kesinlikle silinmez veya bozulmaz.
                </p>
            </div>

            <div class="bg-black/40 p-4 rounded-xl border border-indigo-950/80 space-y-1.5">
                <div class="flex items-center space-x-2 text-sky-400 font-bold text-sm">
                    <span>💾</span>
                    <span>SQLite Veritabanı Yenilenir</span>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Canlı sunucudaki güncel Menü, Ürünler, Kategoriler ve Masa düzeni yerel SQLite veritabanınıza güvenle senkronize edilir.
                </p>
            </div>

            <div class="bg-black/40 p-4 rounded-xl border border-indigo-950/80 space-y-1.5">
                <div class="flex items-center space-x-2 text-amber-400 font-bold text-sm">
                    <span>💻</span>
                    <span>C# & CLI Otomasyon Desteği</span>
                </div>
                <p class="text-xs text-gray-400 leading-relaxed">
                    C# masaüstü uygulamanızdan veya komut satırından <code class="text-amber-300 font-mono text-[11px]">php artisan app:update-offline-system</code> çalıştırabilirsiniz.
                </p>
            </div>
        </div>
    </div>

    <!-- SİSTEM BİLGİLERİ KARTI -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-[#141620] p-6 rounded-xl border border-gray-800 space-y-4">
            <h3 class="text-base font-bold text-white flex items-center space-x-2">
                <span>📁 Yerel Çevrimdışı Veritabanı Durumu</span>
            </h3>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between py-2 border-b border-gray-800">
                    <span class="text-gray-400">Veritabanı Dosya Boyutu:</span>
                    <span class="font-mono text-indigo-400 font-bold">{{ $dbSize }}</span>
                </div>
                <div class="flex justify-between py-2 border-b border-gray-800">
                    <span class="text-gray-400">Son Güncelleme Tarihi:</span>
                    <span class="font-mono text-emerald-400 font-bold">{{ $dbLastModified }}</span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-gray-400">Canlı Sunucu Bağlantısı:</span>
                    <span class="text-emerald-400 font-bold">https://adisyon.synaptropic.com</span>
                </div>
            </div>
        </div>

        <div class="bg-[#141620] p-6 rounded-xl border border-gray-800 space-y-4">
            <h3 class="text-base font-bold text-white flex items-center space-x-2">
                <span>🔌 C# Servis Entegrasyon Bilgisi</span>
            </h3>
            <p class="text-xs text-gray-400 leading-relaxed">
                C# WinForms / WPF uygulamanızdaki Yönetim Paneli butonundan güncelleme başlatmak için arka planda aşağıdaki Laravel komutunu tetikleyebilirsiniz:
            </p>
            <div class="bg-black/60 p-3 rounded-lg border border-gray-800 font-mono text-xs text-amber-400 select-all break-all">
                php artisan app:update-offline-system --force
            </div>
        </div>
    </div>

</div>
@endsection
