Making a Realm Movie:
=====================

http://www.itforeveryone.co.uk/image-to-video.html

=> ffmpeg -r 30 -qscale 2 -i realm-[me]-%05d.png output.mp4 


Get Times:
----------
select min(cycle), max(cycle) from statistic.settlement where realm_id = [me];

Gather all Subrealms:
---------------------
app/console maf:realm:historic [me] [cycle]

Get Max Extents:
----------------
select ST_Extent(g.poly) from geodata g join settlement s on s.geo_data_id = g.id where s.id in (select distinct settlement_id from statistic.settlement where realm_id IN ([all_subs]) );

Get (simplified) Polygon for Specific Cycle:
-------------------------------
echo 'select ST_AsText(ST_SnapToGrid(ST_Simplify(ST_Translate(ST_Scale(ST_Union(g.poly), 0.002, -0.002),0,1024),1),1)) from geodata g where id in (select distinct settlement_id from statistic.settlement where realm_id IN ([all_subs]) and cycle = [cycle])' | psql -t -q maf

with:
* simplify to reduce point counts
* scale to bring it from 0-512000 down to 0-1024
* snapToGrid to get full pixel coordinates
* coordinate transformation


what else to use:

* offset coordinates - http://postgis.net/docs/ST_Translate.html
* drawing with imagemagick - http://www.imagemagick.org/Usage/draw/#primitives
* aspect ratio and such? Or always use the base image?


