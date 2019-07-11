#!/bin/bash

# Enable verbose output.
set -x

DOCKER_COMPOSE_FILE=${DOCKER_COMPOSE_FILE:-"docker-compose.yml"}

# Install WP multisite.
docker-compose --file "$DOCKER_COMPOSE_FILE" run --user 1000 -T wordpress \
	wp core multisite-install \
		--skip-config \
		--subdomains \
		--url=stream.local \
		--title="Stream Development" \
		--admin_user=admin \
		--admin_password=password \
		--admin_email=admin@example.com
