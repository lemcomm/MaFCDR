<?php

namespace BM2\SiteBundle\Twig;

use BM2\SiteBundle\Service\AppState;


class AppStateExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface {
	protected $appState;

	public function __construct(AppState $appState) {
		$this->appState = $appState;
	}

	public function getGlobals() {
		return array(
			'appstate' => $this->appState
		);
	}

	public function getName() {
		return 'appstate';
	}

}
