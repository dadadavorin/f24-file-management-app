# syntax=docker/dockerfile:1

FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY web/package.json web/package-lock.json ./
RUN npm ci
COPY web/ ./
RUN npm run build

FROM php:8.4-cli-alpine AS backend-build
RUN apk add --no-cache postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY api/ ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache nginx postgresql-libs \
    && apk add --no-cache --virtual .build-deps postgresql-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pgsql \
    && apk del .build-deps

WORKDIR /var/www/html/api
COPY --from=backend-build /app ./
COPY --from=frontend-build /app/dist /var/www/html/web/dist
RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
