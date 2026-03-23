FROM php:8.4-fpm

WORKDIR /var/www

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev libzip-dev zip unzip supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP extensions (SQLite-based; no Redis or PostgreSQL needed)
RUN docker-php-ext-install pdo pdo_sqlite mbstring exif pcntl bcmath gd zip

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Application files
COPY . /var/www

# Install PHP dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 755 /var/www/bootstrap/cache

# Supervisor config
COPY docker/supervisor/laravel.conf /etc/supervisor/conf.d/laravel.conf

EXPOSE 8000

ENTRYPOINT ["/var/www/docker/entrypoint.sh"]
