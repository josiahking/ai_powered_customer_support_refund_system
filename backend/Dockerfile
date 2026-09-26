FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install dom pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8000

CMD ["sh", "-c", "if [ -z \"$APP_KEY\" ]; then export APP_KEY=\"$(php artisan key:generate --show)\"; fi && php artisan migrate --force --no-interaction && exec php artisan serve --host=0.0.0.0 --port=8000"]