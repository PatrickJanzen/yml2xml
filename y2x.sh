#!/usr/bin/env sh
# Absolute path to this script, e.g. /home/user/bin/foo.sh
SCRIPT=$(readlink -f "$0")
# Absolute path this script is in, thus /home/user/bin
SCRIPTPATH=$(dirname "$SCRIPT")

if [ "$1" == "" ] || [ $# -gt 1 ]; then
  $SCRIPTPATH/bin/console help y2x
elif [ "$1" == "help" ]; then
  $SCRIPTPATH/bin/console help y2x
else
  $SCRIPTPATH/bin/console y2x $*
fi

