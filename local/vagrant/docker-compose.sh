#!/bin/bash
#
# Helper for running docker-compose commands with custom docker-compose.yml file location.
#

# Enable verbose output.
set -x

DOCKER_COMPOSE_FILE=${DOCKER_COMPOSE_FILE:-"docker-compose.yml"}

# Run a docker-compose task.
docker-compose --file "$DOCKER_COMPOSE_FILE" "${@}"
