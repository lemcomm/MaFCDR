<?php

namespace BM2\SiteBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class BM2SiteBundle extends Bundle {

	public function boot() {
		$em = $this->container->get('doctrine.orm.default_entity_manager');
		$conn = $em->getConnection();

		$custom_types = array('geometry', 'point', 'polygon', 'linestring');
		foreach ($custom_types as $type) {
			$conn->getDatabasePlatform()->registerDoctrineTypeMapping(strtoupper($type), strtolower($type));
		}
	}
}
