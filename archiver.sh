#!/bin/bash
controller_file="/var/www/vhosts/iocurve/lib/archiver/archiver.controller.php"
lock_file="/opt/archiver/archiver.lock"
lock="$(head -1 $lock_file 2>/dev/null)"
#lock="$(head -1 $lock_file)"

#echo "lock was: $lock"

if [ "x$lock" == "xLOCK" ]
then
  echo "LOCK exists (from $(tail -1 $lock_file)), skipping"

else
  echo "LOCK creation ($lock_file)"
  echo "LOCK" > $lock_file
  date >> $lock_file
  php $controller_file
  rm $lock_file
  echo "LOCK released"
  echo
fi
