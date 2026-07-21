# Skor API — TFF Süper Lig Veri API'si

TFF Trendyol Süper Lig'in fikstür, puan cetveli, kulüp ve gol krallığı
verilerini periyodik olarak çekip **kendi JSON API'n** üzerinden sunan,
Docker ile tek komutla ayağa kaldırabileceğin self-hosted bir servis.

Bu proje **senin kendi sunucunda, kendi domain'inde, kendi API key'inle**
çalışacak şekilde tasarlandı. Kimseyle paylaşılan ortak bir sunucu yok —
klonla, kendi ayarlarını gir, kendi altyapına deploy et.

## Özellikler

- ⚽ Fikstür, puan cetveli, kulüpler, gol krallığı — hepsi JSON API olarak
- 🔄 Otomatik periyodik güncelleme (cron ile, sıklığı sen belirlersin)
- 🔑 API key koruması (kendi key'ini kendin belirlersin)
- 🚦 IP bazlı rate limiting (istek sınırı, ayarlanabilir)
- 🖼️ Kulüp logosu desteği (kendi logolarını eklersin)
- 🐳 Tek `Dockerfile` ile Dokploy, Coolify, veya herhangi bir Docker
  ortamında deploy edilebilir
- 💾 Veri kalıcı bir volume'da tutulur, container yeniden oluşturulsa bile
  kaybolmaz

## Hızlı Başlangıç

1. Bu repoyu klonla / fork'la:
   ```bash
   git clone <bu-repo-url> skor-api
   cd skor-api
   ```
2. Kendi sunucunda (Dokploy veya Coolify) yeni bir uygulama oluştur, bu
   repoyu bağla, build tipini **Dockerfile** seç. Detaylı adımlar için
   → [`DEPLOY.md`](./DEPLOY.md)
3. Ortam değişkenlerini (env vars) kendi değerlerinle gir: `API_KEY`,
   `REFRESH_TOKEN`, `CRON_SCHEDULE`, `RATE_LIMIT_MAX`, `RATE_LIMIT_WINDOW`
   → [`DEPLOY.md`](./DEPLOY.md#ortam-değişkenleri) içinde tüm detaylar var.
4. Domain bağla, deploy et.
5. API'ni kullanmaya başla → tüm endpoint'ler ve örnekler için
   [`API-DOCS.md`](./API-DOCS.md)

## Yerelde Test Etme

```bash
docker compose build
docker compose up -d
curl "http://localhost:8080/api.php?health=1"
```

`docker-compose.yml` içindeki `API_KEY`, `REFRESH_TOKEN` değerlerini kendi
değerlerinle değiştirmeyi unutma (varsayılan değerler sadece örnektir,
prod'da kullanma).

## Dosya Yapısı

```
.
├── Dockerfile              # PHP 8.2 + Apache + cron
├── docker-compose.yml      # Yerel test / referans compose dosyası
├── docker-entrypoint.sh    # Container açılışında cron kurar, ilk veriyi çeker
├── api.php                 # Ana API endpoint'i
├── refresh.php             # Manuel veri yenileme (admin, token korumalı)
├── results.php             # Basit HTML görünümü (tarayıcıdan kontrol için)
├── scrape_tff.php          # TFF'den veri çekme motoru
├── cron_update.php         # Cron tarafından çağrılan güncelleme script'i
├── data/                   # Scrape edilen veri burada tutulur (volume mount edilmeli)
├── logos/                  # Kulüp logoları buraya eklenir (bkz. logos/README.md)
├── DEPLOY.md                # Deploy rehberi (Dokploy + Coolify)
└── API-DOCS.md               # Tam API referansı
```

## Sorumluluk Reddi

Bu proje TFF'nin herkese açık web sayfalarından veri kazır (web scraping).
TFF ile resmi bir bağlantısı/ortaklığı yoktur. TFF sitesinin yapısı
değişirse veri çekme işlemi bozulabilir. Kullanım koşulları ve veri
kullanım hakları konusunda kendi sorumluluğundadır.

## Lisans

Bu proje MIT Lisansı altındadır (bkz. `LICENSE` dosyası, eğer repoya
eklenmemişse kendi lisans tercihini eklemekte serbestsin).
