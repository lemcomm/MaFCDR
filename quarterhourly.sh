#!/bin/sh

LOGDIR="/var/www/maf/app/logs"
APP="/var/www/maf/app/console"

#LOGDIR="/tmp"
#APP="./app/console"

# Fix the fucking permissions that never stick:
# sudo setfacl -dR -m u:www-data:rwX -m u:maf:rwX ~/symfony/app/cache ~/symfony/app/logs ~/symfony/app/spool
# sudo setfacl -R -m u:www-data:rwX -m u:maf:rwX ~/symfony/app/cache ~/symfony/app/logs ~/symfony/app/spool


php $APP --env=prod maf:process:battles -t 5 2>&1 >> $LOGDIR/quarterhourly.log
php $APP --env=prod maf:mail 2>&1 >> $LOGDIR/quarterhourly.log
