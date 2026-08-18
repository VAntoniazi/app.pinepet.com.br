FROM php:8.4-fpm-alpine
RUN apk add --no-cache oniguruma-dev postgresql-dev libsodium-dev curl-dev && docker-php-ext-install -j"$(nproc)" mbstring pdo_pgsql opcache sodium curl
WORKDIR /var/www/html
COPY --chown=www-data:www-data app app
COPY --chown=www-data:www-data bin bin
COPY --chown=www-data:www-data bootstrap bootstrap
COPY --chown=www-data:www-data config config
COPY --chown=www-data:www-data public public
COPY --chown=www-data:www-data resources resources
COPY --chown=www-data:www-data routes routes
COPY --chown=www-data:www-data storage storage
COPY --chown=www-data:www-data database database
COPY --chown=www-data:www-data composer.json composer.json
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini
RUN mkdir -p storage/logs storage/private/certificates && chown -R www-data:www-data storage
EXPOSE 9000
CMD ["php-fpm"]
