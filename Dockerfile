ARG PHP_VERSION

# The base image can be found from the following URLs:
# @see https://github.com/deminy/docker-php-couchbase
# @see https://hub.docker.com/r/deminy/php-couchbase
FROM deminy/php-couchbase:3.2.2-php${PHP_VERSION}

ARG APP_ENV=local
ARG APP_NAME=demo

ENV APP_ENV=$APP_ENV \
    APP_NAME=$APP_NAME \
    SCAN_CACHEABLE=(true)

COPY ./ /var/www/
COPY ./docker/rootfilesystem/ /

RUN \
    set -ex && \
    docker-php-ext-configure pcntl --enable-pcntl && \
    docker-php-ext-install pcntl && \
    apt-get update && \
    apt-get install apache2-utils jq -y --no-install-recommends && \
    composer install --no-dev -nq --no-progress && \
    php ./hyperf.php && \
    composer clearcache && \
    rm -rf /var/lib/apt/lists/* /tmp/* /usr/share/man /usr/src/php.tar.xz* $HOME/.composer/*-old.phar

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
