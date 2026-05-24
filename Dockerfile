FROM php:8.3-cli-alpine

RUN apk add --no-cache git unzip curl-dev \
 && rm -rf /var/cache/apk/*

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
