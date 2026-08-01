# Flutter Garson API v1

Temel adres:

```text
https://SUNUCU_ADRESI/api/v1/waiter
```

Tüm isteklerde aşağıdaki başlık gönderilmelidir:

```http
Accept: application/json
Content-Type: application/json
```

Girişten sonraki isteklerde ayrıca:

```http
Authorization: Bearer wtr_...
```

## Sürekli ve anlık veri akışı

REST istekleri Flutter'dan sunucuya yapılan değişiklikleri taşır. Sunucuda web POS, kasa, mutfak, admin, senkronizasyon veya başka bir mobil cihaz üzerinden oluşan değişiklikler Laravel Reverb WebSocket sunucusu tarafından Flutter'a anında gönderilir.

Bağlantı bilgileri token ile alınır:

```text
GET /realtime/config
```

Cevapta Pusher protokolü için `app_key`, `host`, `port`, `scheme`, özel `channel`, `event` ve `auth_endpoint` alanları bulunur. Flutter yalnızca kendi şubesinin `private-waiter.branch.{branchId}` kanalına abone olabilir.

Özel kanal yetkilendirmesi:

```http
POST /api/v1/waiter/realtime/auth
Authorization: Bearer wtr_...
Content-Type: application/json

{
  "socket_id": "1234.5678",
  "channel_name": "private-waiter.branch.42"
}
```

WebSocket adresi Pusher protokolündedir:

```text
wss://{host}:{port}/app/{app_key}?protocol=7&client=flutter&version=1.0&flash=false
```

`waiter.updated` event örneği:

```json
{
  "event_id": "dcc32a71-36e7-4f37-a062-93f50657157d",
  "branch_id": 42,
  "topics": ["orders", "kitchen"],
  "action": "CheckItem.updated",
  "references": {
    "order_id": 951,
    "item_id": 1872,
    "product_id": 91
  },
  "emitted_at": "2026-08-02T10:20:30+03:00"
}
```

`topics` alanına göre Flutter ilgili kaynağı yeniler:

- `tables`: `GET /halls`
- `menu`: `GET /categories`
- `orders`: `GET /orders/{references.order_id}` veya delta sipariş listesi
- `kitchen`: `GET /kitchen/updates?since={last_server_time}`
- `payments`: `GET /orders/{references.order_id}/payments`

WebSocket eventleri kalıcı mesaj kuyruğu değildir. Telefon uykuya geçtiğinde veya bağlantı koptuğunda event kaçabilir. Her yeniden bağlantıdan sonra `/realtime/config` cevabındaki `fallback_sync` adresleri kullanılarak REST senkronizasyonu yapılmalı, ardından yeni `server_time` saklanmalıdır. Eventler yalnızca değişiklik sinyalidir; son doğru veri her zaman REST API'den alınır.

Flutter tarafında `web_socket_channel` ile Pusher protokolü doğrudan uygulanabilir. Bağlantı sırası:

1. WebSocket'e bağlan ve `pusher:connection_established` içindeki `socket_id` değerini al.
2. `socket_id` ve `channel_name` değerlerini Bearer token ile `/realtime/auth` adresine gönder.
3. Dönen `auth` değeriyle `pusher:subscribe` mesajını WebSocket'e gönder.
4. `waiter.updated` eventlerini dinle.
5. `pusher:ping` geldiğinde `pusher:pong` gönder.
6. Bağlantı koparsa artan bekleme süresiyle yeniden bağlan ve fallback REST sync çalıştır.

Başarılı cevaplar `success: true`, hata cevapları `success: false` veya Laravel doğrulama hatalarında `message` ve `errors` alanlarını içerir. Başlıca HTTP kodları `200`, `201`, `401`, `403`, `404`, `409`, `422` ve `429` değerleridir.

## 1. Giriş ve yetkilendirme

### Personel profillerini getir

`POST /auth/profiles`

```json
{
  "restaurant_id": "REST-0001",
  "password": "restoran-sifresi"
}
```

### Giriş yap

`POST /auth/login`

```json
{
  "restaurant_id": "REST-0001",
  "password": "restoran-sifresi",
  "profile_id": 12,
  "pin": "1234",
  "device_name": "Ahmet iPhone"
}
```

Cevaptaki `data.access_token` cihazın güvenli depolama alanında tutulmalıdır. Varsayılan token süresi 30 gündür ve `WAITER_API_TOKEN_TTL_MINUTES` ile değiştirilebilir.

- `GET /auth/me`: aktif şube, personel, yetkiler ve token bitiş zamanı
- `POST /auth/logout`: mevcut tokenı iptal eder

## 2. Salon ve masalar

- `GET /halls`: aktif salonları, masaları ve masadaki aktif adisyon özetini döndürür
- `GET /tables`: tüm aktif masalar
- `GET /tables?hall_id=1&status=available`: salon/durum filtresi
- `GET /tables/{tableId}`: masa ve açık adisyonun tüm detayı

Masa durumları:

```text
available, occupied, awaiting_payment, reserved
```

## 3. Kategoriler ve menü

