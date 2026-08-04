# Flutter Waiter API v1

Base URL:

```text
https://SUNUCU_ADRESI/api/v1/waiter
```

Required headers for every request:

```http
Accept: application/json
Content-Type: application/json
```

After login, also send:

```http
Authorization: Bearer wtr_...
```

## Realtime and sync

REST carries mutations from Flutter to the server. Changes created by web POS, cashier, kitchen, admin, sync jobs or another mobile device are pushed back to Flutter through Laravel Reverb.

Realtime config:

```text
GET /realtime/config
```

The response includes:

- `app_key`
- `host`
- `port`
- `scheme`
- `channel`
- `event`
- `events`
- `auth_endpoint`
- `fallback_sync`
- `server_time`

Flutter subscribes only to its own branch channel:

```text
private-waiter.branch.{branchId}
```

Channel auth:

```http
POST /api/v1/waiter/realtime/auth
Authorization: Bearer wtr_...
Content-Type: application/json

{
  "socket_id": "1234.5678",
  "channel_name": "private-waiter.branch.42"
}
```

WebSocket URL:

```text
wss://{host}:{port}/app/{app_key}?protocol=7&client=flutter&version=1.0&flash=false
```

Generic event example:

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

Specialized table event:

```json
{
  "table_id": 12,
  "status": "occupied",
  "is_locked": true,
  "locked_by": "cashier",
  "emitted_at": "2026-08-04T14:05:10+03:00"
}
```

Specialized kitchen item event:

```json
{
  "order_id": 105,
  "table_name": "Masa 4",
  "item_id": 88,
  "status": "ready",
  "emitted_at": "2026-08-04T14:05:15+03:00"
}
```

Suggested client behavior:

1. Connect WebSocket and read `socket_id` from `pusher:connection_established`.
2. Send `socket_id` and `channel_name` to `/realtime/auth`.
3. Send `pusher:subscribe` with the returned `auth`.
4. Listen to `waiter.updated`, `table.status.updated` and `kitchen.item.status.updated`.
5. Reply `pusher:pong` when `pusher:ping` arrives.
6. On reconnect, run fallback REST sync and refresh the stored `server_time`.

Topic to REST mapping:

- `tables` -> `GET /halls`
- `menu` -> `GET /categories`
- `orders` -> `GET /orders/{references.order_id}` or delta order list
- `kitchen` -> `GET /kitchen/updates?since={last_server_time}`
- `payments` -> `GET /orders/{references.order_id}/payments`

Realtime events are not a durable queue. If the phone sleeps or disconnects, events may be missed. REST remains the source of truth.

## 1. Auth

### Fetch staff profiles

`POST /auth/profiles`

```json
{
  "restaurant_id": "REST-0001",
  "password": "restoran-sifresi"
}
```

### Login

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

Store `data.access_token` in secure storage. Default token lifetime is 30 days and can be changed with `WAITER_API_TOKEN_TTL_MINUTES`.

Other auth endpoints:

- `GET /auth/me`
- `POST /auth/logout`

## 2. Halls and tables

Endpoints:

- `GET /halls`
- `GET /tables`
- `GET /tables?hall_id=1&status=available`
- `GET /tables/{tableId}`

Table statuses:

```text
available, occupied, awaiting_payment, reserved
```

Every table payload now includes lock metadata:

```json
{
  "id": 12,
  "name": "Masa 4",
  "status": "occupied",
  "is_locked": true,
  "locked_by": "cashier",
  "locked_at": "2026-08-04T14:00:00+03:00",
  "lock_expires_at": "2026-08-04T14:03:00+03:00",
  "current_order_total": 450.0
}
```

If `is_locked=true`, Flutter must treat the table as cashier-owned and block local mutations on that table.

Cashier/web POS endpoints:

- `POST /api/v1/waiter/tables/{tableId}/lock`
- `POST /api/v1/waiter/tables/{tableId}/unlock`

These endpoints are for restaurant web sessions, not for the waiter mobile app. Flutter should consume the resulting lock state and realtime events instead of calling them.

