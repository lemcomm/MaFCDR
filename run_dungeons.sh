#!/bin/sh

LOGDIR="/var/maf/logs"


php /var/www/maf/app/console --env=prod dungeons:hourly -d 2>&1 > $LOGDIR/run_dungeons.log
