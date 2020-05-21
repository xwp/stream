#!/bin/bash

docker-compose run --rm -u 1000 --workdir=/var/www/html/wp-content/plugins/stream wordpress -- composer test
