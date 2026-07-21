FROM php:8.2-apache

# ---- Sistem paketleri: cron + gerekli PHP eklentileri ----
RUN apt-get update && apt-get install -y --no-install-recommends \
        cron \
        curl \
        libxml2-dev \
        libonig-dev \
    && docker-php-ext-install dom xml mbstring \
    && a2enmod rewrite headers \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# ---- Apache ayarları ----
# Kök path'te (/) doğrudan api.php'yi göster. sed yerine dosyayı doğrudan
# üzerine yazıyoruz çünkü base image'a göre satır formatı değişebiliyor ve
# sed her zaman eşleşmeyebiliyor.
RUN printf '<IfModule mod_dir.c>\n\tDirectoryIndex api.php index.php index.html\n</IfModule>\n' > /etc/apache2/mods-enabled/dir.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && printf '<Directory "/var/www/html/data">\n\tRequire all denied\n</Directory>\n' > /etc/apache2/conf-enabled/data-deny.conf

WORKDIR /var/www/html

# ---- Uygulama kodu ----
COPY . /var/www/html/

# ---- data/ klasörü: scrape edilen JSON/PHP veri dosyaları + cron.log burada tutulur ----
# Bu klasör docker-compose / Dokploy tarafında volume olarak mount edilmeli ki
# container yeniden oluşturulduğunda veri kaybolmasın.
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data

# ---- Cron job tanımı ----
# Varsayılan: her 3 saatte bir güncelle. Değiştirmek istersen CRON_SCHEDULE env
# değişkenini docker-compose.yml / Dokploy panelinden override edebilirsin.
ENV CRON_SCHEDULE="0 */3 * * *"

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
