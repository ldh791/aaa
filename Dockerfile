FROM php:8.2-apache

WORKDIR /var/www/html

RUN a2enmod rewrite headers \
    && docker-php-ext-install fileinfo

COPY . /var/www/html/

RUN mkdir -p /var/www/html/storage /var/www/html/storage/data/boards /var/www/html/storage/logs /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/html/storage /var/www/html/public/uploads

RUN cat <<'APACHE_CONF' > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /proc/self/fd/2
    CustomLog /proc/self/fd/1 combined
</VirtualHost>
APACHE_CONF

EXPOSE 80
