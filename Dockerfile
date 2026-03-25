FROM php:8.2-apache

# 코드 복사
COPY . /var/www/html/

# mod_rewrite 활성화
RUN a2enmod rewrite

# 🔥 Apache 설정 완전 수정
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
