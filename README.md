# Shopify Currency — Kuyumcu Fiyat Senkronizasyonu

Altınkaynak kurları ve panelde tanımladığınız maliyet/kar kurallarına göre Shopify mağazanızdaki ürün fiyatlarını otomatik güncelleyen Laravel uygulaması.

Bu proje **tedarikçi XML import** değildir; mevcut Shopify ürünlerinin fiyatlarını kuyumcu iş kurallarına göre yönetir.

---

## İçindekiler

1. [Özellikler](#özellikler)
2. [Gereksinimler ve kurulum](#gereksinimler-ve-kurulum)
3. [Ortam değişkenleri](#ortam-değişkenleri)
4. [Çoklu dil (i18n)](#çoklu-dil-i18n)
5. [Sync akışı](#sync-akışı)
6. [Fiyat alanları ve iş kuralları](#fiyat-alanları-ve-iş-kuralları)
7. [Web arayüzü — ekranlar](#web-arayüzü--ekranlar)
8. [Ürün listesi — filtreler ve sütunlar](#ürün-listesi--filtreler-ve-sütunlar)
9. [Rozetler ve görsel işaretler](#rozetler-ve-görsel-işaretler)
10. [Shopify'da silinen ürünler](#shopifyda-silinen-ürünler)
11. [Kur doğrulama ve e-posta bildirimi](#kur-doğrulama-ve-e-posta-bildirimi)
12. [Toplu düzenleme](#toplu-düzenleme)
13. [Kampanyalar](#kampanyalar)
14. [Mimari](#mimari)
15. [Testler](#testler)
16. [Dizin yapısı](#dizin-yapısı)
17. [Sorun giderme](#sorun-giderme)

---

## Özellikler

| Alan | Açıklama |
|------|----------|
| **Kur çekme** | Altınkaynak döviz ve altın JSON API → `public/kurlar.xml` / `public/altin.xml` |
| **Ürün import** | Shopify GraphQL ile ACTIVE ürünleri yerel DB'ye alır veya günceller |
| **Fiyat hesaplama** | Maliyet + kar % + indirim % + fiyat türü (TL/USD/altın vb.) |
| **Fiyat push** | Hesaplanan fiyat Shopify'a GraphQL ile yazılır (koşullu) |
| **Maliyet ayrımı** | `price` = panel maliyeti (manuel); `shopify_price` = mağaza fiyatı (sync) |
| **Sync aç/kapa** | Ürün ve varyant bazında `sync_enabled` |
| **Çoklu fiyat** | `multiple_price=yes` → varyant bazlı maliyet/kar |
| **Silinen ürün** | Shopify'da yoksa `shopify_deleted_at` işaretlenir, kayıt silinmez |
| **Filtreler** | Maliyet, sync, tip, Shopify durumu, işlem bekleyenler vb. |
| **Toplu düzenle** | Seçili ürünlerde toplu alan güncelleme |
| **Kur koruması** | Kurlar geçersiz/eskiyse sync çalışmaz; panelde uyarı bandı |
| **E-posta uyarısı** | Kur hatasında admin'e mail (opsiyonel, throttle'lı) |
| **Çoklu dil** | Türkçe / İngilizce (`APP_LOCALE`, header'da TR \| EN) |
| **Koleksiyon sync** | Shopify koleksiyonları DB'ye senkronize edilir |
| **Seed komutu** | Sandbox'a test ürünü oluşturma (`shopify:seed-products`) |

---

## Gereksinimler ve kurulum

- PHP 8.3+
- Laravel 13
- MySQL / MariaDB
- Shopify Admin API access token
- Composer, Node.js (Vite arayüz derlemesi)

```bash
git clone <repo-url> shopify-currency
cd shopify-currency
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install && npm run build
```

Giriş: `/login` — kayıt varsayılan kapalı (`REGISTRATION_ENABLED=false`).

---

## Ortam değişkenleri

### Uygulama

```env
APP_NAME="Entegrasyonum Fiyat Senkronizasyonu Sistemi"
APP_URL=http://localhost
APP_LOCALE=tr
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=shopify_currency
DB_USERNAME=root
DB_PASSWORD=
```

### Shopify sync

```env
SHOPIFY_ACCESS_TOKEN=shpat_...
SHOPIFY_STORE_DOMAIN=https://your-store.myshopify.com/
SHOPIFY_API_VERSION=2026-07
SHOPIFY_APP_MODE=PROD              # DEV = eşzamanlı sync kilidi kapalı
SHOPIFY_THROTTLE_SECONDS=1         # GraphQL istekleri arası bekleme
SHOPIFY_DEFAULT_PRICE_TYPE=TL      # Yeni ürünlerin varsayılan fiyat türü
SHOPIFY_RATES_MAX_AGE_SECONDS=3600 # Kurlar bu süreden eskiyse uyarı / sync durur
```

### Altınkaynak kurları

[Servis dokümantasyonu](https://www.altinkaynak.com/Araclar/Servisler)

```env
ALTINKAYNAK_CURRENCY_URL=https://static.altinkaynak.com/public/Currency
ALTINKAYNAK_GOLD_URL=https://static.altinkaynak.com/public/Gold
```

Çıktı: `public/kurlar.xml`, `public/altin.xml` (`.gitignore` içinde, sync ile oluşur).

### Kur hatası e-posta bildirimi

```env
ADMIN_MAIL_NOTIFICATIONS_ENABLED=false
ADMIN_EMAIL=admin@ornek.com
ADMIN_MAIL_THROTTLE_SECONDS=300    # Aynı uyarı en fazla 5 dk'da bir mail
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_FROM_ADDRESS=...
```

### Oturum süresi

```env
# Hareketsiz kalınca oturum düşer (dakika) — Laravel varsayılanı
SESSION_LIFETIME=120

# Girişten X dakika sonra zorunlu çıkış (hareket etse bile). 0 = kapalı
SESSION_ABSOLUTE_LIFETIME=0
```

| Değişken | Davranış |
|----------|----------|
| `SESSION_LIFETIME` | Son istekten bu kadar dakika sonra oturum silinir → tekrar giriş |
| `SESSION_ABSOLUTE_LIFETIME` | Giriş anından itibaren üst sınır; dolunca çıkış + “Oturum süreniz doldu” mesajı |

Örnek: `SESSION_LIFETIME=60` ve `SESSION_ABSOLUTE_LIFETIME=480` → 1 saat işlem yoksa veya en geç 8 saat sonra yeniden giriş gerekir.

### Arama motoru engelleme

```env
BLOCK_SEARCH_ENGINE_BOTS=true   # Googlebot, Bingbot vb. → 403
SITE_NOINDEX=true               # Meta robots + X-Robots-Tag başlığı
```

`public/robots.txt` tüm siteyi `Disallow: /` ile kapatır.

### HTTP rate limit

```env
RATE_LIMIT_ENABLED=true
RATE_LIMIT_PER_MINUTE=120          # Tüm web sayfaları (IP veya oturumlu kullanıcı)
AUTH_RATE_LIMIT_PER_MINUTE=20      # login, register, şifre sıfırlama POST
LOGIN_MAX_ATTEMPTS=5               # Yanlış şifre denemesi (e-posta + IP)
LOGIN_DECAY_MINUTES=1              # Giriş kilidi (dakika)
CRON_REFRESH_RATE_LIMIT=10         # /cron-refresh
CRON_SECRET=uzun-rastgele-token    # ?token=... zorunlu; boşsa endpoint kapalı
```

Limit aşılınca **429** özel hata sayfası gösterilir. `RATE_LIMIT_ENABLED=false` ile kapatılır.

### Endpoint güvenliği

| Uç | Koruma |
|----|--------|
| `/products`, `/campaigns`, bulk-update | `auth` + `verified` |
| `/profile`, şifre güncelleme | `auth` |
| `/login`, şifre sıfırlama | `guest` + rate limit |
| `/cron-refresh` | `CRON_SECRET` token + rate limit (auth değil) |
| `/locale/{locale}` | Herkese açık (dil seçimi) |

Sync için sunucuda `php artisan schedule:run` kullanın; `/test` kaldırıldı.

### Diğer

```env
REGISTRATION_ENABLED=false         # Kapalıyken /register → 404
TURNSTILE_ENABLED=false            # Auth formları (giriş, kayıt, şifre sıfırlama) Cloudflare Turnstile
SHOPIFY_LIVE_TESTS=false
SHOPIFY_TEST_TAG=SYNC_TEST
```

---

## Çoklu dil (i18n)

| Ayar | Açıklama |
|------|----------|
| `APP_LOCALE` | Varsayılan dil (`tr` veya `en`) |
| `APP_FALLBACK_LOCALE` | Çeviri yoksa kullanılan dil |
| Header **TR \| EN** | Oturumda dil değiştirir (`/locale/tr`, `/locale/en`) |

Çeviri dosyaları (`lang/tr/`, `lang/en/`) — PHP dizin yapısı:

```
lang/tr/common.php      Ortak butonlar ve etiketler
lang/tr/products.php    Ürün ekranları
lang/tr/account.php     Giriş, profil, hesap
lang/tr/rates.php       Kur uyarıları
lang/tr/mail.php        E-posta metinleri
lang/tr/navigation.php  Menü
...
```

Kullanım: `__('products.title')`, `__('account.email')`, `__('common.save')`

Yeni metin eklerken hem `lang/tr` hem `lang/en` altındaki ilgili PHP dosyasını güncelleyin.

---

## Sync akışı

Komut: `php artisan command:shopify`  
Zamanlama: `app/Console/Kernel.php` — her dakika (`storage/logs/shopify.log`).

```
┌─────────────────────────────────────────────────────────────┐
│ 1. SyncStatus — çift çalışma kilidi (PROD modunda)          │
├─────────────────────────────────────────────────────────────┤
│ 2. Kurlar — Altınkaynak'tan çek → XML yaz                   │
│    └─ Geçersiz / eski / API hatası → DUR, mail (opsiyonel)  │
├─────────────────────────────────────────────────────────────┤
│ 3. CollectionSyncService — koleksiyonları DB'ye al          │
├─────────────────────────────────────────────────────────────┤
│ 4. Shopify GraphQL — tüm ürünleri listele                   │
├─────────────────────────────────────────────────────────────┤
│ 5. Her ürün için:                                           │
│    • status ≠ ACTIVE → atla                                 │
│    • DB'de var → ProductSyncService::updateExisting         │
│    • DB'de yok → createLocalRecords (maliyet boş)         │
├─────────────────────────────────────────────────────────────┤
│ 6. Shopify'da olmayan DB ürünleri → shopify_deleted_at     │
│    Mağazada tekrar görünenler → işaret kaldır               │
├─────────────────────────────────────────────────────────────┤
│ 7. last_update.txt + app_status.txt = done                  │
└─────────────────────────────────────────────────────────────┘
```

**Manuel çalıştırma:**

```bash
php artisan command:shopify
```

**Cron (sunucu):**

```cron
* * * * * cd /path/to/shopify-currency && php artisan schedule:run >> /dev/null 2>&1
```

---

## Fiyat alanları ve iş kuralları

### Veritabanı — `products`

| Alan | Tür | Açıklama |
|------|-----|----------|
| `name` | string | Shopify ürün adı |
| `sku` | string | İlk varyant SKU |
| `price` | decimal, nullable | **Maliyet** — yalnızca panelden girilir |
| `shopify_price` | decimal, nullable | Mağazadaki satış fiyatı — sync ile güncellenir |
| `price_type` | string | TL, USD, EUR, Has Toptan, Gram Toptan, 22 Ayar Bilezik |
| `profit` | string | Kar oranı % |
| `discount` | string | İndirim (karşılaştırma fiyatı) oranı % |
| `total_price` | string | Hesaplanan satış fiyatı |
| `comparison_price` | string | Hesaplanan karşılaştırma fiyatı |
| `commission` | string | %2 komisyon tutarı |
| `sync_enabled` | bool | Fiyat push açık/kapalı |
| `multiple_price` | enum yes/no | Varyant bazlı fiyatlandırma |
| `shopify_product_id` | string | Shopify GID |
| `shopify_image` | string | Kapak görsel URL |
| `shopify_deleted_at` | timestamp | Mağazada silindi işareti |

### Veritabanı — `variations`

Aynı fiyat alanları + `name`, `shopify_variant_id`. Ürünle `shopify_product_id` üzerinden ilişkilidir.

### Hesaplama formülü (`PricingService`)

1. `total = maliyet + maliyet × kar%`
2. `price_type ≠ TL` ise `total × kur` (Altınkaynak XML'den)
3. `comparison = total + total × indirim%`
4. `commission = total × 2%`
5. Shopify'a push yalnızca fark > 0,01 TL ise

### Sync'te fiyat push koşulları

| Durum | Push |
|-------|------|
| `sync_enabled = false` (ürün) | Hayır |
| Maliyet (`price`) boş | Hayır |
| `multiple_price = yes` | Ürün sync kapalı veya varyant maliyeti/sync kapalıysa o varyant atlanır |
| Basit ürün | Ürün sync açık + maliyet var → push |
| Shopify'da silinmiş (`shopify_deleted_at`) | Güncelleme atlanır; işaret sync ile yönetilir |

### İlk import (`createLocalRecords`)

- `price = null` (maliyet boş)
- `shopify_price` = Shopify variant fiyatı
- `sync_enabled = true`
- `price_type` = `SHOPIFY_DEFAULT_PRICE_TYPE`
- `profit` / `discount` = `1` (varsayılan)

### `multiple_price`

| Değer | Davranış |
|-------|----------|
| `no` | Tek maliyet/kar; tüm varyantlar ürün ayarlarına göre |
| `yes` | Her varyantın kendi maliyet/kar/sync ayarı; ürün düzenlemede maliyet alanları gizlenir |

---

## Web arayüzü — ekranlar

Tüm ekranlar giriş gerektirir (`auth` + `verified`).

### Rotalar

| Rota | Ekran | Açıklama |
|------|-------|----------|
| `GET /products` | Ürün listesi | Filtre, tablo, toplu düzenle |
| `GET /products/{id}` | Ürün detay | Özet + varyant tablosu |
| `GET /products/{id}/edit` | Ürün düzenle | Ürün seviyesi ayarlar |
| `GET /products/{id}/variations/{vid}/edit` | Varyant düzenle | Varyant maliyet/kar |
| `PUT /products/bulk-update` | API | Toplu güncelleme (AJAX) |
| `GET /campaigns` | Kampanyalar | Koleksiyon kampanya ayarları (menüde gizli) |
| `GET /locale/{tr\|en}` | — | Dil değiştir |
| `GET /profile` | Profil | Breeze profil |

---

### 1. Ürün listesi (`/products`)

**Üst bölüm**

- Kur uyarı bandı (kurlar yok / geçersiz / eski) — kırmızı
- Son sync zamanı + USD/EUR/altın kurları (kurlar hazırsa)
- Başlık **Ürünler**
- Buton **Toplu Düzenle** — modal açar

**Filtreler** (varsayılan kapalı; tıklayınca açılır; aktif filtre varsa açık gelir)

| Alan | Seçenekler | Açıklama |
|------|------------|----------|
| **Arama** | metin | Ürün adı, SKU, Shopify ID; virgülle çoklu SKU |
| **Maliyet** | Tümü / Belirlenmemiş / Girilmiş | Basit veya varyant maliyet durumu |
| **Sync** | Tümü / Aktif / Pasif | Ürün veya herhangi varyant sync kapalı |
| **Ürün tipi** | Tümü / Basit / Varyasyonlu | Varyant kaydı varlığı |
| **Çoklu fiyat** | Tümü / Hayır / Evet | `multiple_price` |
| **Fiyat türü** | Tümü / TL / USD / … | `price_type` |
| **Shopify** | Tümü / Mağazada / Silinmiş | `shopify_deleted_at` |
| **İşlem bekleyenler** | checkbox | Maliyet eksik, sync kapalı veya silinmiş |

Butonlar: **Filtrele**, **Temizle**

**Tablo sütunları**

| Sütun | İçerik |
|-------|--------|
| ☑ | Toplu seçim |
| Resim | 40×40 Shopify görseli |
| Ürün | Ad (link), SKU, dikkat rozetleri |
| Tip | Basit / Varyasyonlu (n) |
| Shopify | Mağazada / Shopify'da silindi + tarih |
| Mağaza | `shopify_price` |
| Maliyet | Girilmiş/Eksik rozeti + özet metin |
| Sync | Aktif/Pasif |
| Hesaplanan | `total_price` (basit + maliyet varsa) |
| Aç | Detay sayfası linki |

Silinmiş ürün satırları açık kırmızı arka plan.

---

### 2. Ürün detay (`/products/{id}`)

**Butonlar:** ← Ürün listesi, **Ürünü düzenle**

**Uyarı:** Shopify'da silinmişse kırmızı bilgi bandı.

**Özet kartı:** Mağaza fiyatı, Maliyet, Sync, Çoklu fiyat, Hesaplanan fiyat (basit ürün).

**Varyant tablosu** (varsa): Varyant adı, SKU, Mağaza fiyatı, Maliyet, Sync, **Düzenle** linki.

---

### 3. Ürün düzenle (`/products/{id}/edit`)

**Salt okunur:** Mağaza fiyatı, Tip (rozet)

| Alan | Tip | Açıklama |
|------|-----|----------|
| **Sync** | select | Aktif / Pasif |
| **Çoklu fiyat** | select | Hayır (tüm varyantlar ürün maliyetine göre) / Evet (her varyant ayrı) |
| **Fiyat türü** | select | `config/shopify.price_types` |
| **Maliyet** | text | `price` — boş bırakılabilir |
| **İndirim %** | text | `discount` |
| **Kar %** | text | `profit` |

Çoklu fiyat açık + varyant varsa maliyet/kar alanları gizlenir; varyant listesine yönlendirme metni gösterilir.

**Butonlar:** **Kaydet**, **İptal**

---

### 4. Varyant düzenle (`/products/{id}/variations/{vid}/edit`)

**Salt okunur:** Mağaza fiyatı, Hesaplanan fiyat

| Alan | Tip | Açıklama |
|------|-----|----------|
| **Sync** | select | Aktif / Pasif |
| **Fiyat türü** | select | Varyant `price_type` |
| **Maliyet** | text | Varyant `price` |
| **İndirim %** | text | |
| **Kar %** | text | |

**Butonlar:** **Kaydet**, **İptal**

---

## Rozetler ve görsel işaretler

| Rozet | Renk | Anlam |
|-------|------|-------|
| **Aktif** (sync) | yeşil | Fiyat sync açık |
| **Pasif** (sync) | gri | Fiyat sync kapalı |
| **Girilmiş** (maliyet) | yeşil | Maliyet tanımlı |
| **Eksik** (maliyet) | amber | Maliyet yok |
| **Mağazada** | mavi | Shopify'da mevcut |
| **Shopify'da silindi** | kırmızı | API listesinde yok |
| **Basit** / **Varyasyonlu (n)** | gri / indigo | Ürün tipi |
| **İşlem bekleyen** (sarı) | amber | Maliyet eksik, sync kapalı, silinmiş vb. |

**İşlem bekleyenler** filtresi şunları kapsar: maliyet girilmemiş, ürün/varyant sync kapalı, Shopify'da silinmiş.

---

## Shopify'da silinen ürünler

- Sync sırasında Shopify API listesinde olmayan ürünler **silinmez**
- `shopify_deleted_at` doldurulur, `sync_enabled = false` yapılır
- Liste: kırmızı satır + **Silinmiş** rozeti
- Filtre: **Shopify → Silinmiş**
- Mağazada tekrar bulunursa işaret kaldırılır
- Log: `storage/logs/delete-products.log`

---

## Kur doğrulama ve e-posta bildirimi

### Panel (`/products`)

Kurlar hazır değilse üstte kırmızı uyarı bandı; kur satırı gizlenir.

Kontroller (`RateService::inspectRatesForUi`):

1. `kurlar.xml` / `altin.xml` dosyaları var mı?
2. XML geçerli mi, en az bir kur kaydı var mı?
3. Son güncelleme `SHOPIFY_RATES_MAX_AGE_SECONDS` içinde mi? (varsayılan 1 saat)

### Sync

Aynı kontroller sync başında yapılır. Başarısızsa:

- Ürün/koleksiyon sync **çalışmaz**
- Komut `FAILURE` ile çıkar
- `ADMIN_MAIL_NOTIFICATIONS_ENABLED=true` + `ADMIN_EMAIL` ise e-posta (en fazla `ADMIN_MAIL_THROTTLE_SECONDS` aralıkla)

---

## Toplu düzenleme

**Buton:** Ürün listesinde **Toplu Düzenle**

1. Tabloda ürünleri checkbox ile seç
2. Modalda doldurulacak alanlar (boş = değişmez):

| Alan | Açıklama |
|------|----------|
| Çoklu fiyat | Hayır / Evet |
| Sync | Aktif / Pasif |
| Fiyat türü | TL, USD, … |
| Maliyet | Ürün `price` |
| İndirim oranı | `discount` |
| Kar oranı | `profit` |

**Butonlar:** **Güncelle**, **Kapat**

---

## Kampanyalar

- Rota: `GET /campaigns` — header menüsünde **şimdilik gizli** (yorum satırı)
- Koleksiyon bazlı indirim/kar oranı düzenleme
- Tablo: Aktif mi?, Koleksiyon adı, İndirim oranı, Kar oranı, **Güncelle**

---

## Mimari

```
command:shopify
    └── SyncCron
            ├── RatesFailureNotifier   → Kur hatası e-postası
            ├── RateService            → Altınkaynak → kurlar.xml / altin.xml
            ├── CollectionSyncService  → Shopify koleksiyonları → DB
            ├── ProductSyncService     → Import, fiyat hesaplama, push kuralları
            ├── PricingService         → Kar / indirim / kur formülü
            ├── ShopifyProductGraphQL  → GraphQL sorgu/mutasyon
            └── SyncStatus             → Kilitleme, index.txt, last_update.txt
```

Kaynak: `app/Lib/ShopifySync/`

### Test ürünü seed

```bash
php artisan shopify:seed-products --simple=10 --variable=2
php artisan command:shopify   # panele düşmesi için
```

Detay: [docs/SHOPIFY_SYNC_TESTS.md](docs/SHOPIFY_SYNC_TESTS.md)

> **Uyarı:** Seed gerçek Shopify mağazasına ürün yazar.

---

## Testler

### Birim

```bash
php artisan test tests/Unit
```

Örnek: `RateServiceTest`, `ProductShopifyDeletedFilterTest`, `RatesFailureNotifierTest`

### Canlı Shopify

```bash
SHOPIFY_LIVE_TESTS=true php artisan test --group=live
```

Rehber: [docs/SHOPIFY_SYNC_TESTS.md](docs/SHOPIFY_SYNC_TESTS.md)

---

## Dizin yapısı

```
app/
  Http/Controllers/ProductController.php   Panel API + sayfalar
  Lib/ShopifySync/                         Sync motoru
  Mail/RatesSyncBlockedMail.php
  Models/Product.php, Variation.php
  Console/Commands/
    ShopifyCron.php                        command:shopify
    ShopifySeedProductsCommand.php
config/shopify.php
lang/tr/, lang/en/                         Çeviriler
resources/views/products/                  Liste, detay, düzenle
public/kurlar.xml, altin.xml               Kurlar (sync, gitignore)
public/last_update.txt                     Son sync aralığı
storage/app/shopify-sync/                  Durum + debug JSON
storage/logs/shopify.log                   Zamanlanmış sync çıktısı
storage/logs/delete-products.log           Silinen ürün ID'leri
docs/SHOPIFY_SYNC_TESTS.md
```

---

## Sorun giderme

| Sorun | Kontrol |
|-------|---------|
| Sync başlamıyor / "already running" | `storage/app/shopify-sync/app_status.txt` veya `SHOPIFY_APP_MODE=DEV` |
| Sync hemen duruyor | Kurlar — `storage/logs/laravel.log`, Altınkaynak URL'leri |
| Panelde kur uyarısı | `public/kurlar.xml` / `altin.xml`; sync çalıştırın |
| Fiyat Shopify'a gitmiyor | Maliyet girili mi? `sync_enabled` açık mı? `shopify_deleted_at` null mı? |
| Ürün listede silinmiş görünüyor | Shopify'da gerçekten silinmiş; panel kaydı korunur |
| E-posta gitmiyor | `ADMIN_MAIL_NOTIFICATIONS_ENABLED`, `ADMIN_EMAIL`, `MAIL_*` |
| Çok mail geliyor | `ADMIN_MAIL_THROTTLE_SECONDS` (varsayılan 300) |
| Shopify 401 | `SHOPIFY_ACCESS_TOKEN`, mağaza domain |
| Dil değişmiyor | `APP_LOCALE`, session, `/locale/tr` linki |

---

## Lisans

Proje lisansı için `LICENSE` dosyasına bakın.
