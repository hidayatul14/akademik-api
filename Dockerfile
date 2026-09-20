FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libpq-dev libonig-dev libzip-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000

# APP_KEY and database credentials must be supplied by the hosting platform.
CMD ["sh", "-c", "php artisan migrate --force && if [ -n \"${ACADEMIC_DEMO_SEED_COUNT:-}\" ]; then php artisan academic:seed \"$ACADEMIC_DEMO_SEED_COUNT\" --chunk=500 --if-empty || exit 1; fi; exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}"]
