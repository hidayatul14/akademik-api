FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Install Composer (FIX UTAMA ADA DI SINI)
RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan migrate --force || true

RUN php artisan key:generate || true

EXPOSE 8000

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT}
