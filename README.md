# İkas Currency — Kuyumcu Fiyat Senkronizasyonu

Altınkaynak kurları ve panelde tanımladığınız maliyet/kar kurallarına göre İkas mağazanızdaki ürün fiyatlarını otomatik güncelleyen Laravel uygulaması.

## Kurulum

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

## İkas yapılandırması

```env
IKAS_CLIENT_ID=
IKAS_CLIENT_SECRET=
IKAS_STORE_DOMAIN=https://your-store.myikas.com/
IKAS_APP_MODE=DEV
IKAS_LIVE_TESTS=false
IKAS_TEST_TAG=SYNC_TEST
IKAS_THROTTLE_SECONDS=1
IKAS_DEFAULT_PRICE_TYPE=TL
```

OAuth token otomatik alınır (`api.myikas.com/api/admin/oauth/token`). GraphQL istekleri `api.myikas.com/api/v2/admin/graphql` üzerinden gider.

## Sync

```bash
php artisan command:ikas
```

Zamanlayıcı: her dakika (`storage/logs/ikas.log`).

## Test

```bash
php artisan test tests/Unit/IkasSync
IKAS_LIVE_TESTS=true php artisan ikas:sync:test
```

Canlı test rehberi: [docs/IKAS_SYNC_TESTS.md](docs/IKAS_SYNC_TESTS.md)

## Mimari

```
command:ikas → SyncCron
  ├── RateService (Altınkaynak)
  ├── CategorySyncService
  ├── ProductSyncService
  └── IkasProductGraphQL
```

Kaynak: `app/Lib/IkasSync/`
