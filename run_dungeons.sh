#!/bin/sh

LOGDIR="/home/maf/logs"


php ~/symfony/app/console --env=prod dungeons:hourly -d 2>&1 > $LOGDIR/run_dungeons.log
if [ "$?" -ne "0" ]; then
	mail tom@lemuria.org -s 'MaF Dungeons Problem' < $LOGDIR/run_dungeons.log
fi

