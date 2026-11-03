FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
        git \
        unzip \
        icu-libs \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
    && docker-php-ext-install \
        intl \
        zip \
        pdo_mysql \
    && apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

RUN addgroup -g 1000 app && adduser -G app -u 1000 -D app
USER app

CMD ["php-fpm"]