## 3. Categories and menu

Endpoints:

- `GET /categories`
- `GET /products`
- `GET /products?category_id=3`
- `GET /products?search=kahve&available_only=1`

Prices are returned as JSON numbers. `is_available` also reflects stock when stock tracking is enabled.

## 4. Orders

### List orders

```text
GET /orders?status=active&scope=mine
GET /orders?updated_after=2026-08-01T12:00:00Z
GET /orders/{orderId}
```

`status`: `active`, `open`, `awaiting_payment`, `closed`, `cancelled`

`scope`: `all` or `mine`

### Open a new order

`POST /orders`

```json
{
  "client_reference": "0f8526fc-66c9-4c61-9890-1f40df4e7af4",
  "dining_table_id": 25,
  "guest_count": 3,
  "customer_notes": "Fistik alerjisi var",
  "items": [
    {
      "product_id": 91,
      "quantity": 2,
      "notes": "Sogansiz"
    }
  ]
}
```

`client_reference` must be generated once by Flutter and reused for retries. This makes create requests idempotent.

### Add items

`POST /orders/{orderId}/items`

```json
{
  "items": [
    {
      "product_id": 91,
      "quantity": 1,
      "notes": "Acisiz"
    }
  ]
}
```

Other order mutations:

- `DELETE /orders/{orderId}/items/{itemId}`
- `PATCH /orders/{orderId}/notes`
- `POST /orders/{orderId}/send-kitchen`
- `POST /orders/{orderId}/request-payment`

### Locked table conflict

If cashier has locked the table, order mutation endpoints return `409 Conflict`:

```json
{
  "success": false,
  "message": "Bu masayla kasa islem yapmaktadir.",
  "code": "TABLE_LOCKED"
}
```

Flutter should not auto-retry this case. The UI should mark the table as locked and prevent further edits until the lock disappears.

## 5. Kitchen and service updates

`GET /kitchen/updates`

Fallback flow:

1. First request without `since`.
2. Store `meta.server_time`.
3. Repeat with `since={stored_server_time}` after `meta.poll_after_seconds`.
4. Replace stored time with each new `meta.server_time`.

Example:

```text
GET /kitchen/updates?since=2026-08-01T12:00:00Z&mine=1
```

Filters:

- `status=received|preparing|ready|delivered|served|cancelled|all`
- `mine=1`
- `limit=100`

When a waiter physically serves a ready item:

```text
POST /kitchen/items/{itemId}/served
```

Specialized realtime payload `kitchen.item.status.updated` is emitted when kitchen marks an item as `ready` or `delivered`.

## 6. Payments

Endpoints:

- `GET /orders/{orderId}/payments`
- `POST /orders/{orderId}/payments`

```json
{
  "client_reference": "7388c1df-c596-460a-a8ab-aaaf687fdffe",
  "method": "kredi_karti",
  "amount": 250.50
}
```

If `amount` is omitted, the whole remaining balance is charged.

Supported methods:

```text
nakit, kredi_karti, yemek_karti
```

`client_reference` must also stay stable across retries for payment idempotency.

When the remaining balance reaches zero, the order closes and the table automatically becomes `available`.

Payment mutations also enforce table locks. If cashier is already operating on the same table, the endpoint returns `409 / TABLE_LOCKED`.

## Flutter / Dio quick example

```dart
final dio = Dio(BaseOptions(
  baseUrl: '$serverUrl/api/v1/waiter',
  headers: {'Accept': 'application/json'},
));

dio.options.headers['Authorization'] = 'Bearer $accessToken';

final response = await dio.get('/halls');
final halls = response.data['data'] as List<dynamic>;
```

Store tokens in Android Keystore / iOS Keychain via a secure storage package.

## Reverb server config

Production example:

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
ADISYON_TABLE_LOCK_TTL_SECONDS=180
```

`REVERB_APP_SECRET` must never be embedded in Flutter. Reverb should run continuously under a process manager:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```
