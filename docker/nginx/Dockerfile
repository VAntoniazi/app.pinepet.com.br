FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public

RUN find /var/www/html/public -type d -exec chmod 755 {} + \
    && find /var/www/html/public -type f -exec chmod 644 {} +
