FROM php:8.1-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libzip-dev curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        gd \
        intl \
        zip \
    && a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configurar PHP para produção
RUN echo "memory_limit = 256M" >> "$PHP_INI_DIR/conf.d/memory.ini" && \
    echo "max_execution_time = 300" >> "$PHP_INI_DIR/conf.d/timeout.ini" && \
    echo "upload_max_filesize = 20M" >> "$PHP_INI_DIR/conf.d/upload.ini" && \
    echo "post_max_size = 24M" >> "$PHP_INI_DIR/conf.d/upload.ini"

COPY api/ /var/www/html/api/
COPY accounts/ /var/www/html/accounts/
COPY auth/ /var/www/html/auth/
COPY config/ /var/www/html/config/
COPY inc/ /var/www/html/inc/
COPY public/ /var/www/html/public/
COPY resources/ /var/www/html/resources/
COPY src/ /var/www/html/src/

RUN rm -f /var/www/html/.env && \
    chown -R www-data:www-data /var/www/html && \
    find /var/www/html -type d -exec chmod 755 {} \; && \
    find /var/www/html -type f -exec chmod 644 {} \;

USER www-data
EXPOSE 80

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --retries=3 --start-period=15s \
    CMD curl -f http://localhost/index.php?page=api/api_ping || exit 1
