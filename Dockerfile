FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    curl \
    bash

RUN docker-php-ext-install pdo pdo_pgsql pdo_sqlite gd zip bcmath

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data storage/ bootstrap/

EXPOSE 9000

CMD ["php-fpm"]
