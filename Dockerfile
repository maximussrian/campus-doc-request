FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts 2>/dev/null || true

COPY . .

RUN mkdir -p storage/sessions storage/logs \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

ENV APP_BASE=

EXPOSE 80
