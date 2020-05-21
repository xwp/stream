#!/bin/bash

source ./bin/includes.sh

printf "Shutting down containers ... "

docker-compose down 2>/dev/null

printf "$(action_format "done")"
echo ""

# From: http://patorjk.com/software/taag/#p=display&c=echo&f=Standard&t=Foo%20Bar
echo "  _____             ____             ";
echo " |  ___|__   ___   | __ )  __ _ _ __ ";
echo " | |_ / _ \ / _ \  |  _ \ / _\` | '__|";
echo " |  _| (_) | (_) | | |_) | (_| | |   ";
echo " |_|  \___/ \___/  |____/ \__,_|_|   ";
echo "                                     ";

echo "See you again soon, same bat time, same bat channel?"
echo ""
