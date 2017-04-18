<?php

namespace BM2\SiteBundle\Twig;

use BM2\SiteBundle\Service\Dispatcher;


class DispatcherExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface {
    protected $dispatcher;

    public function __construct(Dispatcher $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    public function getGlobals() {
        return array(
            'dispatcher' => $this->dispatcher
        );
    }

    public function getName() {
        return 'dispatcher';
    }

}
