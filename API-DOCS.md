# API Dokümantasyonu

TFF Trendyol Süper Lig fikstür, puan cetveli, kulüp ve gol krallığı
verilerini JSON olarak sunan API'nin tam referansı.

> Bu doküman genel/generic'tir — kendi kurduğun instance'ta domain'in ne
> olursa olsun aynı şekilde çalışır. Aşağıdaki örneklerde geçen
> `https://your-domain.com` yerine **kendi domain'ini** koy.

---

## 1. Base URL

```
https://your-domain.com
```

(Kendi deploy'unda bağladığın domain — bkz. [`DEPLOY.md`](./DEPLOY.md))

Tüm endpoint'ler bu adresin altında, `GET` metoduyla çalışır. API'nin tamamı
`api.php` dosyası üzerinden servis edilir; farklı veri tipleri `?type=...`
query parametresiyle seçilir.

---

## 2. Kimlik Doğrulama (API Key)

Deploy sırasında `API_KEY` ortam değişkenini tanımladıysan (production'da
mutlaka tanımlamalısın), **her istek** aşağıdaki iki yoldan biriyle key
göndermek zorundadır:

**Yöntem 1 — Header (önerilen):**
```
X-Api-Key: SENIN_BELIRLEDIGIN_API_KEY
```

**Yöntem 2 — Query parametresi:**
```
?api_key=SENIN_BELIRLEDIGIN_API_KEY
```

Key eksik veya yanlışsa:

```json
HTTP 401 Unauthorized
{
  "status": "error",
  "message": "Geçersiz veya eksik API key."
}
```

**Muaf endpoint:** `?health=1` (health check) key gerektirmez — izleme/monitoring
araçlarının key'e ihtiyaç duymadan container'ın ayakta olup olmadığını
kontrol edebilmesi içindir.

> `API_KEY` ortam değişkenini deploy sırasında **tanımlamazsan**, API
> tamamen açık çalışır (geriye dönük uyumluluk için varsayılan budur).
> Prod'da mutlaka tanımla.

> ⚠️ Bu key, uygulamanın **tüm kullanıcıları arasında paylaşılan tek bir
> sabit değerdir** (mobil app'in/frontend'in içine gömülü). Kişiye özel
> kimlik doğrulama değildir, sadece "bu istek benim uygulamamdan mı
> geliyor" kontrolü sağlar.

---

## 3. Rate Limiting (İstek Sınırı)

IP adresi başına, sabit pencereli (fixed-window) bir sınır uygulanır.

| Ortam Değişkeni | Varsayılan | Açıklama |
|---|---|---|
| `RATE_LIMIT_MAX` | `60` | Pencere içinde izin verilen maksimum istek |
| `RATE_LIMIT_WINDOW` | `60` (saniye) | Pencere süresi |

Varsayılan davranış: **IP başına dakikada 60 istek**.

`RATE_LIMIT_MAX=0` verilirse rate limiting tamamen kapanır.

Limit aşıldığında:

```
HTTP 429 Too Many Requests
Retry-After: 60
```
```json
{
  "status": "error",
  "message": "Çok fazla istek gönderildi. Lütfen 60 saniye sonra tekrar deneyin."
}
```

`?health=1` bu limitten de muaftır.

Gerçek istemci IP'si `X-Forwarded-For` header'ından (reverse proxy
arkasında — Dokploy/Coolify'ın kendi proxy'si Traefik) tespit edilir;
yoksa `REMOTE_ADDR`'a düşer.

---

## 4. Ortak Response Header'ları

Her `api.php` isteğinde şu header'lar döner:

| Header | Değer | Anlamı |
|---|---|---|
| `Content-Type` | `application/json; charset=utf-8` | |
| `Access-Control-Allow-Origin` | `*` | CORS — herhangi bir origin'den istek atılabilir |
| `Access-Control-Allow-Methods` | `GET` | |
| `Access-Control-Allow-Headers` | `Content-Type, X-Api-Key` | |
| `Cache-Control` | `public, max-age=300` | 5 dk boyunca CDN/proxy cache'lenebilir |

---

## 5. Endpoint Referansı

### 5.1 `GET /api.php` — Tüm veri

Parametre yok (veya `type=all`). `fixtures`, `standings`, `clubs`,
`top_scorers` ve `meta` bir arada döner. `standings` ve `clubs` içindeki her
kayda `logo_url` eklenir. `raw_html` bu response'ta **çıkarılır**.

```
GET https://your-domain.com/api.php
X-Api-Key: SENIN_KEYIN
```

```json
{
  "meta": {
    "source": "https://www.tff.org/Default.aspx?pageID=198",
    "scraped_at": "2026-01-13 11:59:45",
    "title": "Trendyol Süper Lig"
  },
  "fixtures": [ ... ],
  "clubs": [ ... ],
  "standings": [ ... ],
  "top_scorers": [ ... ]
}
```

---

### 5.2 `GET /api.php?type=fixtures` — Tüm fikstürler

```
GET https://your-domain.com/api.php?type=fixtures
```

Her maça `home_logo_url` ve `away_logo_url` eklenir.

```json
[
  {
    "week": 1,
    "home_team": "GAZİANTEP FUTBOL KULÜBÜ A.Ş.",
    "away_team": "GALATASARAY A.Ş.",
    "home_id": 3672,
    "away_id": 3604,
    "home_score": 0,
    "away_score": 3,
    "match_id": 283559,
    "status": "completed",
    "home_logo_url": "https://your-domain.com/logos/3672.png",
    "away_logo_url": "https://your-domain.com/logos/3604.png"
  }
]
```

`status` alanı `"completed"` veya `"scheduled"` değerini alır.

---

### 5.3 `GET /api.php?type=fixtures&week=N` — Belirli hafta

```
GET https://your-domain.com/api.php?type=fixtures&week=18
```

`week` 1–34 arası bir tam sayı olmalı. O haftaya ait maçları döner (yukarıdaki
ile aynı obje şeması). Eşleşme yoksa boş dizi `[]` döner (hata değil).

---

### 5.4 `GET /api.php?type=standings` — Puan cetveli

```
GET https://your-domain.com/api.php?type=standings
```

```json
[
  {
    "position": 1,
    "team": "GALATASARAY A.Ş.",
    "team_id": 3604,
    "played": 17,
    "won": 13,
    "drawn": 3,
    "lost": 1,
    "goals_for": 39,
    "goals_against": 12,
    "goal_difference": 27,
    "points": 42,
    "logo_url": "https://your-domain.com/logos/3604.png"
  }
]
```

Sıralama zaten pozisyona göre (1'den 18'e) gelir, ek sıralama yapmana gerek yok.

---

### 5.5 `GET /api.php?type=clubs` — Kulüpler

```
GET https://your-domain.com/api.php?type=clubs
```

```json
[
  {
    "id": 3604,
    "name": "GALATASARAY A.Ş.",
    "url": "https://www.tff.org/Default.aspx?pageID=28&kulupId=3604",
    "logo_url": "https://your-domain.com/logos/3604.png"
  }
]
```

`url` alanı TFF'nin kendi kulüp sayfasına gider (bilgi amaçlı).

---

### 5.6 `GET /api.php?type=top_scorers` — Gol krallığı

```
GET https://your-domain.com/api.php?type=top_scorers
```

```json
[
  {
    "name": "ELDOR SHOMURODOV",
    "team": "RAMS BAŞAKŞEHİR FUTBOL KULÜBÜ",
    "goals": 12,
    "player_id": 2820747,
    "team_id": 3665
  }
]
```

Gole göre azalan sırada gelir. `team_id` alanı mevcut ama **bu endpoint'e
şu an `logo_url` eklenmiyor** — istersen `team_id`'yi `standings`/`clubs`
verisiyle kendi tarafında eşleştirip logoyu oradan alabilirsin.

---

### 5.7 `GET /api.php?type=stats` — Özet istatistik

```
GET https://your-domain.com/api.php?type=stats
```

```json
{
  "total_fixtures": 306,
  "total_clubs": 18,
  "total_standings": 18,
  "total_scorers": 160,
  "weeks_count": 18,
  "last_update": "2026-01-13 11:59:45"
}
```

Uygulamanda "veri en son ne zaman güncellendi" göstermek için `last_update`
alanını kullan.

---

### 5.8 `GET /api.php?health=1` — Health check

**Key gerektirmez, rate limitten muaf.**

```
GET https://your-domain.com/api.php?health=1
```

```json
{ "status": "ok", "time": "2026-07-21T20:00:24+00:00" }
```

Sadece PHP/Apache'in ayakta olduğunu doğrular; veri dosyasının varlığını
veya güncelliğini kontrol etmez. Uptime monitoring araçların (UptimeRobot
vb.) bunu kullanabilir.

---

### 5.9 `GET /refresh.php?token=...` — Manuel veri yenileme (admin)

Bu endpoint **`api.php`'nin bir parçası değildir**, ayrı bir dosyadır ve
`API_KEY`/rate-limit mantığına dahil değildir — kendi `REFRESH_TOKEN`
korumasını kullanır.

```
GET https://your-domain.com/refresh.php?token=SENIN_REFRESH_TOKENIN
```

Çağrıldığında TFF'den **senkron olarak** yeniden scrape yapar (birkaç saniye
sürebilir) ve veriyi diskteki dosyaya yazar.

