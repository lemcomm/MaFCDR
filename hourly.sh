#!/bin/sh

LOGDIR="/var/maf/logs"
APP="/var/www/maf/app/console"

#LOGDIR="/tmp"
#APP="./app/console"

# Fix the fucking permissions that never stick:
# sudo setfacl -dR -m u:www-data:rwX -m u:maf:rwX ~/symfony/app/cache ~/symfony/app/logs ~/symfony/app/spool
# sudo setfacl -R -m u:www-data:rwX -m u:maf:rwX ~/symfony/app/cache ~/symfony/app/logs ~/symfony/app/spool

php $APP --env=prod maf:process:activities 2>&1 >> $LOGDIR/hourly.log
php $APP --env=prod maf:process:familiarity -t 2>&1 >> $LOGDIR/hourly.log
php $APP --env=prod maf:process:travel -t 2>&1 >> $LOGDIR/hourly.log
php $APP --env=prod maf:process:spotting -t 2>&1 >> $LOGDIR/hourly.log
php $APP --env=prod maf:run -t -d hourly 2>&1 >> $LOGDIR/hourly.log
php $APP --env=prod dungeons:hourly -d 2>&1 >> $LOGDIR/hourly.log
echo "----- hourly done -----" >> $LOGDIR/hourly.log
