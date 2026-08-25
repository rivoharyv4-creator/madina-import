# FrankenPHP (PHP 8.3) base image with the gd extension enabled.
#
# phpoffice/phpspreadsheet (required by maatwebsite/excel) declares a hard
# dependency on ext-gd for image processing. The default FrankenPHP image
# does not ship with gd compiled in, which makes `composer install` fail
# during the build with:
#   phpoffice/phpspreadsheet 5.9.0 requires ext-gd * -> it is missing
#
# This Dockerfile extends the official FrankenPHP image, compiles and
# enables gd, copies the composer binary from the official composer image,
# then runs the normal composer/npm build for the app.

# --- Stage 1: composer binary -----------------------------------------------
FROM composer:2 AS composer

# --- Stage 2: application base ----------------------------------------------
FROM dunglas/frankenphp:php8.3 AS base

# --- PHP gd extension -------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        libwebp-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# --- Composer ----------------------------------------------------------------
COPY --from=composer /usr/bin/composer /usr/bin/composer

# --- Node.js (required to build the frontend assets) -----------------------
RUN apt-get update && apt-get install -y --no-install-recommends curl gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

# --- Application build ------------------------------------------------------
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci --include=dev \
    && npm run build \
    && rm -rf /app/node_modules

ENV PORT=8080
EXPOSE 8080

CMD ["sh", "scripts/railway-start.sh"]
