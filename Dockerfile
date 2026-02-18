FROM php:8.3-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

# Generate key
RUN php artisan key:generate

# Expose Railway port
EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=${PORT}
