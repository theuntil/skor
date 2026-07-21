# Deploy Rehberi

Bu rehber, projeyi **kendi sunucunda**, kendi domain'inle, kendi
key/token'larınla nasıl ayağa kaldıracağını anlatır. İki popüler self-hosted
PaaS için adımlar var: **Dokploy** ve **Coolify**. Hangisini kullanıyorsan
o bölüme geç.

Her iki platform da aynı `Dockerfile`'ı kullanır, tek fark panel arayüzü.

---

## 0) Ortak Ön Hazırlık

1. Bu repoyu kendi GitHub/GitLab hesabına fork'la (ya da kendi reponu
   oluşturup içeriği push'la).
2. Aşağıdaki değerleri **şimdiden belirle**, deploy sırasında bunları
   env variable olarak gireceksin:
   - **API_KEY**: mobil app'inin/frontend'inin API'na erişmek için
     kullanacağı gizli anahtar. Rastgele, uzun bir string üret (örn. bir
     şifre üreticiyle 32+ karakter).
   - **REFRESH_TOKEN**: manuel veri yenileme endpoint'i (`refresh.php`)
     için ayrı bir gizli değer.
   - **CRON_SCHEDULE**: veri ne sıklıkla güncellensin (opsiyonel,
     varsayılan 3 saatte bir).

> Bu iki değeri (API_KEY, REFRESH_TOKEN) **asla** kod içine yazma, sadece
> platformun "Environment Variables" panelinden gir.

---

## A) Dokploy ile Deploy

### A.1) Uygulama oluştur

1. Dokploy panelinde **Create Application** → **Docker (Dockerfile)** seç.
2. Git provider'ını seç (GitHub/GitLab/vb.), repo'nu bağla, branch seç.
3. **Build Type:** `Dockerfile`
4. **Docker Context Path:** `.`
5. **Dockerfile Path:** `Dockerfile`
6. **Build Stage:** boş bırak (bu Dockerfile tek aşamalı, multi-stage değil)
7. **Autodeploy:** `On Push` seçersen her `git push`'ta otomatik yeniden deploy olur.

### A.2) Port

Container içi port **`80`** olmalı (Dockerfile'da `EXPOSE 80`, Apache orada dinliyor).

### A.3) Environment Variables

Dokploy panelinde **Environment** sekmesine şunları gir:

| Değişken | Değer |
|---|---|
| `API_KEY` | (senin belirlediğin gizli değer) |
| `REFRESH_TOKEN` | (senin belirlediğin gizli değer) |
| `CRON_SCHEDULE` | `0 */3 * * *` (veya istediğin sıklık) |
| `RATE_LIMIT_MAX` | `60` (opsiyonel, dakika başı istek limiti) |
| `RATE_LIMIT_WINDOW` | `60` (opsiyonel, saniye) |

### A.4) Persistent Volume (ÇOK ÖNEMLİ)

**Volumes** sekmesinden:
- Container path: `/var/www/html/data`
- Bir named volume veya host path bağla.

Bunu atlarsan her deploy'da scrape edilmiş veri sıfırlanır.

### A.5) Domain + SSL

**Domains** sekmesinden kendi domain'ini bağla. Dokploy otomatik Let's
Encrypt sertifikası çıkarır.

### A.6) Doğrulama

```bash
curl "https://senin-domainin.com/api.php?health=1"
```

---

## B) Coolify ile Deploy

### B.1) Uygulama oluştur

1. Coolify panelinde **Add New Resource** → **Public Repository** (veya
   private repo'n varsa GitHub App entegrasyonunu kullan).
2. Repo URL'ini gir, branch seç.
3. **Build Pack:** Coolify genelde repo kökündeki `Dockerfile`'ı otomatik
   algılar (Dockerfile-based deployment). Algılamazsa build pack olarak
   manuel **Dockerfile** seç.
4. **Ports Exposes:** `80`

### B.2) Environment Variables

Uygulamanın **Environment Variables** sekmesine gir, şunları ekle:

| Değişken | Değer | Not |
|---|---|---|
| `API_KEY` | (senin gizli değerin) | "Runtime" olarak işaretle, "Build Variable" olarak **işaretleme** — build image'ına gömülmesin |
| `REFRESH_TOKEN` | (senin gizli değerin) | Aynı şekilde runtime |
| `CRON_SCHEDULE` | `0 */3 * * *` | |
| `RATE_LIMIT_MAX` | `60` | |
| `RATE_LIMIT_WINDOW` | `60` | |

Coolify'da "Build Variable" kapalı bırakmak önemli: açık olursa key,
image metadata'sına gömülür ve image'a erişimi olan herkes görebilir.
Sadece container çalışırken okunması yeterli olduğu için runtime yeterli.

### B.3) Persistent Storage

Uygulamanın **Storages** (Persistent Volumes) sekmesinden:
- **Source Path:** Coolify otomatik bir host path önerir, değiştirebilirsin.
- **Destination Path (container):** `/var/www/html/data`

Bunu eklemezsen her deploy'da veri sıfırlanır.

### B.4) Domain + SSL

**Domains** sekmesinden domain'ini bağla (`https://senin-domainin.com`
formatında gir). Coolify, Traefik üzerinden otomatik Let's Encrypt
sertifikası çıkarır.

### B.5) Deploy

**Deploy** butonuna bas. Build loglarını takip et, hata olursa (örn. eksik
sistem paketi) log çıktısını incele.

### B.6) Doğrulama

```bash
curl "https://senin-domainin.com/api.php?health=1"
```

---

## Ortam Değişkenleri

| Değişken | Varsayılan | Zorunlu mu | Açıklama |
|---|---|---|---|
| `CRON_SCHEDULE` | `0 */3 * * *` | Hayır | Scraping cron zamanlaması (standart cron syntax'ı) |
| `API_KEY` | (boş) | **Evet (prod)** | `api.php` erişim key'i. Boş bırakılırsa API tamamen açık/korumasız çalışır |
| `REFRESH_TOKEN` | (boş) | **Evet (prod)** | `refresh.php` erişim token'ı. Boş bırakılırsa herkes tetikleyebilir |
| `RATE_LIMIT_MAX` | `60` | Hayır | Pencere başına IP başına izin verilen istek sayısı |
| `RATE_LIMIT_WINDOW` | `60` | Hayır | Rate limit pencere süresi (saniye) |

`API_KEY` ve `REFRESH_TOKEN`'ı boş bırakmak sadece hızlı test/deneme
amaçlıdır — gerçek kullanıcıların erişeceği bir prod ortamında **ikisini de
mutlaka doldur**.

---

## Deploy Sonrası Kontrol Listesi

- [ ] `GET /api.php?health=1` → `{"status":"ok",...}` dönüyor mu?
- [ ] `GET /api.php?type=standings` **key olmadan** 401 dönüyor mu? (API_KEY doğru çalışıyor demektir)
- [ ] `GET /api.php?type=standings` header'da doğru key ile 200 dönüyor mu?
- [ ] `data/` klasörü persistent volume'a bağlı mı? (Container'ı yeniden
      deploy edip veri hâlâ orada mı diye kontrol et)
- [ ] `cron.log` dosyasında (container içinden `cat data/cron.log`) güncelleme
      loglarını görüyor musun?
- [ ] Domain HTTPS ile açılıyor mu (sertifika geçerli mi)?
- [ ] Kulüp logolarını `logos/` klasörüne eklediysen `logo_url` alanı
      response'ta doluyor mu?

Hepsi ✅ ise sistem production'a hazır. Detaylı API kullanımı için
→ [`API-DOCS.md`](./API-DOCS.md)
