FROM phpswoole/swoole:4.6-php7.4-alpine

RUN set -ex \
    && apk update \
    && apk add --no-cache libcouchbase=2.10.6-r0 \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libcouchbase-dev=2.10.6-r0 zlib-dev \
    && pecl update-channels \
    && pecl install couchbase-2.6.2 \
    && docker-php-ext-enable couchbase \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/* /usr/share/man /usr/src/php.tar.xz* $HOME/.composer/*-old.phar
