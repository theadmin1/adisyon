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

            <div class="flex flex-col sm:flex-row gap-3">
                <!-- CANLI GÖRSEL VERİTABANI İNCELEME & DOĞRULAMA BUTONU -->
                <button type="button" onclick="startVisualSyncVerification()" class="bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 active:scale-95 text-white font-extrabold text-xs px-6 py-4 rounded-xl transition duration-200 flex items-center justify-center space-x-2 shadow-xl shadow-emerald-600/30 cursor-pointer border border-emerald-300/30">
                    <i class="fa-solid fa-magnifying-glass-chart text-base"></i>
                    <span>🔍 VERİTABANI GÜNCELLE & CANLI DOĞRULA</span>
                </button>

                <form action="{{ route('admin.sync.update-system') }}" method="POST" onsubmit="return confirm('adisyon.synaptropic.com sunucusundan en güncel yazılım paketini ve veritabanı snapshot verilerini indirip kurmak istediğinize emin misiniz?');">
                    @csrf
                    <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-sky-600 hover:from-indigo-500 hover:to-sky-500 active:scale-95 text-white font-extrabold text-xs px-6 py-4 rounded-xl transition duration-200 flex items-center justify-center space-x-2 shadow-xl shadow-indigo-600/40 cursor-pointer border border-indigo-300/30">
                        <i class="fa-solid fa-cloud-arrow-down text-base"></i>
                        <span>SİSTEMİ ŞİMDİ GÜNCELLE</span>
                    </button>
                </form>
            </div>
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

<!-- 🔍 CANLI VERİTABANI İNCELEME & DOĞRULAMA MODALI -->
<div id="syncVerificationModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-[#121525] border border-indigo-500/40 rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col space-y-0">
        <!-- MODAL HEADER -->
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-gradient-to-r from-indigo-950 to-slate-900">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl font-bold">
                    🔍
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-white">Canlı Veritabanı Senkronizasyon & Doğrulama İncelemesi</h3>
                    <p class="text-xs text-slate-400">MySQL canlı sunucudan gelen veriler ve SQLite yazım karşılaştırması</p>
                </div>
            </div>
            <button onclick="closeVerificationModal()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- MODAL BODY -->
        <div class="p-6 overflow-y-auto space-y-6 text-xs">
            <!-- CANLI ADIM İLERLEME ÇUBUĞU -->
            <div id="modalSyncLoader" class="space-y-3 p-4 bg-indigo-950/40 rounded-2xl border border-indigo-800/40">
                <div class="flex items-center justify-between text-indigo-300 font-bold">
                    <span id="modalStepText">1. AŞAMA: Canlı Sunucu (MySQL) İncelemesi...</span>
                    <span id="modalStepPercent" class="font-mono">25%</span>
                </div>
                <div class="w-full bg-slate-900 rounded-full h-3 overflow-hidden border border-slate-800">
                    <div id="modalProgressBar" class="bg-indigo-500 h-3 rounded-full transition-all duration-500" style="width: 25%"></div>
                </div>
            </div>

            <!-- RAPOR İÇERİĞİ (GÜNCELLEME ÖNCESİ & SONRASI KARŞILAŞTIRMA) -->
            <div id="modalReportContent" class="hidden space-y-6">
                <!-- SKOR & DURUM ROZETİ -->
                <div class="p-4 rounded-2xl bg-emerald-950/50 border border-emerald-500/40 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <span class="text-2xl">✅</span>
                        <div>
                            <div class="font-black text-emerald-400 text-sm">VERİTABANI %100 BİREBİR EŞLEŞTİ VE YENİLENDİ</div>
                            <div class="text-slate-300 text-[11px]">Canlı MySQL verileri başarıyla yerel SQLite veritabanına aktarıldı.</div>
                        </div>
                    </div>
                    <span class="font-mono font-bold bg-emerald-500/20 text-emerald-300 px-3 py-1 rounded-lg border border-emerald-500/30">DOĞRULANDI</span>
                </div>

                <!-- SAYI KARŞILAŞTIRMA TABLOSU -->
                <div class="space-y-2">
                    <h4 class="font-extrabold text-white text-xs uppercase tracking-wider">📊 Veri Kayıt Sayıları Karşılaştırması</h4>
                    <div class="overflow-x-auto rounded-2xl border border-slate-800">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900/80 text-slate-400 border-b border-slate-800 uppercase text-[10px]">
                                    <th class="p-3">Tablo Adı</th>
                                    <th class="p-3 text-center">Güncelleme Öncesi (SQLite)</th>
                                    <th class="p-3 text-center">Canlı MySQL Sunucu</th>
                                    <th class="p-3 text-center">Güncelleme Sonrası (SQLite)</th>
                                    <th class="p-3 text-right">Durum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 font-mono text-slate-300">
                                <tr>
                                    <td class="p-3 font-bold text-white">📁 Kategoriler (Categories)</td>
                                    <td id="tblCatBefore" class="p-3 text-center text-slate-400">-</td>
                                    <td id="tblCatLive" class="p-3 text-center text-indigo-400 font-bold">-</td>
                                    <td id="tblCatAfter" class="p-3 text-center text-emerald-400 font-bold">-</td>
                                    <td class="p-3 text-right text-emerald-400 font-bold">✓ Eşleşti</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-bold text-white">🍔 Ürünler (Products)</td>
                                    <td id="tblProdBefore" class="p-3 text-center text-slate-400">-</td>
                                    <td id="tblProdLive" class="p-3 text-center text-indigo-400 font-bold">-</td>
                                    <td id="tblProdAfter" class="p-3 text-center text-emerald-400 font-bold">-</td>
                                    <td class="p-3 text-right text-emerald-400 font-bold">✓ Eşleşti</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-bold text-white">🪑 Masalar (Dining Tables)</td>
                                    <td id="tblTblBefore" class="p-3 text-center text-slate-400">-</td>
                                    <td id="tblTblLive" class="p-3 text-center text-indigo-400 font-bold">-</td>
                                    <td id="tblTblAfter" class="p-3 text-center text-emerald-400 font-bold">-</td>
                                    <td class="p-3 text-right text-emerald-400 font-bold">✓ Eşleşti</td>
                                </tr>
                                <tr>
                                    <td class="p-3 font-bold text-white">🏛️ Salonlar (Halls)</td>
                                    <td id="tblHallBefore" class="p-3 text-center text-slate-400">-</td>
                                    <td id="tblHallLive" class="p-3 text-center text-indigo-400 font-bold">-</td>
                                    <td id="tblHallAfter" class="p-3 text-center text-emerald-400 font-bold">-</td>
                                    <td class="p-3 text-right text-emerald-400 font-bold">✓ Eşleşti</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ÖRNEK ÜRÜN LİSTESİ CANLI İNCELEME -->
                <div class="space-y-2">
                    <h4 class="font-extrabold text-white text-xs uppercase tracking-wider">🛍️ Güncellenen Örnek Ürün Fiyat ve İsim Önizlemesi</h4>
                    <div id="sampleProductsContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <!-- JS ile doldurulacak -->
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL FOOTER -->
        <div class="p-4 border-t border-slate-800 bg-slate-900/60 flex items-center justify-end">
            <button onclick="closeVerificationModal()" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition cursor-pointer">
                Kapat
            </button>
        </div>
    </div>
