FROM composer:2.8.12 AS composer
FROM php:8.5.10-cli-bookworm
RUN docker-php-ext-install pdo_mysql
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --optimize
USER www-data
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
