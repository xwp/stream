#!/bin/bash

set -e

export WP_TESTS_DIR=/tmp/wordpress-tests/
export PLUGIN_DIR=$(pwd)
export PLUGIN_SLUG=$(basename $(pwd) | sed 's/^wp-//')
export PHPCS_DIR=/tmp/phpcs
export PHPCS_GITHUB_SRC=squizlabs/PHP_CodeSniffer
export PHPCS_GIT_TREE=master
export PHPCS_IGNORE='tests/*,vendor/*'
export WPCS_DIR=/tmp/wpcs
export WPCS_GITHUB_SRC=WordPress-Coding-Standards/WordPress-Coding-Standards
export WPCS_GIT_TREE=master
export YUI_COMPRESSOR_CHECK=1
export DISALLOW_EXECUTE_BIT=0
export PATH_INCLUDES=./
export WPCS_STANDARD=$(if [ -e phpcs.ruleset.xml ]; then echo phpcs.ruleset.xml; else echo WordPress-Core; fi)
if [ -e .ci-env.sh ]; then source .ci-env.sh; fi
if [ -e phpunit.xml ] || [ -e phpunit.xml.dist ]; then wget -O /tmp/install-wp-tests.sh https://raw.githubusercontent.com/wp-cli/wp-cli/master/templates/install-wp-tests.sh; bash /tmp/install-wp-tests.sh wordpress_test root '' localhost $WP_VERSION; cd /tmp/wordpress/wp-content/plugins; ln -s $PLUGIN_DIR $PLUGIN_SLUG; cd $PLUGIN_DIR; fi
mkdir -p $PHPCS_DIR && curl -L https://github.com/$PHPCS_GITHUB_SRC/archive/$PHPCS_GIT_TREE.tar.gz | tar xvz --strip-components=1 -C $PHPCS_DIR
mkdir -p $WPCS_DIR && curl -L https://github.com/$WPCS_GITHUB_SRC/archive/$WPCS_GIT_TREE.tar.gz | tar xvz --strip-components=1 -C $WPCS_DIR
$PHPCS_DIR/scripts/phpcs --config-set installed_paths $WPCS_DIR
npm install -g jshint
if [ -e composer.json ]; then wget http://getcomposer.org/composer.phar && php composer.phar install --dev; fi

set +e
