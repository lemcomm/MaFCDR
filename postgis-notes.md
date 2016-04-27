Postgres and Postgis
====================

backup
------
* pg_dump -Fc -c -C maf > maf.dump


create on Linux server
------
(as user postgres)
createdb --owner=maf maf
psql maf
CREATE EXTENSION postgis;
alter table spatial_ref_sys owner to maf;


create on OS X  (new)
------
createdb -U postgres -T template_postgis --owner=maf maf


restore
-------
(Linux)
/usr/share/postgresql/9.4/contrib/postgis-2.1/postgis_restore.pl maf.sql | psql maf

(OS X - new)
iMac:
/Applications/mampstack-5.6.8-0/postgresql/share/contrib/postgis-2.1/postgis_restore.pl maf-*.sql | psql maf
MacBook Air:
/Applications/mappstack-5.4.28-0/postgresql/share/contrib/postgis-2.1/postgis_restore.pl maf-*.sql | psql maf