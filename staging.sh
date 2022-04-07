#!/bin/sh

sudo /etc/init.d/apache2 stop
sudo -u www-data /bin/rm -rf app/cache/dev/* app/cache/test/* app/cache/prod/*
/bin/rm -rf app/cache/dev/* app/cache/test/* app/cache/prod/*
php ~/composer.phar dump-autoload --optimize
php app/console cache:clear --env=prod
php app/console doctrine:generate:entities BM2 --no-backup
php app/console assets:install
#php app/console cache:warmup --env=test
php app/console cache:warmup --env=prod
php apc-clear.php
#sudo chown -R www-data app/cache app/logs

# FIXME: add a call to some update script here to update the database where necessary.

sudo /etc/init.d/apache2 start
#ln -s /home/maf/apc.php web/apc.php

# TODO: create static CSS file for use by minify:
#ssh battlemaster.org 'cd /var/bm2/css ; php style.php > style.css'

# Fix the fucking permissions that never stick:
echo "fixing permissions"
sudo /usr/bin/setfacl -dR -m u:www-data:rwX -m u:maf:rwX /var/www/maf/app/cache /var/www/maf/app/logs
sudo /usr/bin/setfacl -R -m u:www-data:rwX -m u:maf:rwX /var/www/maf/app/cache /var/www/maf/app/logs
