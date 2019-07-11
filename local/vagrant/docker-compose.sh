#!/bin/bash

# Enable verbose output.
set -x

DOCKER_COMPOSE_FILE=${DOCKER_COMPOSE_FILE:-"docker-compose.yml"}

# Run a docker-compose task.
docker-compose --file "$DOCKER_COMPOSE_FILE" "${@}"
