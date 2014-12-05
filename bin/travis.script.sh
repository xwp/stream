#!/bin/bash

set -e
set -v

find $PATH_INCLUDES -path ./bin -prune -o \( -name '*.php' \) -exec php -lf {} \;
if [ -e phpunit.xml ] || [ -e phpunit.xml.dist ]; then phpunit $( if [ -e .coveralls.yml ]; then echo --coverage-clover build/logs/clover.xml; fi ); fi
$PHPCS_DIR/scripts/phpcs --standard=$WPCS_STANDARD $(if [ -n "$PHPCS_IGNORE" ]; then echo --ignore=$PHPCS_IGNORE; fi) $(find $PATH_INCLUDES -name '*.php')
jshint $( if [ -e .jshintignore ]; then echo "--exclude-path .jshintignore"; fi ) $(find $PATH_INCLUDES -name '*.js')
if [ "$YUI_COMPRESSOR_CHECK" == 1 ]; then YUI_COMPRESSOR_PATH=/tmp/yuicompressor-2.4.8.jar; wget -O "$YUI_COMPRESSOR_PATH" https://github.com/yui/yuicompressor/releases/download/v2.4.8/yuicompressor-2.4.8.jar; java -jar "$YUI_COMPRESSOR_PATH" -o /dev/null $(find $PATH_INCLUDES -name '*.js') 2>&1; fi
