ARG PHP_VERSION

FROM phpswoole/swoole:5.1-php${PHP_VERSION}

ARG APP_ENV=local
ARG APP_NAME=demo
ARG LIBCOUCHBASE_VERSION=3.3.10

ENV APP_ENV=$APP_ENV \
    APP_NAME=$APP_NAME \
    SCAN_CACHEABLE=(true)

RUN \
    set -ex && \
    docker-php-ext-configure pcntl --enable-pcntl && \
    docker-php-ext-install pcntl && \
    apt-get update && \
    apt-get install apache2-utils lsb-release jq -y --no-install-recommends && \
    curl -sfL http://ftp.br.debian.org/debian/pool/main/libe/libevent/libevent-core-2.1-7_2.1.12-stable-1_$(dpkg --print-architecture).deb -o libevent-core.deb && \
    dpkg -i libevent-core.deb && \
    curl -sfL https://github.com/couchbase/libcouchbase/releases/download/${LIBCOUCHBASE_VERSION}/libcouchbase-${LIBCOUCHBASE_VERSION}_debian$(lsb_release -rs)_$(lsb_release -cs)_$(dpkg --print-architecture).tar | tar -C . -x && \
    cd libcouchbase-${LIBCOUCHBASE_VERSION}_debian$(lsb_release -rs)_$(lsb_release -cs)_$(dpkg --print-architecture) && \
    dpkg -i \
        libcouchbase3-tools_${LIBCOUCHBASE_VERSION}-*.deb \
        libcouchbase3-libevent_${LIBCOUCHBASE_VERSION}-*.deb \
        libcouchbase3_${LIBCOUCHBASE_VERSION}-*.deb \
        libcouchbase-dev_${LIBCOUCHBASE_VERSION}-*.deb && \
    cd - && \
    rm -rf libevent-core.deb libcouchbase-${LIBCOUCHBASE_VERSION}_debian$(lsb_release -rs)_$(lsb_release -cs)_$(dpkg --print-architecture) && \
    pecl update-channels && \
    pecl install couchbase-3.2.2 && \
    docker-php-ext-enable couchbase

COPY ./ /var/www/
COPY ./docker/rootfilesystem/ /

RUN \
    set -ex && \
    composer install --no-dev -nq --no-progress && \
    php ./hyperf.php && \
    composer clearcache && \
    rm -rf /var/lib/apt/lists/* /tmp/* /usr/share/man /usr/src/php.tar.xz* $HOME/.composer/*-old.phar

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
