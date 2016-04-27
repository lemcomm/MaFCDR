#!/bin/bash

if [ `hostname` == "lemuria.org" ]; then
	echo "acceptance tests";
	/usr/bin/php vendor/bin/codecept run -c src/BM2/SiteBundle/ acceptance
else
	echo "local testing";
	dropdb maf-test
	createdb -U postgres -T template_postgis --owner=maf maf-test
	/Applications/MAPPStack/postgresql/share/contrib/postgis-2.1/postgis_restore.pl "/Users/Tom/Documents/BM2/maf-test.dump" | psql maf-test >/dev/null
	/Applications/MAPPStack/php/bin/php vendor/bin/codecept run -c src/BM2/SiteBundle/ -s acceptance --coverage-html --html
	rm -r "/Users/Tom/Documents/BM2/testreport"
	mkdir "/Users/Tom/Documents/BM2/testreport"
	mv src/BM2/SiteBundle/Tests/_log/* "/Users/Tom/Documents/BM2/testreport/"
	open "/Users/Tom/Documents/BM2/testreport/report.html"
	open "/Users/Tom/Documents/BM2/testreport/coverage/index.html"
fi
