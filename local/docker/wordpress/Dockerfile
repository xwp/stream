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

# Enable HTTPS for testing WordPress Application Passwords and the MCP
# Adapter (both flows assume a secure connection). The vhost expects a
# mkcert-generated certificate mounted at /etc/ssl/certs/stream-local;
# see local/scripts/setup-https.sh for one-time generation. Apache stays
# functional on :80 too so HTTP redirects and existing tooling keep working.
COPY apache-ssl.conf /etc/apache2/sites-available/stream-ssl.conf
RUN a2enmod ssl rewrite \
	&& a2ensite stream-ssl \
	&& mkdir -p /etc/ssl/certs/stream-local
