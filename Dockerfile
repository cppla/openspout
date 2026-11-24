FROM php:8.6.0-cli@sha256:d84acf3155d907afaa5afa9b9457c5f12016b6dc7ade84659f40cdc505cc087d

COPY --from=composer:2.10.3@sha256:d0c32820c62cc860fddd22adceed4eeda7698b093a7a88d503414172ce400626 /usr/bin/composer /usr/local/bin/composer

# Xdebug's PHP 8.6 coverage driver is used only by the development image.
RUN set -eu; \
    apt-get update; \
    apt-get install -y --no-install-recommends git libonig-dev libzip-dev; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-source extract; \
    mkdir -p /usr/src/php/ext/xdebug; \
    curl -fsSL --retry 3 https://xdebug.org/files/xdebug-3.6.0alpha1.tgz -o /tmp/xdebug.tgz; \
    echo '5f464dfbba2f2aec384f9a10cb42cbfb2b3c07274b70491c46080b707271fcde  /tmp/xdebug.tgz' | sha256sum -c -; \
    tar -xzf /tmp/xdebug.tgz --strip-components=1 -C /usr/src/php/ext/xdebug; \
    docker-php-ext-install -j"$(nproc)" mbstring zip xdebug; \
    docker-php-source delete; \
    rm /tmp/xdebug.tgz; \
    echo 'xdebug.mode=off' > /usr/local/etc/php/conf.d/zz-xdebug-mode.ini

ARG USER_ID
ARG GROUP_ID

RUN if ! getent group "${GROUP_ID}" >/dev/null; then groupadd --gid "${GROUP_ID}" code; fi \
    && useradd --create-home --shell /bin/bash --uid "${USER_ID}" --gid "${GROUP_ID}" code

USER code
