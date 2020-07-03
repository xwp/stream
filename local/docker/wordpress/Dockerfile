ARG PHP_VERSION=7.4

FROM wordpress:php${PHP_VERSION}-apache

# Include our Composer vendor binrary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream-src/vendor/bin:${PATH}"

RUN apt-get update; \
	apt-get install -y --no-install-recommends \
	# WP-CLI dependencies.
	bash less default-mysql-client git \
	# MailHog dependencies.
	msmtp;

COPY php.ini /usr/local/etc/php/php.ini

# Setup xdebug.
RUN	pecl install xdebug; \
	docker-php-ext-enable xdebug;

COPY wait.sh /usr/local/bin/xwp_wait
