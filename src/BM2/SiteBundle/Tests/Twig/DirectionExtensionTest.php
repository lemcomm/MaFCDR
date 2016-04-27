<?php

namespace BM2\SiteBundle\Tests\Twig;

use BM2\SiteBundle\Twig\DirectionExtension;


class DirectionExtensionTest extends SimpleTestCase {
	// NOTE: this is mostly to complete paths that the normal game code never takes

	public function testBasics() {
		$test = new DirectionExtension;

		$this->assertEquals('direction.short.north', $test->directionFilter(0));
		$this->assertEquals('direction.long.west', $test->directionFilter(3*pi()/2, true));
		$this->assertEquals('direction.long.north', $test->directionFilter(3.9*pi()/2, true));
	}


}

