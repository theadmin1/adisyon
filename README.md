# Adisyon Services

Laravel 13 merkezi yönetim/POS uygulaması ile .NET 8 Windows cihaz, kiosk ve yazdırma servisinden oluşan hibrit restoran otomasyon sistemi.

## Gereksinimler

- PHP 8.3 veya üzeri
- Composer 2
- Node.js 22
- .NET 8 SDK
- Merkezi kurulum için MySQL; cihazdaki çevrimdışı kurulum için SQLite

## Merkezi uygulama kurulumu

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate --force
```

Üretimde `.env` içinde en az `APP_ENV=production`, `APP_DEBUG=false`, güçlü bir `APP_KEY`, MySQL bilgileri, `TRUSTED_PROXIES`, webhook imza sırları ve güncelleme imzalama anahtarları tanımlanmalıdır.

Seed işlemi bilinçli olarak varsayılan üretim parolası oluşturmaz. Üretimde seed gerekiyorsa `SEED_ADMIN_PASSWORD`, `SEED_CASHIER_PASSWORD` ve `SEED_WAITER_PIN` değerlerini önce tanımlayın.

## Windows cihaz servisi

```powershell
dotnet restore AltF4DeviceService.sln
dotnet build AltF4DeviceService.sln
dotnet run --project src/AltF4DeviceService.WebApi/AltF4DeviceService.WebApi.csproj
```

Cihaz servisi başarılı lisans doğrulamasından sonra sunucunun ürettiği cihaz API anahtarını kendi SQLite deposunda saklar. Yerel Laravel alt süreçleri `ADISYON_OFFLINE_MODE=true` ve `DB_CONNECTION=sqlite` ile başlatılır; anahtar kaynak koda veya `.env` dosyasına yazılmaz.

## Güvenlik yapılandırması

- Cihaz uçları yalnızca `X-Device-Api-Key` başlığını kabul eder.
- Webhook istekleri HMAC-SHA256 ile doğrulanır ve mağaza kimliği bir şubeyle eşleştirilir.
- Güncelleme paketleri SHA-256 özeti ve asimetrik imza doğrulanmadan kurulmaz.
- Tenant verileri kullanıcı veya cihazın bağlı olduğu `branch_id` ile sınırlandırılır.
- `UPDATE_SIGNING_PRIVATE_KEY` yalnızca merkezi sunucuda; `UPDATE_SIGNING_PUBLIC_KEY` çevrimdışı istemcilerde bulunmalıdır.

PEM anahtarları ortam değişkenine tek satır halinde yazılacaksa satır sonlarını `\n` biçiminde kullanın.

## Test ve kalite

```bash
composer test
php vendor/bin/pint --test
npm run build
dotnet build AltF4DeviceService.sln
composer audit --locked
```

GitHub Actions aynı kontrolleri PHP 8.3, Node.js 22 ve .NET 8 ile çalıştırır.