- `GET /categories`: kategori ve altındaki aktif ürünler
- `GET /products`: düz ürün listesi
- `GET /products?category_id=3`
- `GET /products?search=kahve&available_only=1`

Fiyatlar JSON number olarak döner. `is_available`, stok takip edilen ürünlerde stok miktarını da dikkate alır.

## 4. Siparişler

### Siparişleri getir

```text
GET /orders?status=active&scope=mine
GET /orders?updated_after=2026-08-01T12:00:00Z
GET /orders/{orderId}
```

`status`: `active`, `open`, `awaiting_payment`, `closed`, `cancelled`
`scope`: `all` veya `mine`

### Yeni adisyon aç

`POST /orders`

```json
{
  "client_reference": "0f8526fc-66c9-4c61-9890-1f40df4e7af4",
  "dining_table_id": 25,
  "guest_count": 3,
  "customer_notes": "Fıstık alerjisi var",
  "items": [
    {
      "product_id": 91,
      "quantity": 2,
      "notes": "Soğansız"
    }
  ]
}
```

`client_reference` Flutter tarafından bir kez UUID olarak üretilmeli ve ağ hatasında aynı istek tekrar gönderilirken değiştirilmemelidir. Böylece tekrar denemeler ikinci bir adisyon açmaz.

### Adisyona ürün ekle

`POST /orders/{orderId}/items`

```json
{
  "items": [
    {"product_id": 91, "quantity": 1, "notes": "Acısız"}
  ]
}
```

- `DELETE /orders/{orderId}/items/{itemId}`: yalnızca henüz mutfağa gönderilmemiş kalemi kaldırır
- `PATCH /orders/{orderId}/notes`: `customer_notes` alanını günceller
- `POST /orders/{orderId}/send-kitchen`: yeni kalemleri mutfağa gönderir
- `POST /orders/{orderId}/request-payment`: masayı/adisyonu hesap bekliyor durumuna alır

## 5. Mutfak ve servis bildirimleri

`GET /kitchen/updates`

WebSocket birincil bildirim kanalıdır. Aşağıdaki polling/delta akışı ilk açılışta ve bağlantı yeniden kurulduğunda güvenli fallback olarak kullanılır:

1. İlk istekte `since` göndermeyin.
2. Cevaptaki `meta.server_time` değerini saklayın.
3. `meta.poll_after_seconds` sonrasında aynı endpointi `since={saklanan_deger}` ile çağırın.
4. Her başarılı cevapta saklanan zamanı yeni `meta.server_time` ile değiştirin.

Örnek:

```text
GET /kitchen/updates?since=2026-08-01T12:00:00Z&mine=1
```

Filtreler:

- `status=received|preparing|ready|delivered|served|cancelled|all`
- `mine=1`: yalnızca aktif garsonun adisyonları
- `limit=100`: en fazla 200

Mutfak ürünü hazır durumuna getirdikten sonra garson aşağıdaki istekle ürünün masaya teslim edildiğini işaretleyebilir:

```text
POST /kitchen/items/{itemId}/served
```

## 6. Ödemeler

- `GET /orders/{orderId}/payments`: toplam, ödenen, kalan ve ödeme listesi
- `POST /orders/{orderId}/payments`: kısmi veya tam ödeme alır

```json
{
  "client_reference": "7388c1df-c596-460a-a8ab-aaaf687fdffe",
  "method": "kredi_karti",
  "amount": 250.50
}
```

`amount` gönderilmezse kalan bakiyenin tamamı alınır. Desteklenen yöntemler:

```text
nakit, kredi_karti, yemek_karti
```

Ödemede de `client_reference` aynı ağ isteğinin tekrarında değiştirilmemelidir. Kalan bakiye sıfırlandığında adisyon kapanır ve masa otomatik olarak `available` durumuna geçer.

## Flutter/Dio kısa örnek

```dart
final dio = Dio(BaseOptions(
  baseUrl: '$serverUrl/api/v1/waiter',
  headers: {'Accept': 'application/json'},
));

dio.options.headers['Authorization'] = 'Bearer $accessToken';

final response = await dio.get('/halls');
final halls = response.data['data'] as List<dynamic>;
```

Token Android Keystore veya iOS Keychain kullanan `flutter_secure_storage` benzeri güvenli bir alanda saklanmalıdır.

## Sunucu Reverb yapılandırması

Üretimde aşağıdaki değerler gerçek WebSocket alan adına göre tanımlanmalıdır:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=benzersiz-uygulama-id
REVERB_APP_KEY=genel-uygulama-anahtari
REVERB_APP_SECRET=gizli-imzalama-anahtari
REVERB_HOST=adisyon.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=*
REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM=none
```

`REVERB_APP_SECRET` Flutter uygulamasına kesinlikle yazılmaz. Docker imajında Reverb Supervisor ile otomatik çalışır ve Nginx `/app` ile `/apps` yollarını dahili `8080` portuna yönlendirir. Docker dışı kurulumda süreç yöneticisi altında şu komut sürekli çalışmalıdır:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```
