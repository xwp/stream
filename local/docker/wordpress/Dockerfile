FROM wordpress:php7.3-apache

# Include our Composer vendor binrary path into global path.
ENV PATH="/var/www/html/wp-content/plugins/stream/vendor/bin:${PATH}"

RUN apt-get update; \
	apt-get install -y --no-install-recommends \
	# WP-CLI dependencies.
	bash less mysql-client \
	# Mailcatcher dependencies.
	ssmtp;

# Setup xdebug.
RUN	pecl install xdebug; \
	docker-php-ext-enable xdebug;
