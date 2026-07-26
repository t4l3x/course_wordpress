ARG COMPOSER_IMAGE=composer:2.10.2
ARG WORDPRESS_IMAGE=wordpress:7.0.2-php8.5-fpm-alpine

FROM ${COMPOSER_IMAGE} AS composer

FROM ${WORDPRESS_IMAGE}

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/wordpress/php.ini /usr/local/etc/php/conf.d/zz-development.ini

# wp-phpunit scans this directory before each test; allow arbitrary host UIDs
# used by the Composer tools container to create fixtures inside it.
RUN mkdir -p /usr/src/wordpress/wp-content/uploads \
    && chmod 0777 /usr/src/wordpress/wp-content/uploads
