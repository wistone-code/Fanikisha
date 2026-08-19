FROM php:8.3-cli

# System packages needed to build the PHP extensions below, plus zip/unzip for Composer.
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# gd is required by phpoffice/phpspreadsheet (the Excel export feature) — this is the
# extension that was missing from Railway's default build environment and caused
# `composer install` to fail with "ext-gd * -> it is missing from your system".
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql mbstring xml zip bcmath

# Official Composer binary, copied in rather than installed via a separate script —
# this is the standard, well-documented pattern for adding Composer to a PHP image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# Railway's "Custom Start Command" setting (already configured in the dashboard)
# overrides this CMD when set, so this is a fallback for completeness/consistency —
# both run the exact same command either way.
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT