#!/bin/bash

lockfile="/var/www/thor/archiver/archiver.lock"
lock="$(head -1 $lockfile 2>/dev/null)"
#lock="$(head -1 $lockfile)"

#echo "lock was: $lock"

if [ "x$lock" == "xLOCK" ]
then
  echo "LOCK exists (from $(tail -1 $lockfile)), skipping"

else
  echo "LOCK creation ($lockfile)"
  echo "LOCK" > $lockfile
  date >> $lockfile
  php /var/www/thor/archiver/archiver.php
  rm $lockfile
  echo "LOCK released"
  echo
fi
