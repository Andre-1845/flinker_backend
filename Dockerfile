# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Dockerfile do backend Flinker (Laravel 12 + Filament).
#
# Propósito: rodar o mesmo container em qualquer provedor (Hostinger VPS,
# outra VPS, um PaaS que aceite Dockerfile, etc.) sem depender de como o PHP
# está configurado na máquina host. Dois alvos (targets) a partir do mesmo
# Dockerfile:
#   - `app`   -> PHP-FPM que executa o código Laravel (usado por app/queue/scheduler)
#   - `nginx` -> Nginx servindo os assets estáticos e repassando .php pro `app`
#
# Ver docker-compose.yml para como os dois se conectam.
# ---------------------------------------------------------------------------

# ---- estágio 1: dependências PHP (composer) --------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs \
    --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---- estágio 2: assets do frontend do painel (vite) -------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* ./
# O repo do backend não tem package-lock.json commitado (só o package.json
# padrão do Laravel), então `npm ci` (que exige lockfile) não funciona aqui —
# `npm install` resolve e, se um lockfile for commitado depois, passa a usá-lo
# normalmente.
RUN npm install
COPY resources/ resources/
COPY vite.config.js ./
RUN npm run build

# ---- estágio 3: imagem PHP-FPM base com extensões ---------------------------
FROM php:8.4-fpm-alpine AS php-base

# Nota sobre Redis: a extensão pecl/redis foi deixada de fora de propósito.
# Hoje QUEUE_CONNECTION, CACHE_STORE e SESSION_DRIVER são todos "database"
# (ver .env.example) — nada no app depende de Redis ainda. Se um dia isso
# mudar, basta acrescentar `pecl install redis && docker-php-ext-enable redis`
# aqui; não vale travar o build nisso agora (o pecl.php.net é notoriamente
# instável em builds Docker).
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        icu-data-full \
        oniguruma-dev \
        libxml2-dev \
        gmp-dev \
        supervisor \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        gmp \
        intl \
        gd \
        zip \
        pcntl \
        opcache \
    && apk del --no-cache libpng-dev libjpeg-turbo-dev freetype-dev icu-dev libxml2-dev gmp-dev

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-flinker.ini

WORKDIR /var/www/html

# ---- estágio 4: `app` — código + vendor + assets, pronto pra rodar ----------
FROM php-base AS app

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- estágio 5: `nginx` — serve estáticos + repassa .php pro app -----------
FROM nginx:1.27-alpine AS nginx

COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
