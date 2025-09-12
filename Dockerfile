# --- Estágio 1: O "Builder" ---
FROM php:8.2-cli-alpine AS builder

RUN apk add --no-cache git unzip libzip-dev
RUN docker-php-ext-install pdo_mysql zip
COPY --from=composer/composer:2-bin /composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --no-scripts --optimize-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize && composer run-script post-autoload-dump

# --- Estágio 2: A Imagem Final ---
FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

# Instala as dependências de build, depois as de runtime, e depois limpa
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetds-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
    && apk add --no-cache \
        nginx \
        supervisor \
        mariadb-client \
        freetds \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_dblib \
        gd \
        zip \
    && apk del .build-deps

COPY --chown=www-data:www-data --from=builder /app .
    
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
# CORREÇÃO: Copia o arquivo que força o PHP-FPM a escutar na porta 9000
COPY docker/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# --- SETUP DA APLICAÇÃO DENTRO DO BUILD ---
COPY .env .
RUN php artisan key:generate --force
RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache
RUN php artisan storage:link
# ------------------------------------------

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
