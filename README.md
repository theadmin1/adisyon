# 🍽️ AltF4 Adisyon & Restoran Yönetim Sistemi (Hybrid Kiosk Architecture)

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![C# .NET](https://img.shields.io/badge/C%23_.NET-8.0-512BD4?style=for-the-badge&logo=dotnet&logoColor=white)
![Windows Forms](https://img.shields.io/badge/WinForms-WebView2-0078D6?style=for-the-badge&logo=windows&logoColor=white)
![SQLite](https://img.shields.io/badge/SQLite-Local_DB-003B57?style=for-the-badge&logo=sqlite&logoColor=white)

**AltF4 Adisyon**, restoranlar, kafeler ve hızlı servis işletmeleri için geliştirilmiş **Merkezi Web Yönetim Portalı (Central Admin)** ve **Windows Masaüstü Kiosk Servisi (C# .NET 8)** hibrit mimarisine sahip kapsamlı bir lisanslama ve adisyon yönetim çözümüdür.

---

## 🌟 Öne Çıkan Özellikler

### 🏰 1. Central Admin Portalı (Laravel 11 + Tailwind CSS)
* **Canlı Yayın Adresi**: [https://adisyon.synaptropic.com/admin/login](https://adisyon.synaptropic.com/admin/login)
* **Şube & Restoran Yönetimi**: Şubelerin, masaların ve yetkili hesapların merkezi takibi.
* **Lisanslama Merkezi**: Şubelere özel lisans anahtarı üretimi, süre uzatma, pasife alma/askıya alma işlemleri.
* **Cihaz Monitörü & API Key**: Şubelerde çalışan C# Kiosk uygulamalarının benzersiz `UUID`, `IP`, `Versiyon` ve `X-Device-Api-Key` el sıkışma kontrolü.
* **Canlı Cihaz Logları**: İstemcilerden gelen canlılık (Heartbeat), lisans ve sistem durumlarının anlık kaydı.

### 🖥️ 2. Windows Masaüstü Kiosk Servisi (C# .NET 8 + WebView2)
* **Dahili Kiosk Tarayıcı**: Microsoft Chromium WebView2 tabanlı, tam ekran, yüksek performanslı Adisyon arayüzü.
* **Sistem Tepsi (System Tray) Entegrasyonu**: Arka planda çalışan tepsi ikonu ve 1980 retro şifreli yönetici paneli (`AdminLoginForm`).
* **Otomatik Kimlik Dosyalaması (SQLite)**: İlk kurulumda donanıma özel `Device UUID` ve `Device API Key` üreterek yerel SQLite veritabanında saklama.
* **Canlı Lisans Kilit Sistemi**: Lisans pasife çekildiğinde Kiosk tarayıcısını anında kapatıp tekil şık ikaz penceresi (`LicenseWarningForm`) gösterme.
* **Otomatik Canlılık Sinyali (Heartbeat Ping)**: Her 30 saniyede bir merkeze ping göndererek cihaz durumunu `🟢 ONLINE` olarak tutma.

---

## 🚀 Mimariler ve API Endpoint'leri

Sistem istemci (C# Windows Masaüstü) ile sunucu (Laravel Central API) arasında güvenli `X-Device-Api-Key` başlık doğrulaması kullanır.

| Yöntem | Endpoint | Açıklama |
| :--- | :--- | :--- |
| `POST` | `/api/v1/license/verify` | Cihaz ilk kayıt el sıkışması & Lisans anahtarı doğrulaması (API Key üretir) |
| `POST` | `/api/v1/device/ping` | 30 saniyelik canlılık sinyali (Heartbeat) ve anlık lisans kontrolü |

---

## 🛠️ Kurulum ve Çalıştırma

### 1. Central Web Uygulaması (Laravel)
```bash
# Bağımlılıkları yükleyin
composer install

# Environment dosyasını oluşturun ve anahtarı üretin
cp .env.example .env
php artisan key:generate

# Veritabanı migration ve varsayılan seed verilerini yükleyin
php artisan migrate --seed

# Yerel geliştirme sunucusunu başlatın
php artisan serve
```

### 2. Windows Masaüstü Servis Uygulaması (C# .NET 8)
```powershell
# Projeyi derleyin
dotnet build src/AltF4DeviceService.WebApi/AltF4DeviceService.WebApi.csproj

# Servisi ve Kiosk uygulamasını başlatın
dotnet run --project src/AltF4DeviceService.WebApi/AltF4DeviceService.WebApi.csproj
```

---

## 🔐 Güvenlik ve Canlı Sunucu (Production)

* **Canlı Sunucu IP**: `95.111.230.88`
* **Central Admin Portal**: `https://adisyon.synaptropic.com/admin/login`
* **Restoran Kasa Portalı**: `https://adisyon.synaptropic.com/login`
* **Güvenlik Sıkılaştırma**: `APP_ENV=production`, `APP_DEBUG=false`, CSRF korumalı API muafiyetleri ve HTTPS SSL sertifika zorunluluğu uygulanmıştır.

---

## 📄 Lisans
Bu proje **AltF4 Software Technology** tarafından geliştirilmiş olup tüm hakları saklıdır.
