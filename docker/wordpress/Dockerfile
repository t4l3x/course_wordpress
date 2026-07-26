ARG COMPOSER_IMAGE=composer:2.10.2
ARG WORDPRESS_IMAGE=wordpress:7.0.2-php8.5-fpm-alpine

FROM ${COMPOSER_IMAGE} AS composer

FROM ${WORDPRESS_IMAGE}

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/wordpress/php.ini /usr/local/etc/php/conf.d/zz-development.ini

