FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    intl \
    mbstring

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files
COPY src/composer.json ./

RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

COPY src/ ./

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
