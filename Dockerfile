FROM phpswoole/swoole:4.7-php7.4-alpine

ARG APP_ENV=local
ARG APP_NAME=demo

ENV APP_ENV=$APP_ENV \
    APP_NAME=$APP_NAME \
    SCAN_CACHEABLE=(true)

RUN set -ex \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl \
    && apk update \
    && apk add --no-cache apache2-utils jq libcouchbase=2.10.6-r0 \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libcouchbase-dev=2.10.6-r0 zlib-dev \
    && pecl update-channels \
    && pecl install couchbase-2.6.2 redis-5.3.4 \
    && docker-php-ext-enable couchbase redis

COPY ./ /var/www/
COPY ./docker/rootfilesystem/ /

RUN set -ex \
    && composer install --no-dev -nq --no-progress \
    && php ./hyperf.php \
    && apk del .build-deps \
    && composer clearcache \
    && rm -rf /var/cache/apk/* /tmp/* /usr/share/man /usr/src/php.tar.xz* $HOME/.composer/*-old.phar

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
