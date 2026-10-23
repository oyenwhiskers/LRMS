# LRMS — production image for Dokploy (PHP-FPM + Nginx + queue + scheduler)
# Port: 80 | Health: /up

# --- Composer deps (also needed so Tailwind can scan vendor Blade views) ---
FROM composer:2 AS vendor
WORKDIR /app
# Dummy key so artisan / package discover can run during build.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && php artisan package:discover --ansi

# --- Vite / Tailwind build ---
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY --from=vendor /app /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci && npm run build

# --- Runtime: PHP-FPM + Nginx ---
FROM php:8.2-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        git \
        unzip \
        curl \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        intl \
        bcmath \
        exif \
        pcntl \
        opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/lrms.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/lrms.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
