FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libicu-dev libonig-dev libpng-dev libjpeg62-turbo-dev unzip \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring intl zip gd opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
COPY deploy/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/php.ini /usr/local/etc/php/conf.d/clinic.ini
RUN mkdir -p bootstrap/cache storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader \
    && chmod +x deploy/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 80
ENTRYPOINT ["/var/www/html/deploy/entrypoint.sh"]
CMD ["apache2-foreground"]
