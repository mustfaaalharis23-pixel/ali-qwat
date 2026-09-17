# syntax=docker/dockerfile:1

# The panel's stylesheet and scripts, built once here so no build tool is
# needed on the server.
FROM node:22-alpine AS assets

WORKDIR /app

# Browsershot's browser is deliberately absent: this deployment is for trying
# the screens out, and pulling Chromium in would multiply the image size.
ENV PUPPETEER_SKIP_DOWNLOAD=true

COPY package.json package-lock.json vite.config.js ./
RUN npm ci --ignore-scripts

COPY resources ./resources
RUN npm run build


# The application itself.
FROM php:8.4-cli-bookworm

# libpq for Postgres, ICU for the panel's dates and numbers, zip for the
# spreadsheet exports.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# The dependencies come first, so changing a line of code does not reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public/build ./public/build

# Filament keeps its own assets out of the repository, so they are published
# here. The throwaway .env lives only while artisan runs during the build: at
# run time the service reads its settings from the environment Render sets.
RUN cp .env.example .env \
    && php artisan key:generate --force \
    && composer dump-autoload --no-dev --optimize \
    && php artisan filament:upgrade \
    && rm .env

RUN chmod +x docker/start.sh \
    && chmod -R ug+w storage bootstrap/cache

# The built-in server runs one request at a time by default, and a Filament
# screen fires several at once.
ENV PHP_CLI_SERVER_WORKERS=4

CMD ["docker/start.sh"]
