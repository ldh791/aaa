FROM php:8.2-apache

COPY . /var/www/html/

RUN a2enmod rewrite

EXPOSE 80
RUN echo "<Directory /var/www/html>\n\
    AllowOverride All\n\
</Directory>" >> /etc/apache2/apache2.conf
