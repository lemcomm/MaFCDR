#!/bin/sh

BACKUPDIR="/home/maf/backups"
LOGDIR="/home/maf/logs"
APP="/home/maf/symfony/app/console"
DAY=`date +%a%H`

pg_dump -Fc -C maf | gzip > $BACKUPDIR/maf-$DAY.sql.gz

# this permission system is so fucked up that just to be sure I have to run this every time or I get exceptions
sudo setfacl -R -m u:www-data:rwX -m u:maf:rwX /home/maf/symfony/app/cache /home/maf/symfony/app/logs /home/maf/symfony/app/spool
sudo setfacl -dR -m u:www-data:rwX -m u:maf:rwX /home/maf/symfony/app/cache /home/maf/symfony/app/logs /home/maf/symfony/app/spool

php $APP maf:process:expires --env=prod 2>&1 > $LOGDIR/turn-$DAY.log
php $APP maf:run -t -d turn --env=prod 2>&1 >> $LOGDIR/turn-$DAY.log
php $APP maf:process:economy --env=prod -t 2>&1 >> $LOGDIR/turn-$DAY.log
php $APP maf:cleanup --env=prod -d 2>&1 >> $LOGDIR/turn-$DAY.log
echo "----- turn done -----" >> $LOGDIR/turn-$DAY.log

mail tom@lemuria.org -s 'MaF Turn' < $LOGDIR/turn-$DAY.log


php $APP maf:stats:turn --env=prod -d 2>&1 > $LOGDIR/stats.log
if [ "$?" -ne "0" ]; then
	# this doesn't actually give any output...
	mail tom@lemuria.org -s 'MaF Stats Problem' < $LOGDIR/stats.log
fi

# map generation
curl -so ~/qgis/maps/allrealms.png "http://maps.mightandfealty.com/qgis?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap&BBOX=0,0,512000,512000&CRS=EPSG:4326&WIDTH=2048&HEIGHT=2048&LAYERS=water,blocked,AllRealms&FORMAT=image/png&map=MapWithRealms.qgs"
curl -so ~/qgis/maps/2ndrealms.png "http://maps.mightandfealty.com/qgis?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap&BBOX=0,0,512000,512000&CRS=EPSG:4326&WIDTH=2048&HEIGHT=2048&LAYERS=water,blocked,2ndLevelRealms&FORMAT=image/png&map=MapWithRealms.qgs"
curl -so ~/qgis/maps/majorrealms.png "http://maps.mightandfealty.com/qgis?SERVICE=WMS&VERSION=1.3.0&REQUEST=GetMap&BBOX=0,0,512000,512000&CRS=EPSG:4326&WIDTH=2048&HEIGHT=2048&LAYERS=water,blocked,MajorRealms&FORMAT=image/png&map=MapWithRealms.qgs"
convert ~/qgis/maps/allrealms.png -resize 256x256 ~/qgis/maps/allrealms-thumb.png
convert ~/qgis/maps/2ndrealms.png -resize 256x256 ~/qgis/maps/2ndrealms-thumb.png
convert ~/qgis/maps/majorrealms.png -resize 256x256 ~/qgis/maps/majorrealms-thumb.png

scp $BACKUPDIR/maf-$DAY.sql.gz flame.lemuria.org:~/backups/
