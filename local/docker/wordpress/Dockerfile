FROM wordpress:php7.3-apache

# Include our Composer vendor binrary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream-src/vendor/bin:${PATH}"

# This is somehow required for phpunit tests.
RUN mkdir -p "/var/www/html/wp-content/uploads"

RUN apt-get update; \
	apt-get install -y --no-install-recommends \
	# WP-CLI dependencies.
	bash less mysql-client git \
	# Mailcatcher dependencies.
	ssmtp;

# Setup xdebug.
RUN	pecl install xdebug; \
	docker-php-ext-enable xdebug;