Başarılı:
```json
{
  "status": "success",
  "message": "Veriler başarıyla güncellendi!",
  "stats": {
    "fixtures": 306,
    "clubs": 18,
    "standings": 18,
    "top_scorers": 160,
    "updated_at": "2026-07-22 10:00:00"
  }
}
```

Token yanlış/eksikse:
```json
HTTP 403
{ "status": "error", "message": "Geçersiz veya eksik token." }
```

> ⚠️ `REFRESH_TOKEN` ortam değişkeni tanımlı değilse bu endpoint **korumasız**
> çalışır — prod'da mutlaka tanımla, yoksa herkes tetikleyip TFF'yi gereksiz
> yorabilir.

Bu endpoint'i mobil app'ten/frontend'den **çağırma** — sadece senin (admin)
manuel tetiklemen için var. Normal veri akışı zaten cron ile otomatik.

---

### 5.10 `/results.php` — Tarayıcı arayüzü (bilgi amaçlı)

JSON değil, HTML döner. API key/rate-limit koruması **yok**. Fikstür ve puan
cetvelini basit bir tabloda gösteren tarayıcı sayfası. Programatik entegrasyon
için kullanılmaz, sadece hızlı görsel kontrol içindir
(`https://your-domain.com/results.php`).

---

## 6. Format Parametresi

