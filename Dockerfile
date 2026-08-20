# ---- Stage 1: build the frontend assets (compiled Tailwind CSS via Vite) ----
FROM node:20-bookworm-slim AS assets

WORKDIR /app
COPY package.json ./
RUN npm install

# Needs the full source since Tailwind scans .blade.php files to know which
# classes are actually used.
COPY . .
RUN npm run build


# ---- Stage 2: the PHP application (unchanged from before, plus one COPY) ----
FROM php:8.4-cli

RUN apt-get update && apt-get install -y unzip git && rm -rf /var/lib/apt/lists/*

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd pdo_mysql pdo_sqlite mbstring xml zip bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Pull in the compiled CSS/JS built in stage 1 above.
COPY --from=assets /app/public/build ./public/build

RUN composer config policy.advisories.block false

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8080

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080
