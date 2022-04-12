<?php

namespace BM2\SiteBundle\Twig;

use BM2\SiteBundle\Service\UnitDispatcher;


class UnitDispatcherExtension extends \Twig_Extension implements \Twig\Extension\GlobalsInterface {
    protected $dispatcher;

    public function __construct(UnitDispatcher $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    public function getGlobals() {
        return array(
            'unitDispatcher' => $this->dispatcher
        );
    }

    public function getName() {
        return 'unitDispatcher';
    }

}
