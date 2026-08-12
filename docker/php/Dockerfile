FROM php:8.5-fpm-bookworm

ARG APP_UID=1000
ARG APP_GID=1000
ARG CHROME_FOR_TESTING_VERSION=150.0.7871.46

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        ca-certificates \
        git \
        libasound2 \
        libatk-bridge2.0-0 \
        libatk1.0-0 \
        libatspi2.0-0 \
        libcairo2 \
        libcups2 \
        libdbus-1-3 \
        libdrm2 \
        libfreetype6-dev \
        libgbm1 \
        libglib2.0-0 \
        libnss3 \
        libpango-1.0-0 \
        libx11-6 \
        libxcb1 \
        libxcomposite1 \
        libxdamage1 \
        libxext6 \
        libxfixes3 \
        libicu-dev \
        libjpeg62-turbo-dev \
        libxkbcommon0 \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxrandr2 \
        libxml2-dev \
        libzip-dev \
        libxslt1-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mbstring \
        pdo_pgsql \
        xsl \
        zip \
    && pecl install xdebug-3.5.3 \
    && pecl clear-cache \
    && pecl install apcu-5.1.28 \
    && docker-php-ext-enable xdebug apcu \
    && rm -rf /var/lib/apt/lists/*

COPY bin/chrome-linux64-${CHROME_FOR_TESTING_VERSION}.zip /tmp/chrome-linux64.zip

RUN mkdir -p /opt/chrome-for-testing \
    && unzip -q /tmp/chrome-linux64.zip -d /opt/chrome-for-testing \
    && rm /tmp/chrome-linux64.zip

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer

ENV PANTHER_CHROME_BINARY=/opt/chrome-for-testing/chrome-linux64/chrome
ENV PANTHER_CHROME_DRIVER_BINARY=/var/www/html/bin/drivers/chromedriver
ENV PANTHER_NO_SANDBOX=1
ENV PANTHER_CHROME_ARGUMENTS="--disable-dev-shm-usage"
ENV PATH="/var/www/html/bin/drivers:${PATH}"

COPY docker/php/conf.d/*.ini /usr/local/etc/php/conf.d/

RUN groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app \
    && mkdir -p /var/www/html /home/app/.composer \
    && chown app:app /var/www/html /home/app/.composer \
    && sed -ri 's/^[;[:space:]]*user[[:space:]]*=.*/user = app/' /usr/local/etc/php-fpm.d/www.conf \
    && sed -ri 's/^[;[:space:]]*group[[:space:]]*=.*/group = app/' /usr/local/etc/php-fpm.d/www.conf \
    && if grep -Eq '^[;[:space:]]*clear_env[[:space:]]*=' /usr/local/etc/php-fpm.d/www.conf; then \
        sed -ri 's/^[;[:space:]]*clear_env[[:space:]]*=.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf; \
    else \
        printf '\nclear_env = no\n' >> /usr/local/etc/php-fpm.d/www.conf; \
    fi

CMD ["php-fpm"]
