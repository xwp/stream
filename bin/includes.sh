#!/bin/bash

##
# WordPress helper
#
# Executes a request in the WordPress container.
#
# @param {string} host The host to check.
#
# @return {bool} Whether the host exists or not.
##
function is_wp_available() {
	RESULT=`curl -w "%{http_code}" -o /dev/null -s $1`

	if test "$RESULT" -ge 200 && test "$RESULT" -le 302; then
		return 0
	else
		return 1
	fi
}

##
# Check if the command exists as some sort of executable.
#
# The executable form of the command could be an alias, function, builtin, executable file or shell keyword.
#
# @param {string} command The command to check.
#
# @return {bool} Whether the command exists or not.
##
command_exists() {
	type -t "$1" >/dev/null 2>&1
}

##
# Add error message formatting to a string, and echo it.
#
# @param {string} message The string to add formatting to.
##
error_message() {
	echo -en "\033[31mERROR\033[0m: $1"
}

##
# Add warning message formatting to a string, and echo it.
#
# @param {string} message The string to add formatting to.
##
warning_message() {
	echo -en "\033[33mWARNING\033[0m: $1"
}

##
# Add formatting to an action string.
#
# @param {string} message The string to add formatting to.
##
action_format() {
	echo -en "\033[32m$1\033[0m"
}
