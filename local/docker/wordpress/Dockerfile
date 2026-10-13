# Keep in sync with the default value of XDEBUG_VERSION below!
# PHPUnit 11 (XWPENG-43) requires PHP ≥ 8.2. Plugin runtime floor is still 7.2
# (XWPENG-46 owns a Requires PHP bump). Use switch-to:php7.4 for old-PHP smoke,
# but composer test / PHPUnit cannot run there.
ARG PHP_VERSION=8.2

FROM wordpress:php${PHP_VERSION}-apache

# Xdebug 3.3.2 supports PHP 8.2. PHP 7.4 rebuilds need 3.1.6
# (`npm run switch-to:php7.4`).
# @link https://xdebug.org/docs/compat
ARG XDEBUG_VERSION=3.3.2

# Include our Composer vendor binary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream-src/vendor/bin:${PATH}"
ENV COMPOSER_HOME=/tmp/.composer

# Debian 11 LTS ended on 2026-08-31. The bullseye-security index on
# deb.debian.org still names revisions whose pool files were removed
# (git 1:2.30.2-1+deb11u5 and the mariadb, curl, and gsasl updates), so
# apt-get install exits 100. Drop that suite; the packages below are in
# the main Bullseye archive. Images that are not Bullseye keep their sources.
RUN if . /etc/os-release && [ "$VERSION_CODENAME" = "bullseye" ]; then \
		sed -i '/debian-security/d' /etc/apt/sources.list; \
	fi \
	&& apt-get update \
	&& apt-get install -y --no-install-recommends \
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
