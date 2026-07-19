# İkas Sync — Canlı Test Dokümantasyonu

Bu doküman `tests/IkasSync` altındaki **canlı İkas API** testlerinin nasıl çalıştırılacağını açıklar.

> **Uyarı:** Bu testler gerçek bir İkas mağazasına istek atar. Production mağazada çalıştırmayın; geliştirme mağazası ve private app kullanın.

## Ön koşullar

`.env` dosyasında:

```env
IKAS_CLIENT_ID=
IKAS_CLIENT_SECRET=
IKAS_STORE_DOMAIN=https://dev-your-store.myikas.com/
IKAS_APP_MODE=DEV
IKAS_LIVE_TESTS=true
IKAS_TEST_TAG=SYNC_TEST
```

## Komutlar

```bash
# Varsayılan (canlı testler atlanır)
php artisan test tests/IkasSync

# Tüm canlı testler
IKAS_LIVE_TESTS=true php artisan test --group=live

# veya
php artisan ikas:sync:test

# Tek senaryo
php artisan ikas:sync:test UpdateSimpleStockAndPriceTest
```

## Test senaryoları

| # | Sınıf | Açıklama |
|---|-------|----------|
| 01 | ImportStartupSyncTest | Ürün/kategori sayım doğrulama |
| 02 | CreateSimpleFullTest | Basit ürün + fiyat |
| 03 | CreateSimpleWithMediaCategoryTest | Görsel + kategori |
| 04 | CreateVariableFullTest | Varyantlı ürün |
| 05 | CreateVariableWithMediaAttachTest | Varyant görseli |
| 07 | UpdateSimpleStockAndPriceTest | Stok + fiyat güncelleme |
| 08 | UpdateMultiVariantStockAndPriceTest | Çoklu varyant |
| 09 | UpdateVariablePerSkuTest | SKU bazlı güncelleme |
| 10 | UpdateTagsTest | Etiket güncelleme |
| 11 | UpdateInventoryFieldsTest | SKU/maliyet alanları |
| 12 | DeleteVariantAndProductTest | Silme |
| 13 | DeleteScanDryRunTest | Salt okuma tarama |
| 14 | CategorySyncLiveTest | CategorySyncService |
| 15 | CategoryMembershipSyncTest | Kategori üyeliği |
| 16 | CategoryListCountCompareTest | Kategori sayımı |

Test verileri `TEST-SYNC-{timestamp}` SKU öneki ile oluşturulur ve `finally` bloklarında temizlenir.
