FROM php:8.5.10-cli-bookworm

RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY public/ public/
USER www-data
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
