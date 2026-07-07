FROM php:8.3-fpm-bookworm

ARG APP_UID=1000
ARG APP_GID=1000

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bash \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
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
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer

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
