# Keep in sync with the default value of XDEBUG_VERSION below!
ARG PHP_VERSION=7.4

FROM wordpress:php${PHP_VERSION}-apache

# The latest Xdebug version which supports PHP 7.4 is 3.1.6.
# @link https://xdebug.org/docs/compat
ARG XDEBUG_VERSION=3.1.6

# Include our Composer vendor binary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream-src/vendor/bin:${PATH}"
ENV COMPOSER_HOME=/tmp/.composer

RUN apt-get update; \
	apt-get install -y --no-install-recommends \
	# WP-CLI dependencies.
	bash less default-mysql-client git \
	# MailHog dependencies.
	msmtp;

COPY php.ini /usr/local/etc/php/php.ini

# Setup xdebug.
RUN pecl install "xdebug-${XDEBUG_VERSION}"
RUN docker-php-ext-enable xdebug
