#!/bin/bash
set -e

APP_DIR="/var/www/html"
DATA_DIR="$APP_DIR/data"
DATA_FILE="$DATA_DIR/tff_superlig_data.php"

mkdir -p "$DATA_DIR"
chown -R www-data:www-data "$DATA_DIR"

# ---- Cron job'unu dinamik olarak yaz ----
# CRON_SCHEDULE env değişkeni ile zamanlama değiştirilebilir (varsayılan: 3 saatte bir).
printf 'SHELL=/bin/bash\nPATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\n%s www-data php %s/cron_update.php >> %s/cron.log 2>&1\n' \
    "${CRON_SCHEDULE}" "${APP_DIR}" "${DATA_DIR}" > /etc/cron.d/tff-scraper
chmod 0644 /etc/cron.d/tff-scraper

echo "[entrypoint] Cron zamanlaması ayarlandı: ${CRON_SCHEDULE}"

# ---- İlk açılışta veri dosyası yoksa senkron olarak bir kere scrape et ----
# Böylece container ayağa kalktığı anda API 503 dönmez, elinde veri olur.
if [ ! -f "$DATA_FILE" ]; then
    echo "[entrypoint] Veri dosyası bulunamadı, ilk scrape işlemi başlatılıyor..."
    su -s /bin/bash www-data -c "php ${APP_DIR}/scrape_tff.php" || \
        echo "[entrypoint] UYARI: İlk scrape başarısız oldu, API veri gelene kadar 503 dönecek. Cron bir sonraki denemede tekrar dener."
else
    echo "[entrypoint] Mevcut veri dosyası bulundu, ilk scrape atlanıyor."
fi

# ---- Cron daemon'ı arka planda başlat ----
service cron start
echo "[entrypoint] Cron daemon başlatıldı."

# ---- Ana process'e devret (Apache) ----
exec "$@"
