FROM php:8.2-apache

# Инсталиране на разширения
RUN docker-php-ext-install pdo pdo_mysql

# Активиране на mod_rewrite
RUN a2enmod rewrite

# Копиране на файловете
COPY web/ /var/www/html/

# Права за uploads папката
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/ && \
    chmod -R 755 /var/www/html/

EXPOSE 80