Tüm `type` endpoint'lerinde ek olarak `format=text` verilebilir:

```
GET /api.php?type=standings&format=text
```

JSON yerine `<pre>` içinde PHP `print_r` çıktısı döner. Uygulama
entegrasyonunda kullanma, sadece tarayıcıdan debug amaçlıdır. Varsayılan ve
önerilen: `format=json` (parametre verilmezse zaten bu).

---

## 7. Hata Kodları Özeti

| HTTP Kodu | Ne zaman | Response |
|---|---|---|
| `200` | Başarılı | İlgili JSON veri |
| `401` | API key eksik/yanlış | `{"status":"error","message":"Geçersiz veya eksik API key."}` |
| `403` | `refresh.php` token eksik/yanlış | `{"status":"error","message":"Geçersiz veya eksik token."}` |
| `429` | Rate limit aşıldı | `{"status":"error","message":"Çok fazla istek..."}` + `Retry-After` header |
| `503` | Veri dosyası henüz yok (ilk scrape tamamlanmamış) | `{"status":"error","message":"Veri dosyası henüz oluşturulmadı..."}` |
| `500` | Veri dosyası okunamadı/bozuk | `{"status":"error","message":"Veri dosyası okunamadı veya bozuk."}` |

**Client tarafında öneri:** `401` alırsan key konfigürasyon hatası
(uygulama güncellemesi gerekebilir), `429` alırsan `Retry-After` saniye
kadar bekleyip tekrar dene, `503`/`500` alırsan birkaç dakika sonra tekrar
dene (sunucu tarafı geçici sorunu).

