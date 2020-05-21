#!/bin/bash

source .env
source ./bin/includes.sh

printf "Starting up containers ..."

# Start the containers.
docker-compose up -d 2>/dev/null

if ! command_exists "curl"; then
	printf " $(action_format "unknown")"
	echo ""
	echo -e "$(error_message "The $(action_format "curl") command is not installed on your host machine.")"
	echo -e "$(warning_message "Checking that WordPress has been installed has failed.")"
	echo -e "$(warning_message "It could take a minute for the Database to become available.")"
else

	# Check for WordPress.
	until is_wp_available "localhost:8088"; do
		printf "."
		sleep 5
	done

	printf " $(action_format "done")"
	echo ""
fi

echo ""
echo "Welcome to:"

# From: http://patorjk.com/software/taag/#p=display&c=echo&f=Standard&t=Foo%20Bar
echo "  _____             ____             ";
echo " |  ___|__   ___   | __ )  __ _ _ __ ";
echo " | |_ / _ \ / _ \  |  _ \ / _\` | '__|";
echo " |  _| (_) | (_) | | |_) | (_| | |   ";
echo " |_|  \___/ \___/  |____/ \__,_|_|   ";
echo "                                     ";

# Give the user more context to what they should do next: Build the plugin and start testing!
echo -e "Run $(action_format "npm run dev") to build the latest version of the Foo Bar plugin,"
echo -e "then open $(action_format "http://localhost:8088/") to get started!"
echo ""
