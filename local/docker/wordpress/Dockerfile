FROM wordpress:php7.3-apache

# Include our Composer vendor binrary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream-src/vendor/bin:${PATH}"

RUN apt-get update; \
	apt-get install -y --no-install-recommends \
	# WP-CLI dependencies.
	bash less default-mysql-client git \
	# Mailcatcher dependencies.
	msmtp;

# Setup xdebug.
RUN	pecl install xdebug; \
	docker-php-ext-enable xdebug;
