#!/bin/bash


if [ `hostname` == "lemuria.org" ]; then
	sudo -u postgres /usr/bin/dropdb maf-test
	sudo -u postgres /usr/bin/createdb --owner=maf maf-test
	echo 'CREATE EXTENSION postgis; alter table spatial_ref_sys owner to maf' | sudo -u postgres /usr/bin/psql maf-test
	/usr/share/postgresql/9.3/contrib/postgis-2.1/postgis_restore.pl ~maf/maf-test.dump | psql maf-test >/dev/null
else 
	dropdb -U postgres maf-test
	createdb -U postgres -T template_postgis --owner=maf maf-test
	/Applications/MAPPStack/postgresql/share/contrib/postgis-2.1/postgis_restore.pl "/Users/Tom/Documents/BM2/maf-test.dump" | psql maf-test >/dev/null
fi
