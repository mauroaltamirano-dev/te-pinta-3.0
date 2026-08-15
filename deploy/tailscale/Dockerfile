ARG SAIL_IMAGE=sail-8.5/app

FROM ${SAIL_IMAGE} AS build

WORKDIR /var/www/html

COPY composer.json composer.lock package.json package-lock.json ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts \
    && npm ci --no-audit --no-fund

COPY . .

RUN mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --no-interaction --optimize \
    && npm run build \
    && test ! -e public/hot \
    && test -f public/build/manifest.json

FROM ${SAIL_IMAGE} AS runtime

WORKDIR /var/www/html

COPY --from=build --chown=sail:sail /var/www/html /var/www/html

RUN rm -rf public/storage \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R sail:sail bootstrap/cache storage

ENV APP_ENV=production \
    APP_DEBUG=false \
    SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"

EXPOSE 80

ENTRYPOINT ["start-container"]