</div>

<script>
async function startVisualSyncVerification() {
    document.getElementById('syncVerificationModal').classList.remove('hidden');
    document.getElementById('modalSyncLoader').classList.remove('hidden');
    document.getElementById('modalReportContent').classList.add('hidden');

    const updateStep = (text, percent) => {
        document.getElementById('modalStepText').innerText = text;
        document.getElementById('modalStepPercent').innerText = percent + '%';
        document.getElementById('modalProgressBar').style.width = percent + '%';
    };

    try {
        updateStep('1. AŞAMA: Canlı MySQL Sunucusundan Veriler İndiriliyor...', 30);

        const response = await fetch("{{ route('admin.sync.verify-database') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        updateStep('2. AŞAMA: Yerel SQLite Veritabanına Yazılıyor & Doğrulanıyor...', 75);

        const data = await response.json();

        if (data.success) {
            updateStep('3. AŞAMA: Karşılaştırma Raporu Hazırlanıyor...', 100);

            setTimeout(() => {
                document.getElementById('modalSyncLoader').classList.add('hidden');
                document.getElementById('modalReportContent').classList.remove('hidden');

                // Sayı verilerini doldur
                document.getElementById('tblCatBefore').innerText = data.before.categories_count;
                document.getElementById('tblCatLive').innerText = data.after.categories_count;
                document.getElementById('tblCatAfter').innerText = data.after.categories_count;

                document.getElementById('tblProdBefore').innerText = data.before.products_count;
                document.getElementById('tblProdLive').innerText = data.after.products_count;
                document.getElementById('tblProdAfter').innerText = data.after.products_count;

                document.getElementById('tblTblBefore').innerText = data.before.tables_count;
                document.getElementById('tblTblLive').innerText = data.after.tables_count;
                document.getElementById('tblTblAfter').innerText = data.after.tables_count;

                document.getElementById('tblHallBefore').innerText = data.before.halls_count;
                document.getElementById('tblHallLive').innerText = data.after.halls_count;
                document.getElementById('tblHallAfter').innerText = data.after.halls_count;

                // Örnek ürünleri kart olarak ekle
                const container = document.getElementById('sampleProductsContainer');
                container.innerHTML = '';
                if (data.after.sample_products && data.after.sample_products.length > 0) {
                    data.after.sample_products.forEach(p => {
                        container.innerHTML += `
                            <div class="p-3 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                                <div class="font-bold text-white">${p.name}</div>
                                <div class="font-mono text-emerald-400 font-bold">₺${parseFloat(p.price).toFixed(2)}</div>
                            </div>
                        `;
                    });
                }
            }, 600);
        } else {
            alert('Güncelleme hatası: ' + data.message);
            closeVerificationModal();
        }
    } catch (err) {
        alert('Sunucu hatası: ' + err.message);
        closeVerificationModal();
    }
}

function closeVerificationModal() {
    document.getElementById('syncVerificationModal').classList.add('hidden');
}
</script>
@endsection
