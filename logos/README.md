# Kulüp Logoları

Bu klasöre resmi kulüp logolarını **team_id.png** formatında koy.
team_id değerlerini `api.php?type=clubs` çıktısından görebilirsin, örnek:

```
logos/3604.png   -> Galatasaray A.Ş.  (team_id: 3604)
logos/3592.png   -> Fenerbahçe A.Ş.   (team_id: 3592)
logos/3596.png   -> Trabzonspor A.Ş.  (team_id: 3596)
logos/3688.png   -> Göztepe A.Ş.      (team_id: 3688)
logos/3590.png   -> Beşiktaş A.Ş.     (team_id: 3590)
logos/3597.png   -> Samsunspor A.Ş.   (team_id: 3597)
logos/3665.png   -> RAMS Başakşehir FK
logos/132.png    -> Kocaelispor
logos/3672.png   -> Gaziantep FK A.Ş.
logos/51.png     -> Corendon Alanyaspor
logos/3606.png   -> Gençlerbirliği
logos/3631.png   -> Çaykur Rizespor A.Ş.
logos/3600.png   -> Tümosan Konyaspor
logos/39.png     -> Kasımpaşa A.Ş.
logos/52.png     -> Hesap.com Antalyaspor
logos/72.png     -> Zecorner Kayserispor
logos/3610.png   -> İkas Eyüpspor
logos/3611.png   -> Fatih Karagümrük
```

Dosyayı buraya koyup deploy ettiğinde, `api.php` içindeki `clubs` ve `standings`
kayıtlarında otomatik olarak şu alan görünür:

```json
"logo_url": "https://your-domain.com/logos/3604.png"
```

Dosya yoksa `logo_url` değeri `null` döner, hata vermez.

**Önemli:** Bu klasör public/açık bir klasördür (API key gerektirmez), çünkü
sadece görsel dosyalar barındırır. Resmi logoları kulüplerin kendi medya
kitlerinden veya kendi düzenlediğin/telifi sende olan görsellerden temin et.