---

## 8. Kulüp Logoları

`clubs`, `standings` ve `fixtures` (home/away) response'larına otomatik
`logo_url` alanı eklenir — **eğer** ilgili `team_id.png` dosyası sunucuda
`logos/` klasöründe varsa. Yoksa `null` döner (hata vermez).

### Logoları nereden bulmalısın

Kulüplerin **kendi resmi web sitelerindeki basın/medya kiti** sayfaları en
güvenli kaynaktır. Üçüncü parti sitelerden hotlink etmek hem kırılgan hem
de telif açısından risklidir — logoları indirip kendi `logos/` klasörüne
koymalısın.

### Dosya adlandırma (team_id.png)

| team_id | Takım |
|---|---|
| 3604 | Galatasaray A.Ş. |
| 3592 | Fenerbahçe A.Ş. |
| 3596 | Trabzonspor A.Ş. |
| 3688 | Göztepe A.Ş. |
| 3590 | Beşiktaş A.Ş. |
| 3597 | Samsunspor A.Ş. |
| 3665 | RAMS Başakşehir FK |
| 132 | Kocaelispor |
| 3672 | Gaziantep FK A.Ş. |
| 51 | Corendon Alanyaspor |
| 3606 | Gençlerbirliği |
| 3631 | Çaykur Rizespor A.Ş. |
| 3600 | Tümosan Konyaspor |
| 39 | Kasımpaşa A.Ş. |
| 52 | Hesap.com Antalyaspor |
| 72 | Zecorner Kayserispor |
| 3610 | İkas Eyüpspor |
| 3611 | Fatih Karagümrük |

> Bu ID'ler sezon değişince güncel olmayabilir (kulüp isim sponsorlukları
> değişse de `team_id` genelde sabit kalır) — güncel listeyi her zaman
> `GET /api.php?type=clubs` çağrısından teyit edebilirsin.

Logo dosyaları `https://your-domain.com/logos/{team_id}.png` adresinden
**doğrudan** de erişilebilir (public, key gerektirmez).

---

## 9. Veri Ne Sıklıkla Güncelleniyor?

