# Shopify Sync — Canlı Test Dokümantasyonu

Bu doküman `tests/ShopifySync` altındaki **canlı Shopify API** testlerinin nasıl çalıştırılacağını açıklar.

> **Uyarı:** Bu testler gerçek bir Shopify mağazasına istek atar. Production mağazada çalıştırmayın; sandbox veya geliştirme token kullanın.

## Ön koşullar

1. `.env` dosyasında Shopify ayarları:

```env
SHOPIFY_ACCESS_TOKEN=shpat_...
SHOPIFY_STORE_DOMAIN=https://your-dev-store.myshopify.com/
SHOPIFY_API_VERSION=2026-07
SHOPIFY_LIVE_TESTS=true
SHOPIFY_TEST_TAG=SYNC_TEST
```

2. Mağaza token'ının test senaryoları için yeterli scope'u olmalı (ürün, varyant, koleksiyon, envanter okuma/yazma).

3. Altınkaynak kurları sync için ayrıca yapılandırılır (canlı Shopify testlerinden bağımsız):

```env
ALTINKAYNAK_CURRENCY_URL=https://static.altinkaynak.com/public/Currency
ALTINKAYNAK_GOLD_URL=https://static.altinkaynak.com/public/Gold
```

Kaynak: [Altınkaynak Web Servisleri](https://www.altinkaynak.com/Araclar/Servisler)

## Test yapısı

```
tests/ShopifySync/
├── LiveTestCase.php              # SHOPIFY_LIVE_TESTS kontrolü, graphql helper
├── Support/
│   └── ShopifyTestData.php     # SKU üretimi, test ürün verisi, assert yardımcıları
├── ImportStartupSyncTest.php     # 01 — allProducts vs total_product_count
├── CreateSimpleFullTest.php      # 02
├── CreateSimpleWithMediaCollectionTest.php  # 03
├── CreateVariableV3FullTest.php  # 04
├── CreateVariableWithMediaAttachTest.php    # 05
├── UpdateSimpleStockAndPriceTest.php        # 07
├── UpdateMultiVariantStockAndPriceTest.php  # 08
├── UpdateVariablePerSkuTest.php             # 09
├── UpdateTagsAndMetafieldsTest.php          # 10
├── UpdateInventoryFieldsTest.php            # 11
├── DeleteVariantAndProductTest.php          # 12
├── DeleteScanDryRunTest.php                 # 13
├── CollectionSyncLiveTest.php               # 14
├── CollectionMembershipSyncTest.php         # 15
└── CollectionListCountCompareTest.php       # 16
```

Tüm canlı testler `@group live` ile işaretlenir. `SHOPIFY_LIVE_TESTS=false` iken otomatik **skip** edilir.

## Komutlar

### Varsayılan (canlı testler atlanır)

```bash
php artisan test tests/ShopifySync
```

### Tüm canlı testler

```bash
SHOPIFY_LIVE_TESTS=true php artisan test --group=live
```

veya

```bash
php artisan shopify:sync:test
```

### Tek senaryo

```bash
SHOPIFY_LIVE_TESTS=true php artisan test --filter=ImportStartupSyncTest
```

veya

```bash
php artisan shopify:sync:test ImportStartupSyncTest
```

## Test ürünü seed komutu (`shopify:seed-products`)

Canlı PHPUnit senaryolarından bağımsız olarak, Shopify mağazasına toplu **test ürünü** eklemek için Artisan komutu vardır. Ürünler panelde görünmez; önce `command:shopify` ile yerel DB'ye çekilmesi gerekir.

Kaynak: `app/Console/Commands/ShopifySeedProductsCommand.php`

### Ön koşullar

`.env` içinde geçerli Shopify bilgileri:

```env
SHOPIFY_ACCESS_TOKEN=shpat_...
SHOPIFY_STORE_DOMAIN=https://your-dev-store.myshopify.com/
SHOPIFY_TEST_TAG=SYNC_TEST
SHOPIFY_THROTTLE_SECONDS=1
```

Token'ın ürün oluşturma, varyant, envanter ve yayınlama scope'larına sahip olmalıdır.

### Komut imzası

```bash
php artisan shopify:seed-products [options]
```

| Seçenek | Varsayılan | Açıklama |
|---------|------------|----------|
| `--simple` | `10` | Varyasyonsuz (tek varyantlı) ürün sayısı |
| `--variable` | `10` | Varyasyonlu ürün sayısı |
| `--variants` | `2` | Her varyasyonlu üründeki varyant sayısı (en az 2) |
| `--tag` | `SHOPIFY_TEST_TAG` | Shopify ürün etiketi |

### Örnekler

```bash
# Varsayılan: 10 basit + 10 varyasyonlu (her biri 2 varyant)
php artisan shopify:seed-products

# Sadece 10 basit ürün
php artisan shopify:seed-products --simple=10 --variable=0

# 3 varyasyonlu ürün, her birinde 4 varyant
php artisan shopify:seed-products --simple=0 --variable=3 --variants=4

# Özel etiket
php artisan shopify:seed-products --simple=5 --variable=5 --tag=DEV_SEED
```

### Ne yapar?

**Basit ürün** (`--simple`):

1. Shopify'da ürün oluşturur (başlık: `Seed Simple N`, SKU: `SEED-SIMPLE-{batch}-{n}`)
2. Fiyat, karşılaştırma fiyatı, maliyet, SKU ve stok ayarlar
3. Etiketler: `SHOPIFY_TEST_TAG` + `SEED_SIMPLE`
4. Tüm satış kanallarına yayınlar

**Varyasyonlu ürün** (`--variable`):

1. `Renk` seçeneğiyle N varyantlı ürün oluşturur (GraphQL v3 akışı)
2. Her varyanta ayrı SKU/fiyat/stok verir (SKU: `SEED-VAR-{batch}-{ürün}-{varyant}`)
3. Etiketler: `SHOPIFY_TEST_TAG` + `SEED_VARIABLE`
4. Yayınlar

Ürünler arasında `SHOPIFY_THROTTLE_SECONDS` kadar bekler. Bittiğinde oluşturulan Shopify product ID'lerini listeler.

### Panelde görünmesi

Seed komutu yalnızca **Shopify** tarafına yazar. Web paneli (`/products`) yerel veritabanını okur:

```bash
php artisan shopify:seed-products --simple=10 --variable=0
php artisan command:shopify
```

Sync sonrası ürünler `products` tablosuna düşer. Seed ürünlerini filtrelemek için arama kutusuna `SEED-SIMPLE` veya `SEED-VAR` yazın.

### PHPUnit testleri ile fark

| | `shopify:seed-products` | `tests/ShopifySync` |
|--|-------------------------|---------------------|
| Amaç | Manuel toplu ürün ekleme | Otomatik senaryo doğrulama |
| Temizlik | Ürünler mağazada kalır | `finally` ile silinir |
| `SHOPIFY_LIVE_TESTS` | Gerekmez | `true` olmalı |
| SKU öneki | `SEED-SIMPLE-` / `SEED-VAR-` | `TEST-SYNC-` |

> **Uyarı:** Production mağazada kullanmayın. Oluşturulan ürünler otomatik silinmez; gerekirse Shopify admin'den veya GraphQL ile temizleyin.

### Sadece birim testler (ağ gerektirmez)

```bash
php artisan test tests/Unit/ShopifySync
```

## Senaryo özeti

| # | Test sınıfı | Ne doğrular |
|---|-------------|-------------|
| 01 | `ImportStartupSyncTest` | `allProducts()` sayısı = `total_product_count()`; koleksiyon listesi/sayısı |
| 02 | `CreateSimpleFullTest` | Basit ürün oluşturma, fiyat/stok güncelleme, doğrulama |
| 03 | `CreateSimpleWithMediaCollectionTest` | Medya + koleksiyon üyeliği |
| 04 | `CreateVariableV3FullTest` | Varyasyonlu ürün (v3 API) |
| 05 | `CreateVariableWithMediaAttachTest` | Varyanta medya bağlama |
| 07 | `UpdateSimpleStockAndPriceTest` | `update_multiple_variation_prices` yolu (sync cron'un kullandığı) |
| 08 | `UpdateMultiVariantStockAndPriceTest` | Çoklu varyant fiyat/stok |
| 09 | `UpdateVariablePerSkuTest` | SKU bazlı tek varyant güncelleme |
| 10 | `UpdateTagsAndMetafieldsTest` | Tag ve metafield mutasyonları |
| 11 | `UpdateInventoryFieldsTest` | Barkod, maliyet, SKU alanları |
| 12 | `DeleteVariantAndProductTest` | Varyant silme |
| 13 | `DeleteScanDryRunTest` | Ürün listesi okuma (silme yok) |
| 14 | `CollectionSyncLiveTest` | `getAllCollections`, `CollectionSyncService`, DB kaydı |
| 15 | `CollectionMembershipSyncTest` | Koleksiyon ekleme/çıkarma, `product_list` |
| 16 | `CollectionListCountCompareTest` | `collectionsCount` vs `getAllCollections` tutarlılığı |

## Önerilen doğrulama sırası

1. **01** — GraphQL katmanı ve sayım
2. **07** — Fiyat push (asıl sync yolu)
3. **14–16** — Koleksiyon senkronu
4. `php artisan command:shopify` — Tam end-to-end sync (sandbox)

## Test ürünleri ve temizlik

- SKU formatı: `TEST-SYNC-{timestamp}-{suffix}`
- Oluşturulan ürünler `finally` bloğunda `product_delete` ile silinir
- Oluşturulan koleksiyonlar `collectionDelete` ile silinir
- Debug yanıtları: `storage/app/shopify-sync/responses/` (gitignore)

## Sorun giderme

| Belirti | Olası neden | Çözüm |
|---------|-------------|--------|
| Tüm testler skip | `SHOPIFY_LIVE_TESTS=false` | `.env` içinde `true` yapın |
| 401 / Unauthorized | Geçersiz token | Admin API access token kontrol edin |
| GraphQL version hatası | API sürümü uyumsuz | `SHOPIFY_API_VERSION` güncelleyin |
| Koleksiyon sayısı uyuşmuyor | Shopify indeks gecikmesi | Test 16'da 2 sn bekleme var; tekrar deneyin |
| Rate güncellenmiyor | Altınkaynak erişimi | `ALTINKAYNAK_*` URL'lerini kontrol edin |

## İlgili dosyalar

- Sync orchestrator: `app/Lib/ShopifySync/SyncCron.php`
- GraphQL client: `app/Lib/ShopifySync/GraphqlClient.php`
- Kur servisi: `app/Lib/ShopifySync/RateService.php`
- Test ürünü seed: `app/Console/Commands/ShopifySeedProductsCommand.php`
- Config: `config/shopify.php`
