# SENTRA Core + Command (Laravel) — nginx (TLS) + php-fpm + queue + scheduler + Reverb.
ARG BASE_IMAGE=ubuntu:24.04
ARG COMPOSER_IMAGE=composer:2
ARG NODE_IMAGE=node:22-bookworm-slim

FROM ${COMPOSER_IMAGE} AS composer-bin

FROM ${BASE_IMAGE} AS php-base
ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl unzip \
       php8.3-cli php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-zip php8.3-intl php8.3-sqlite3 \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS vendor
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

FROM ${NODE_IMAGE} AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM php-base AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends php8.3-fpm nginx supervisor openssl \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default
WORKDIR /var/www/core
COPY --from=vendor /app /var/www/core
COPY --from=assets /app/public/build /var/www/core/public/build
COPY docker/nginx.conf /etc/nginx/conf.d/sentra.conf
COPY docker/php-fpm.conf /etc/php/8.3/fpm/pool.d/zz-sentra.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/sentra.conf
COPY docker/entrypoint.sh /usr/local/bin/sentra-entrypoint
# Izin dinormalkan: build context dari Windows (mis. folder OneDrive) bisa membawa
# atribut read-only sehingga direktori menjadi dr-xr-xr-x di dalam image.
RUN chmod +x /usr/local/bin/sentra-entrypoint \
    && chmod -R u+rwX,go+rX,go-w /var/www/core \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache
EXPOSE 443
HEALTHCHECK --interval=5s --timeout=3s --start-period=20s --retries=30 \
    CMD curl -fsk https://127.0.0.1/up >/dev/null || exit 1
ENTRYPOINT ["/usr/local/bin/sentra-entrypoint"]