Veri, bir cron job ile periyodik olarak TFF'den yeniden çekilip diskteki
dosyanın üzerine yazılır (canlı/anlık scraping **değildir** — her API isteği
diskteki hazır dosyayı okur, TFF'ye o an istek atmaz).

| Ortam Değişkeni | Varsayılan | Açıklama |
|---|---|---|
| `CRON_SCHEDULE` | `0 */3 * * *` | Standart cron syntax'ı, varsayılan 3 saatte bir |

Bu değeri deploy sırasında (bkz. [`DEPLOY.md`](./DEPLOY.md)) kendi
tercihine göre ayarlayabilirsin. Anlık son güncelleme zamanını öğrenmek için:

```
GET /api.php?type=stats  →  "last_update" alanı
```

---

## 10. Tüm Ortam Değişkenleri

| Değişken | Varsayılan | Zorunlu mu | Açıklama |
|---|---|---|---|
| `CRON_SCHEDULE` | `0 */3 * * *` | Hayır | Scraping cron zamanlaması |
| `API_KEY` | (boş) | **Prod'da evet** | `api.php` erişim key'i. Boşsa API açık çalışır |
| `REFRESH_TOKEN` | (boş) | **Prod'da evet** | `refresh.php` erişim token'ı. Boşsa endpoint açık çalışır |
| `RATE_LIMIT_MAX` | `60` | Hayır | Pencere başına izin verilen istek sayısı |
| `RATE_LIMIT_WINDOW` | `60` | Hayır | Pencere süresi (saniye) |

Bu değişkenleri nasıl gireceğin platforma göre değişir, detaylar için
→ [`DEPLOY.md`](./DEPLOY.md)

---

## 11. Entegrasyon Örnekleri

Tüm örneklerde `your-domain.com` ve `SENIN_KEYIN` yerini kendi
değerlerinle değiştir.

### cURL
```bash
curl -H "X-Api-Key: SENIN_KEYIN" \
  "https://your-domain.com/api.php?type=standings"
```

### JavaScript (fetch)
```javascript
fetch('https://your-domain.com/api.php?type=standings', {
  headers: { 'X-Api-Key': 'SENIN_KEYIN' }
})
  .then(res => {
    if (res.status === 429) throw new Error('Rate limit, biraz sonra dene');
    if (res.status === 401) throw new Error('API key hatalı');
    return res.json();
  })
  .then(standings => console.log(standings));
```

### Swift (iOS / URLSession)
```swift
var request = URLRequest(url: URL(string: "https://your-domain.com/api.php?type=standings")!)
request.setValue("SENIN_KEYIN", forHTTPHeaderField: "X-Api-Key")

URLSession.shared.dataTask(with: request) { data, response, error in
    guard let data = data,
          let httpResponse = response as? HTTPURLResponse else { return }

    if httpResponse.statusCode == 200 {
        let standings = try? JSONDecoder().decode([Standing].self, from: data)
        // ...
    }
}.resume()
```

### Kotlin (Android / OkHttp)
```kotlin
val request = Request.Builder()
    .url("https://your-domain.com/api.php?type=standings")
    .addHeader("X-Api-Key", "SENIN_KEYIN")
    .build()

client.newCall(request).enqueue(object : Callback {
    override fun onResponse(call: Call, response: Response) {
        when (response.code) {
            200 -> { /* parse JSON */ }
            401 -> { /* key hatası */ }
            429 -> { /* rate limit, Retry-After header'ına bak */ }
        }
    }
    override fun onFailure(call: Call, e: IOException) { /* ağ hatası */ }
})
```

---

## 12. Bilinen Sınırlamalar (Production Notları)

- **Scraper kırılganlığı:** Veri, TFF'nin HTML yapısına bağımlı regex/DOM
  parsing ile çekiliyor. TFF sitesini yeniden tasarlarsa scraper veri
  çekemeyebilir — bu durumda API **hata vermez**, sadece son başarılı
  scrape'teki eski veriyi göstermeye devam eder. `stats` endpoint'indeki
  `last_update` alanını periyodik kontrol etmen (veya bir uyarı mekanizması
  kurman) önerilir.
- **Paylaşılan API key:** `API_KEY` tek bir sabit değer, kullanıcı bazlı
  değil. Kişiye özel erişim/iptal mekanizması yok.
- **Rate limit dosya tabanlı:** Küçük/orta ölçek için yeterli; çok yüksek
  eşzamanlı trafik olursa (binlerce istek/saniye) Redis tabanlı bir çözüme
  geçmek gerekebilir.
- **`top_scorers` içinde `logo_url` yok** (bkz. Bölüm 5.6).
- **Tek sunucu, tek instance varsayımı:** Rate limiting ve dosya tabanlı
  veri, aynı anda birden fazla container instance'ı (horizontal scaling)
  çalıştırıyorsan tutarlı çalışmaz — bu proje tek instance kullanım için
  tasarlandı.

---

## 13. Sorun Giderme (Troubleshooting)

| Belirti | Olası Sebep | Çözüm |
|---|---|---|
| `502 Bad Gateway` | Container ayakta değil / crash oluyor | Platform loglarına bak, container durumunu kontrol et |
| `403 Forbidden` (kök path'te) | `DirectoryIndex` ayarı `api.php`'yi göstermiyor olabilir | Doğrudan `/api.php?type=...` dene |
| Her deploy'da veri sıfırlanıyor | `data/` klasörü volume'a bağlı değil | `DEPLOY.md`'deki persistent storage adımını uygula |
| `logo_url` hep `null` dönüyor | `logos/{team_id}.png` dosyası yok | Bölüm 8'deki tabloya göre dosya ekle, push et |
| `401` her zaman dönüyor (doğru key'e rağmen) | Header adı yanlış gönderiliyor olabilir | Header adının tam olarak `X-Api-Key` olduğundan emin ol |
